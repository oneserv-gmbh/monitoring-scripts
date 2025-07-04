#!/usr/bin/perl -w
#
# Copyright (c) 2010 Stéphane Urbanovski <stephane.urbanovski@ac-nancy-metz.fr>
# Copyright (c) 2019 Claudio Kuenzler <ck@claudiokuenzler.com>
#
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; either version 2
# of the License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty
# of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# you should have received a copy of the GNU General Public License
# along with this program (or with Nagios);  if not, write to the
# Free Software Foundation, Inc., 59 Temple Place - Suite 330,
# Boston, MA 02111-1307, USA
#
# $Id: $

use strict;					# should never be differently :-)
use warnings;


use Locale::gettext;
use File::Basename;			# get basename()

use POSIX qw(setlocale);
use Time::HiRes qw(time);			# get microtime
use POSIX qw(mktime);

sub load_module {
    my @names = @_;
    my $module;
    for my $name (@names) {
        my $file = $name;
        # requires need either a bare word or a file name
        $file =~ s{::}{/}gsxm;
        $file .= '.pm';
        eval {
            require $file;
            $name->import();
            $module = $name;
        };
        last if $module;
    }
    return $module;
}

my $plugin_module;
BEGIN {
    $plugin_module = load_module( 'Monitoring::Plugin', 'Nagios::Plugin' );
}

use LWP::UserAgent;			# http client
use HTTP::Request;			# used by LWP::UserAgent
use HTTP::Status;			# to get http err msg
use IO::Socket;				# To use unix Sockets


use Data::Dumper;


my $PROGNAME = basename($0);
'$Revision: 1.1 $' =~ /^.*(\d+\.\d+) \$$/;  # Use The Revision from RCS/CVS/SVN
my $VERSION = $1;

my $DEBUG = 0;
my $TIMEOUT = 9;

# i18n :
setlocale(LC_MESSAGES, '');
textdomain('nagios-plugins-perl');


my $np = $plugin_module->new(
    version => $VERSION,
    blurb => _gt('Plugin to check HAProxy stats url'),
    usage => "Usage: %s [ -v|--verbose ]  -u <url> [-t <timeout>] [-U <username>] [-P <password>] [ -c|--critical=<threshold> ] [ -w|--warning=<threshold> ] [ -b|--critical-backends=<comma separated list> ] [ -i|--ignore-backends=<comma separated list> ]",
    timeout => $TIMEOUT+1
);
$np->add_arg (
    spec => 'debug|d',
    help => _gt('Debug level'),
    default => 0,
);
$np->add_arg (
    spec => 'username|U=s',
    help => _gt('Username for HTTP Auth'),
    required => 0,
);
$np->add_arg (
    spec => 'password|P=s',
    help => _gt('Password for HTTP Auth'),
    required => 0,
);
$np->add_arg (
    spec => 'w=f',
    help => _gt('Warning request time threshold (in seconds)'),
    default => 2,
    label => 'FLOAT'
);
$np->add_arg (
    spec => 'c=f',
    help => _gt('Critical request time threshold (in seconds)'),
    default => 10,
    label => 'FLOAT'
);
$np->add_arg (
    spec => 'url|u=s',
    help => _gt('URL of the HAProxy csv statistics page HTTP or unix Socket.'),
    required => 1,
);
$np->add_arg (
    spec => 'critical-backends|b=s',
    help => _gt('List of critical backend, if set other backend are only warning backend'),
    required => 0,
);
$np->add_arg (
    spec => 'ignore-backends|i=s',
    help => _gt('Comma-separated list of backends to ignore'),
    required => 0,
);


$np->getopts;

$DEBUG = $np->opts->get('debug');
my $verbose = $np->opts->get('verbose');
my $username = $np->opts->get('username');
my $password = $np->opts->get('password');
my $crit_backends = $np->opts->get('critical-backends');
my @crit_backends_list;
if ( defined ( $crit_backends ) ) {
    @crit_backends_list = split(',',$crit_backends);
}
my $ignore_backends = $np->opts->get('ignore-backends');
my @ignore_backends_list;
if ( defined ( $ignore_backends ) ) {
    @ignore_backends_list = split(',',$ignore_backends);
}

# Thresholds :
# time
my $warn_t = $np->opts->get('w');
my $crit_t = $np->opts->get('c');

my $url = $np->opts->get('url');


# Create a LWP user agent object:
my $ua = new LWP::UserAgent(
    'env_proxy' => 0,
    'timeout' => $TIMEOUT,
);
$ua->agent(basename($0));

# Workaround for LWP bug :
$ua->parse_head(0);

# For csv data
my $stats="";

my $timer = time();
if ( $url =~ /^http/ ) {
    if ( defined($ENV{'http_proxy'}) ) {
        # Normal http proxy :
        $ua->proxy(['http'], $ENV{'http_proxy'});
        # Https must use Crypt::SSLeay https proxy (to use CONNECT method instead of GET)
        $ENV{'HTTPS_PROXY'} = $ENV{'http_proxy'};
    }
    # Build and submit an http request :
    my $request = HTTP::Request->new('GET', $url);
    # Authenticate if username and password are supplied
    if ( defined($username) && defined($password) ) {
        $request->authorization_basic($username, $password);
    }
    my $http_response = $ua->request( $request );

    if ( $http_response->is_error() ) {
        my $err = $http_response->code." ".status_message($http_response->code)." (".$http_response->message.")";
        $np->add_message(CRITICAL, _gt("HTTP error: ").$err );
    } elsif ( ! $http_response->is_success() ) {
        my $err = $http_response->code." ".status_message($http_response->code)." (".$http_response->message.")";
        $np->add_message(CRITICAL, _gt("Internal error: ").$err );
    }
    if ( $http_response->is_success() ) {
        $stats = $http_response->content;
    }

}elsif ( $url =~ /^\// ) {
    my $sock = new IO::Socket::UNIX (
        Peer => "$url",
        Type => SOCK_STREAM,
        Timeout => 1);
    if ( !$sock ) {
        my $err = "Can't connect to unix socket";
        $np->add_message(CRITICAL, _gt("Internal error: ").$err );
    }else{
        print $sock "show stat\n";
        while(my $line = <$sock>){
            $stats.=$line;
        }
    }
}else {
    my $err = "Can't detect socket type";
    $np->add_message(CRITICAL, _gt("Internal error: ").$err );
}
$timer = time()-$timer;



my $status = $np->check_threshold(
    'check' => $timer,
    'warning' => $warn_t,
    'critical' => $crit_t,
);

$np->add_perfdata(
    'label' => 't',
    'value' => sprintf('%.6f',$timer),
    'min' => 0,
    'uom' => 's',
    'threshold' => $np->threshold()
);

if ( $status > OK ) {
    $np->add_message($status, sprintf(_gt("Response time degraded: %.6fs !"),$timer) );
}


my $message = 'msg';




($status, $message) = $np->check_messages();

if ( $status == OK && $stats ne "") {

    if ($DEBUG) {
        print "------------------===csv output===------------------\n$stats\n-----------------------------------------------------\n";
        print "t=".$timer."s\n";
    };

    my @fields = ();
    my @rows = split(/\n/,$stats);
    if ( $rows[0] =~ /#\ \w+/ ) {
        $rows[0] =~ s/#\ //;
        @fields = split(/\,/,$rows[0]);
    } else {
        $np->nagios_exit(UNKNOWN, _gt("Can't find csv header !") );
    }

    my %stats = ();
    for ( my $y = 1; $y < $#rows; $y++ ) {
        my @values = split(/\,/,$rows[$y]);
        if ( !defined($stats{$values[0]}) ) {
            $stats{$values[0]} = {};
        }
        if ( !defined($stats{$values[0]}{$values[1]}) ) {
            $stats{$values[0]}{$values[1]} = {};
        }
        for ( my $x = 2,; $x <= $#values; $x++ ) {
            # $stats{pxname}{svname}{valuename}
            $stats{$values[0]}{$values[1]}{$fields[$x]} = $values[$x];
        }
    }
    #print Dumper(\%stats);
    my %stats2 = ();
    my $okMsg = '';
    foreach my $pxname ( keys(%stats) ) {
        if ( defined($ignore_backends) ) {
            if ( grep(/^$pxname$/,@ignore_backends_list) ) {
                logD( sprintf(_gt("Skipping %s because it is defined in ignore list."),$pxname) );
                next;
            }
        }
        $stats2{$pxname} = {
            'act' => 0,
            'acttot' => 0,
            'bck' => 0,
            'bcktot' => 0,
            'scur' => 0,
            'slim' => 0,
            'bin' => 0,
            'bout' => 0,
        };
        foreach my $svname ( keys(%{$stats{$pxname}}) ) {
            if ( $stats{$pxname}{$svname}{'type'} eq '2' ) {
                my $svstatus = $stats{$pxname}{$svname}{'status'} eq 'UP';
                my $active = $stats{$pxname}{$svname}{'act'} eq '1';
                my $activeDescr = $active ? _gt("Active service") :_gt("Backup service") ;
                if ( $stats{$pxname}{$svname}{'status'} eq 'UP' ) {
                    logD( sprintf(_gt("%s '%s' is up on '%s' proxy."),$activeDescr,$svname,$pxname) );
                } elsif ( $stats{$pxname}{$svname}{'status'} eq 'DOWN' ) {
                    if ( defined($crit_backends) ) {
                        if ( grep(/^$pxname$/,@crit_backends_list) ) {
                            $np->add_message(CRITICAL, sprintf(_gt("%s '%s' is DOWN on '%s' proxy !"),$activeDescr,$svname,$pxname) );
                        }else{
                            $np->add_message(WARNING, sprintf(_gt("%s '%s' is DOWN on '%s' proxy !"),$activeDescr,$svname,$pxname) );
                        }
                    }else{
                        $np->add_message(CRITICAL, sprintf(_gt("%s '%s' is DOWN on '%s' proxy !"),$activeDescr,$svname,$pxname) );
                    }
                }
                if ( $stats{$pxname}{$svname}{'act'} eq '1' ) {
                    $stats2{$pxname}{'acttot'}++;
                    $stats2{$pxname}{'act'} += $svstatus;

                } elsif ($stats{$pxname}{$svname}{'bck'} eq '1')  {
                    $stats2{$pxname}{'bcktot'}++;
                    $stats2{$pxname}{'bck'} += $svstatus;
                }
                $stats2{$pxname}{'scur'} += $stats{$pxname}{$svname}{'scur'};
                logD( "Current sessions : ".$stats{$pxname}{$svname}{'scur'} );

            } elsif ( $stats{$pxname}{$svname}{'type'} lt '2' ) {
                $stats2{$pxname}{'slim'} = $stats{$pxname}{$svname}{'slim'};
                $stats2{$pxname}{'bin'} = $stats{$pxname}{$svname}{'bin'};
                $stats2{$pxname}{'bout'} = $stats{$pxname}{$svname}{'bout'};
            }
        }
        if ( $stats2{$pxname}{'acttot'} > 0 ) {
            $okMsg .= ' '.$pxname.' (Active: '.$stats2{$pxname}{'act'}.'/'.$stats2{$pxname}{'acttot'};
            if ( $stats2{$pxname}{'bcktot'} > 0 ) {
                $okMsg .= ' , Backup: '.$stats2{$pxname}{'bck'}.'/'.$stats2{$pxname}{'bcktot'};
            }
            $okMsg .= ')';
            $np->add_perfdata(
                'label' => 'sess_'.$pxname,
                'value' => $stats2{$pxname}{'scur'},
                'min' => 0,
                'uom' => '',
                'max' => $stats2{$pxname}{'slim'},
            );
            $np->add_perfdata(
                'label' => 'bytes_in_'.$pxname,
                'value' => $stats2{$pxname}{'bin'},
                'min' => '0',
                'uom' => 'B',
                'max' => '',
            );
            $np->add_perfdata(
                'label' => 'bytes_out_'.$pxname,
                'value' => $stats2{$pxname}{'bout'},
                'min' => '0',
                'uom' => 'B',
                'max' => '',
            );
        }
    }

    #print Dumper(\%stats2);
    ($status, $message) = $np->check_messages('join' => ' ');

    if ( $status == OK ) {
        $message = $okMsg;

    }

}
#	if ( $verbose ) {
#		($status, $message) = $np->check_messages('join' => '<br/>','join_all' => '<br/>');
#	} else {
#		($status, $message) = $np->check_messages('join' => '<br/>');
#	}


$np->nagios_exit($status, $message );


sub logD {
    print STDERR 'DEBUG:   '.$_[0]."\n" if ($DEBUG);
}
sub logW {
    print STDERR 'WARNING: '.$_[0]."\n" if ($DEBUG);
}
# Gettext wrapper
sub _gt {
    return gettext($_[0]);
}


__END__

=head1 NAME

This Nagios plugins check the statistics url provided by HAProxy (http://haproxy.1wt.eu/).


=head1 NAGIOS CONGIGURATIONS

In F<checkcommands.cfg> you have to add :

	define command {
	  command_name	check_haproxy
	  command_line	$USER1$/check_haproxy.pl -u $ARG1$
	}


In F<services.cfg> you just have to add something like :

	define service {
	  host_name             haproxy.exemple.org
	  normal_check_interval 10
	  retry_check_interval  5
	  contact_groups        linux-admins
	  service_description	HAProxy
	  check_command			check_haproxy!http://haproxy.exemple.org/haproxy?stats;csv
	}

	Or:

	define service {
	  host_name             haproxy.exemple.org
	  normal_check_interval 10
	  retry_check_interval  5
	  contact_groups        linux-admins
	  service_description	HAProxy
	  check_command			check_haproxy!/var/run/my_haproxy.sock
	}

=head1 AUTHOR

Stéphane Urbanovski <stephane.urbanovski@ac-nancy-metz.fr>
David BERARD <david@nfrance.com>
Claudio Kuenzler <ck@claudiokuenzler.com>

=cut