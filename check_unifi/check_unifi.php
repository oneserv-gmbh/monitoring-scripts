#!/usr/bin/php
<?php
/**
 * PHP API usage Unifi Controller by nagios
 *
 * Author : Joerg Hoerter
 *
 * Mail   : nagios@jhoerter.com
 *
 */


/* 
 New UPDATE
 API 1.94
*/

$version="1.20240828";

/* 
Status Array 
*/
$status_ap=array(0=>'CRITICAL',        #offline
                 1=>'OK',              #connected
                 2=>'WARNING',         #pending adoption
                 4=>'WARNING',         #updating
                 5=>'WARNING',         #provisionig 
                 6=>'CRITICAL',        #unreachable
                 7=>'WARNING',         #adopting
                 9 =>'CRITICAL',       #adoption error
                 11=>'CRITICAL'        #isolated
                 );
/* 
Channel Array 
*/
$channel_m=array(32=>array(40=>34), 
                 34=>array(40=>34), 
                 36=>array(40=>38, 80=>42, 160=>50),
                 38=>array(40=>38, 80=>42, 160=>50),
                 40=>array(40=>38, 80=>42, 160=>50),
                 42=>array(80=>42, 160=>50),
                 44=>array(40=>46, 80=>42, 160=>50),
                 46=>array(40=>46, 80=>42, 160=>50),
                 48=>array(40=>46, 80=>42, 160=>50),
                 50=>array(160=>50), 
                 52=>array(40=>42, 80=>58, 160=>50),
                 54=>array(40=>54, 80=>58, 160=>50),
                 56=>array(40=>54, 80=>58, 160=>50),
                 58=>array(80=>58, 160=>50),
                 60=>array(40=>62, 80=>58, 160=>50),
                 62=>array(40=>62, 80=>58, 160=>50),
                 64=>array(40=>62, 80=>58, 160=>50),
                100=>array(40=>102, 80=>106, 160=>114),
                102=>array(40=>102, 80=>106, 160=>114),
                104=>array(40=>102, 80=>106, 160=>114),
                106=>array(80=>106, 160=>114),
                108=>array(40=>110, 80=>106, 160=>114),
                110=>array(40=>110, 80=>106, 160=>114),
                112=>array(40=>110, 80=>106, 160=>114),
                114=>array(160=>114), 
                116=>array(40=>118, 80=>122, 160=>114),
                118=>array(40=>118, 80=>122, 160=>114),
                120=>array(40=>118, 80=>122, 160=>114),
                122=>array(80=>122, 160=>114),
                124=>array(40=>126, 80=>122, 160=>114),
                126=>array(40=>126, 80=>122, 160=>114),
                128=>array(40=>126, 80=>122, 160=>114),
                132=>array(40=>134, 80=>138),
                134=>array(40=>134, 80=>138),
                136=>array(40=>134, 80=>138),
                138=>array(80=>138),
                140=>array(40=>142, 80=>138),
                142=>array(40=>142, 80=>138),
                144=>array(40=>142, 80=>138),
                149=>array(40=>151, 80=>155, 160=>162),
                151=>array(40=>151, 80=>155, 160=>162),
                153=>array(40=>151, 80=>155, 160=>162),
                155=>array(80=>155, 160=>162), 
                157=>array(40=>159, 80=>155, 160=>162),
                159=>array(40=>159, 80=>155, 160=>162),
                161=>array(40=>159, 80=>155, 160=>162),
                163=>array(160=>163),
                165=>array(40=>167, 80=>171, 160=>162),
                167=>array(40=>167, 80=>171, 160=>162),
                169=>array(40=>169, 80=>171, 160=>162),
                171=>array(80=>171, 160=>162),
                173=>array(40=>175, 80=>171, 160=>162),
                175=>array(40=>175, 80=>171, 160=>162),
                177=>array(40=>175, 80=>171, 160=>162));


/* 
function to get uptime
*/
function fct_uptime($uptime) {
 if ( is_numeric($uptime)){
  if(PHP_OS == "Linux") {
    if ($uptime !== false) {
      $uptime = explode(" ",$uptime);
      $uptime = $uptime[0];
      $days = explode(".",(($uptime % 31556926) / 86400));
      $hours = explode(".",((($uptime % 31556926) % 86400) / 3600));
      $minutes = explode(".",(((($uptime % 31556926) % 86400) % 3600) / 60));
      $time = ".";
      if ($minutes > 0)
        $time=$minutes[0]." mins".$time;
      if ($minutes > 0 && ($hours > 0 || $days > 0))
        $time = ", ".$time;
      if ($hours > 0)
        $time = $hours[0]." hours".$time;
      if ($hours > 0 && $days > 0)
        $time = ", ".$time;
      if ($days > 0)
        $time = $days[0]." days".$time;
    } else {
      $time = false;
    }
  } else {
    $time = false;
  }
} else { $time = false; }
  return $time . '| Uptime=' . $uptime ;
}

/* 
function to exit program
*/
function return_status(int $status,$false=false)
{
  exit($status);
}

/* 
function to get state and status
*/
function return_result($status, $msg, $perf="",$false=false)
{
  switch($status)
  {
    case 0: $state = "OK"; break;
    case 1: $state = "WARNING"; break;
    case 2: $state = "CRITICAL"; break;
    default: $state = "UNKNOWN";
  }
  $msg=rtrim($msg);
  $perf=trim($perf);
  if(strlen($msg)>0) $state .= " - " . $msg;
  if(strlen($perf)>0) $state .= " | " . $perf;
  echo $state . PHP_EOL;
  return_status($status);
}

/* 
default values
*/
$phptest='true';
$host='';
$user='';
$pass='';
$prot='http';
$port='8080';
$mode='';
$ap_mode='';
$warn=0;
$crit=0;
$arg=1;
$debug=false;
$help=false;
$config='';
$siteid='default';
$dir=dirname(__FILE__);
$result=0;
$inverse=false;
$host_url='';
$dir_unifi_client='';
$dir_unifi_model='';
$status_code=0;
$logfile=false;

/*
 check php version
*/



/*
 check extensions
*/
if (!extension_loaded('curl')) {
            trigger_error('CRITICVAL - the PHP curl extension is not loaded. Please correct this before proceeding!');
        }
if (!extension_loaded('session')) {
            trigger_error('CRITICVAL - the PHP curl extension is not loaded. Please correct this before proceeding!');
        }



/*
get proram parameters
*/
while ($arg <= $argc) {
switch ( @$argv[$arg] ) {
  case '-H':
     $host=@$argv[$arg+1];
     break;
  case '-m':
     $mode=@$argv[$arg+1];
     break;
  case '-w':
     $warn=@$argv[$arg+1];
     break;
  case '-c':
     $crit=@$argv[$arg+1];
     break;
  case '-a':
     $ap_mode=@$argv[$arg+1];
     break;
  case '-s':
     $prot="https";
     $arg=$arg - 1;
     break;
  case '-u':
     $user=@$argv[$arg+1];
     break;
  case '-p':
     $pass=@$argv[$arg+1];
     break;
  case '-n':
     $switch_port=@$argv[$arg+1];
     break;
  case '-r':
     $result=@$argv[$arg+1];
     break;
  case '-i':
     $arg=$arg - 1;     
     $inverse=true;
     break;
  case '-P':
     $port=@$argv[$arg+1];
     break;
  case '-d':
     $arg=$arg - 1;
     $debug=true;
     break;
  case '-l':
     $arg=$arg - 1;
     $logfile=true;
     break;
  case '-D':
     $dir=@$argv[$arg+1];
     break;
  case '-S':
     $siteid=@$argv[$arg+1];
     break;
  case '-h':
     $help=true;
     $arg=$arg - 1;
     break;
  case '-C':
     $config=@$argv[$arg+1];
     if ( file_exists($config) === false ) 
     { 
     $arg=$arg - 1;
       if ( file_exists(dirname(__FILE__). '/' . $config) === true ) 
         { $config=dirname(__FILE__) . '/' . $config; }
       else  
         { $config=dirname(__FILE__) . '/unifi.php'; }
     } 
     break;
  case '-php':
     $phptest=false;
     $arg=$arg - 1;
     break;

}

if ( $phptest == true ) {
  if ( PHP_VERSION_ID < 70300 ) {
     echo "CRITICAL - php version is lower than 7.3" . PHP_EOL;
     exit(2);
  }
}

$arg = $arg + 2;
}

/* 
get config file
*/
if ( is_file($config)) {
   require_once($config);
  }
elseif ( $config != '' ) {
    echo 'CRITICAL - no config file' . PHP_EOL;
    return_status(2, true);
}

/*
get api client file
*/
if ( $dir_unifi_client == '' ) {
  $dir_unifi_client=dirname(__FILE__); 
}

if ( is_file($dir_unifi_client . '/unifi_client.php')) { 
  require_once($dir_unifi_client . '/unifi_client.php');
}
else { echo 'CRITICAL - no unifi_client.php file found' . PHP_EOL; 
       return_status(2, true);
}

/*
HELP
*/
if ( $help == true || $argc == 1 ) {
   echo PHP_EOL;
   echo 'Usage: /usr/bin/php check_unifi.php -H [controller] -u [controlleruser] -p [controllerpassword] -P [controllerport] -m [mode] -a [Accesspoint/Switch/Client/SSID] -w [Warning] -c [Critical] ( -S [site] -s =https -C [configfile] -r [nagios result] -n switch port-i =invers nagios result -d =debug)' . PHP_EOL;
   echo PHP_EOL;
   echo 'mode - site_console                              = list all site ID and name (only use in console)' . PHP_EOL;
   echo '       devices_console                           = list all devices (only use in console)' . PHP_EOL;
   echo '       clients_wifi_console ( + Accesspoint)     = list all wifi clients with connection speed, optional for one accesspoint (only use in console)' . PHP_EOL;
   echo '       clients_console                           = list all clients (only use in console) ' . PHP_EOL;
   echo PHP_EOL;
   echo '       controller                                = controller Version' . PHP_EOL;
   echo '       clients_all                               = count all clients ' . PHP_EOL;
   echo '       clients_name ( + Accesspoint)             = list all wifi clients off an accesspoint' . PHP_EOL;
   echo '       clients_name_guest ( + Accesspoint)       = list all wifi clients and guests or off an accesspoint' . PHP_EOL;
   echo '       clients_count ( + Accesspoint)            = count wifi clients off an accesspoint' . PHP_EOL;
   echo '       clients_count_guest ( + Accesspoint)      = count wifi clients and guests or off an accesspoint' . PHP_EOL;
   echo '       client_experience + Clientname or IP      = wireless experience off a wifi client with warn/critical' . PHP_EOL;
   echo '       client_transfer + Clientname or IP        = rx/tx transfer off a client (KBit/MBit)' . PHP_EOL;
   echo '       client_transfer+ + Clientname or IP       = rx/tx transfer off a client (KByte/MByte)' . PHP_EOL;
   echo '       client_uplink + Clientname or IP          = uplink off a wifi client' . PHP_EOL;
   echo '       client_uptime + Clientname or IP          = uptime off a client' . PHP_EOL;
   echo '       channels + Accesspoint                    = channels 2GHz/5GHz off an accesspoint' . PHP_EOL;
   echo '       transfer + Accesspoint                    = rx/tx transfer off an accesspoint (KBit/MBit)' . PHP_EOL;
   echo '       transfer+ + Accesspoint                   = rx/tx transfer off an accesspoint (KByte/MByte)' . PHP_EOL;
   echo '       swtich_transfer + Switch                  = rx/tx transfer off a swtich (KBit/MBit)' . PHP_EOL;
   echo '       switch_transfer+ + Switch                 = rx/tx transfer off a switch (KByte/MByte)' . PHP_EOL;
   echo '       uptime + Accesspoint/Switch               = uptime off an accesspoint/switch' . PHP_EOL;
   echo '       update + Accesspoint/Switch               = update firmware is available off an accesspoint/switch and warn/critical (point release, major version, minor version)' . PHP_EOL;
   echo '       uplink + Accesspoint warn crit            = uplink off an accesspoint (wireless: with connection speed and warn/critical)' . PHP_EOL;
   echo '       experience + Accesspoint warn crit        = wireless experience off an accesspoint with warn/critical' . PHP_EOL;
   echo '       utilisation + Accesspoint warn crit       = wireless utilisation off an accesspoint with warn/critical (2GHz,5GHz)' . PHP_EOL;
   echo '       mem + Accesspoint warn crit               = memory usage output Mb off an accesspoint or a switch with warn/critical percent' . PHP_EOL;
   echo '       mem% + Accesspoint warn crit              = memory usage output percent off an accesspoint or a switch with warn/critical percent' . PHP_EOL;
   echo '       cpu + Accesspoint/Switch warn crit        = cpu and load combination usage off an accesspoint or a switch with warn/critical percent' . PHP_EOL;
   echo '       cpu% + Accesspoint/Switch warn crit       = cpu usage off an accesspoint or a switch with warn/critical percent' . PHP_EOL;
   echo '       load + Accesspoint/Switch warn crit       = linux load usage off an accesspoint or a switch with warn/critical 1,5,15' . PHP_EOL;
   echo '       temperature + Switch                      = temperature off a switch with warn/critical' . PHP_EOL;
   echo '       udm_temperature + UDM                     = temperature off an UDM Pro with warn/critical cpu,local,phy' . PHP_EOL;
   echo '       ap warn crit                              = count Accesspoint (Online and Offline) with warn and critical offline Accesspoints' . PHP_EOL;
   echo '       switch warn crit                          = count Switch (Online and Offline) with warn and critical offline Switch' . PHP_EOL;
   echo '       ap_name warn crit                         = count Accesspoint (Online and Offline) with warn and critical named offline Accesspoints' . PHP_EOL;
   echo '       switch_name warn crit                     = count Switch (Online and Offline) with warn and critical named offline Switch' . PHP_EOL;
   echo '       clients_ssid ( + SSID Name)               = count all wifi clients for every ssid or one ssid' . PHP_EOL;
   echo '       clients_count_ssid + SSID Name            = count all wifi clients for one ssid' . PHP_EOL;
   echo '       clients_count_guest_ssid + SSID Name      = count all wifi clients and guest for one ssid' . PHP_EOL;
   echo '       clients_wifi                              = count all clients wifi standards for one site' . PHP_EOL;
   echo '       lte_uplink                                = lte uplink off a LTE AP with warn and critical rssi,rsrq,rsrp' . PHP_EOL;
   echo '       lte_failover                              = lte failover off a LTE AP' . PHP_EOL;
   echo '       lte                                       = lte connection off a LTE AP' . PHP_EOL;
   echo PHP_EOL;
   echo '       Count over all sites:' . PHP_EOL;
   echo '       ap_unifi warn crit                        = count Accesspoint (Online and Offline) with warn and critical offline Accesspoints' . PHP_EOL;
   echo '       switch_unifi warn crit                    = count Switch (Online and Offline) with warn and critical offline Switch' . PHP_EOL;
   echo '       ap_unifi_name warn crit                   = count Accesspoint (Online and Offline) with warn and critical named offline Accesspoints' . PHP_EOL;
   echo '       switch_unifi_name warn crit               = count Switch (Online and Offline) with warn and critical named offline Switch' . PHP_EOL;
   echo '       clients_unifi                             = count all clients ' . PHP_EOL;
   echo '       clients_count_unifi                       = count all wifi clients ' . PHP_EOL;
   echo '       clients_count_guest_unifi                 = count all wifi clients and guests' . PHP_EOL;
   echo '       clients_wifi_unifi                        = count all clients wifi standards' . PHP_EOL;
   echo PHP_EOL;
   echo '       alarms_count                              = count all alarm messages' . PHP_EOL;
   echo PHP_EOL;
   echo '-r   - controller/client_transfer/client_transfer+/client_uplink/clients_ssid' . PHP_EOL;
   echo '       nagios result - new controller version/if a client not present/or if a guest ssid is enabled or disabled' . PHP_EOL;
   echo '       0 = OK (default)' . PHP_EOL; 
   echo '       1 = WARNING' . PHP_EOL; 
   echo '       2 = CRITICAL' . PHP_EOL; 
   echo PHP_EOL;
   echo '-i   - inverse -r (only clients_ssid, if the guest ssid is enabled or disabled)' . PHP_EOL;
   echo PHP_EOL;
   echo '-C   - config file (default=unifi.php) instead off the controller parameters -H,-u,-p,-P,-s -S -U (you can specify any file with path)' . PHP_EOL; 
   echo '       example:' . PHP_EOL;
   echo PHP_EOL;
   echo '-php  - no check of php version' . PHP_EOL;
   echo PHP_EOL;
   echo '       <?php' .PHP_EOL;
   echo '       $host=\'192.168.2.213\';              # IP or Hostname unifi controller Server' . PHP_EOL;
   echo '       $prot=\'https\';                      # Value http or https' . PHP_EOL;
   echo '       $port=\'8443\';                       # Controller Port' . PHP_EOL;
   echo '       $host_url=\'\';                       # Part off url (Cloud Key Gen2=/proxy/network)' . PHP_EOL;
   echo '       $user=\'nagios\';                     # Loginuser Controller' . PHP_EOL;
   echo '       $pass=\'nagios\';                     # Password Loginuser' . PHP_EOL;
   echo '       $siteid=\'default\';                  # Site ID' . PHP_EOL;
   echo '       $dir_unifi_client=\'\';               # Directory unifi_client.php' . PHP_EOL;
   echo '       $status_ap=array(0=>\'CRITICAL\',     # offline' . PHP_EOL;
   echo '                        1=>\'OK\',           # connected' . PHP_EOL;
   echo '                        2=>\'WARNING\',      # pending adoption' . PHP_EOL;
   echo '                        4=>\'WARNING\',      # updating' . PHP_EOL;
   echo '                        5=>\'WARNING\',      # provisionig' . PHP_EOL;
   echo '                        6=>\'CRITICAL\',     # unreachable' . PHP_EOL;
   echo '                        7=>\'WARNING\',      # adopting' . PHP_EOL;
   echo '                        9 =>\'CRITICAL\',    # adoption error' . PHP_EOL;
   echo '                        11=>\'CRITICAL\'     # isolated' . PHP_EOL;
   echo '                        );' . PHP_EOL;
   echo '       ?>' . PHP_EOL;
   echo PHP_EOL;
   echo 'Important: First take a look to the examples on my webpage https://www.jhoerter.com/download/check_unifi-php.html' . PHP_EOL;
   echo 'Version  : ' . $version . PHP_EOL;
   echo 'Contact  : nagios@jhoerter.com' . PHP_EOL;
   echo PHP_EOL;
   echo PHP_EOL;
   return_status(0, true);
}

/* 
get debug information 
*/
if ( $debug === false ) {
  error_reporting(0); }
else {
  error_reporting(-1); }
/* 
get api url
*/
if ($port == '443' )
  { $url=$prot.'://'. $host; }
else
  { $url=$prot.'://'.$host.':'.$port; }

/*
connect to api and read from api
*/
$unifi_connection  = new UniFi_API\Client($user, $pass, $url, $siteid , "6.0.45");
$set_debug_mode    = $unifi_connection->set_debug($debug);
$loginresults      = $unifi_connection->login();
$aps_array         = $unifi_connection->list_devices();
$aps_array_ap      = $unifi_connection->list_devices();
$aps_array_clients = $unifi_connection->list_clients();
$server            = $unifi_connection->stat_sysinfo();
$site_array        = $unifi_connection->list_sites();
$wifi_array        = $unifi_connection->list_wlanconf();
$alarms_count      = $unifi_connection->count_alarms(false);
$alarms_archived      = $unifi_connection->count_alarms(true);

if (empty($server) || $unifi_connection === false) {
           { echo 'CRITICAL - no connection to server' . PHP_EOL;
             return_status(2); }
}

/*
mode program
*/
switch ("$mode") {
  case "ssid":
  case "clients_ssid":
     $num=0;
     $ssid=array();
     foreach($site_array as $site_list) {
       if ( $siteid == $site_list->name ) {
         $siteid=$type_name . ' ['.$site_list->desc . '] - ';
       }
     }
     foreach ($aps_array_clients as $client) {
       if ( $client->is_wired === false ) {
             $num=$num+1;
             $clients=array_push($ssid, $client->essid);
       }
     } 

     $ssid_count=array_count_values($ssid);
     $total=array_sum($ssid_count);
      
     if ( count($wifi_array) > 0 ) {
       $num=0;
       $perf='';
       $msg = 'SSID clients' . $siteid ;
       if ( @$ap_mode == '' ) {
         $total . ' ' ;
       }
       $sv=0; 
       foreach ($wifi_array as $wifi ) {
         if ( @$ap_mode == '' or @$ap_mode == $wifi->name ) {
           if ( $num > 0 ) { 
             $msg .= ', '; }
           $count=number_format($ssid_count[$wifi->name]);

           if ( $wifi->is_guest === false ) {
             if ( $wifi->enabled === true ) {
               $msg .= $wifi->name . ': ' . $count ;
             }
             else {
               $msg .= $wifi->name . ': disabled';
             } 
           }
           else { 
             if ( $wifi->enabled === true ) { 
               $msg .= $wifi->name . '(G): ' . $count ;
               if ( $inverse === true) {
	          $sv = $result; }
               else { $sv = 0; }
             }
             elseif ( $wifi->enabled === false ) {
                $msg .= $wifi->name . '(G): disabled' ;
               if ( $inverse === false ) {  
	          $sv = $result;
               }
	       else { $sv = 0; }
             }
           }
           $perf=$perf . ' ' . $wifi->name . '=' . $count;
           $num=$num+1;
         } 
       }
       if ( @$ap_mode == '' ) {
         $perf = ' Total=' . $total . $perf; 
       }
       if ( $num == 0)
	 { 
	   $msg .= "unknown SSID " . @$ap_mode;
	   $sv = 3;
       }
       return_result($sv, $msg, $perf);
     }
     else {
       echo 'UNKNOWN - no ssid | Total=0' . PHP_EOL;
       return_status(3);
     }
     break;


  case "client_transfer":
  case "client_transfer+":
    $uptime_start=0;
    $uptime_stop=0;
    $rx_start=0;
    $tx_start=0;
    $rx_stop=0;
    $tx_stop=0;
    $rx_perf=0;
    $tx_perf=0;

    foreach ($aps_array_clients as $client) {
      if ( $ap_mode == $client->name || $ap_mode == $client->ip ) {
        if ( $client->is_wired === false ) {
          foreach ($aps_array as $apname) {
            if ($client->ap_mac === $apname->mac) {
                $connection='(Uplink: ' . $apname->name . ')';
                break;
            }
          }

          $rx_start=$client->{"rx_bytes"};        
          $tx_start=$client->{"tx_bytes"}; 
          $uptime_start=$client->uptime;
          sleep(30);
          $unifi_connection_new  = new UniFi_API\Client($user, $pass, $url, $siteid , "6.0.45");
          $loginresults = $unifi_connection_new->login();
          $aps_new_array_client = $unifi_connection_new->list_clients();
          foreach ($aps_new_array_client as $client_new) {
            if ( $ap_mode == $client_new->name || $ap_mode == $client_new->ip ) {
              $rx_stop=$client_new->{"rx_bytes"};
              $tx_stop=$client_new->{"tx_bytes"};
              $uptime_stop=$client_new->uptime;
              break;
            }
          }
        }
        else { 
          foreach ($aps_array as $apname) {
            if ($client->sw_mac === $apname->mac) {
                $connection='(Uplink: ' . $apname->name . ' P: ' . $client->sw_port . ')';
                $port=$client->sw_port - 1;
                break;
            }
          }

          $tx_start=$apname->port_table[$port]->tx_bytes;
          $rx_start=$apname->port_table[$port]->rx_bytes;
          $uptime_start=$client->uptime;
          sleep(30);
          $unifi_connection_new  = new UniFi_API\Client($user, $pass, $url, $siteid , "6.0.45");
          $loginresults = $unifi_connection_new->login();
          $aps_new_array_client = $unifi_connection_new->list_clients();

          foreach ($aps_new_array_client as $client_new) {
            if ( $ap_mode == $client_new->name || $ap_mode == $client_new->ip ) {
              $uptime_stop=$client_new->uptime;

              $unifi_connection_array_new  = new UniFi_API\Client($user, $pass, $url, $siteid , "6.0.45");
              $loginresults = $unifi_connection_array_new->login();
              $aps_new_array = $unifi_connection_array_new->list_devices();

              foreach ($aps_new_array as $apname_new) {
                 if ($client->sw_mac === $apname_new->mac) {
                    $tx_stop=$apname_new->port_table[$port]->tx_bytes;
                    $rx_stop=$apname_new->port_table[$port]->rx_bytes;
                break;
                 }
              }
              break;
            }
          }

        }
        $seconds=$uptime_stop - $uptime_start;
        $seconds=60;

        if ( $mode == 'client_transfer' ) {
          $rx_perf=number_format(($rx_stop - $rx_start) * 8 / 1000 / $seconds,3,".","");
          $tx_perf=number_format(($tx_stop - $tx_start) * 8 / 1000 / $seconds,3,".","");
          $perf_format="Kbit";
          $rx=$rx_perf;
          $tx=$tx_perf;
          $rx_format="Kbit/s"; 
          $tx_format="Kbit/s";

          if ( $rx_perf > 1000 ) {
             $rx=number_format($rx_perf/1000,1,".",""); 
             $rx_format="Mbit/s"; }

          if ( $tx_perf > 1000 ) {
             $tx=number_format($tx_perf/1000,1,".",""); 
             $tx_format="Mbit/s"; }
        }

        if ( $mode == 'client_transfer+' ) {
          $rx_perf=number_format(($rx_stop - $rx_start) / 1024 / $seconds,3,".","");
          $tx_perf=number_format(($tx_stop - $tx_start) / 1024 / $seconds,3,".","");
          $perf_format="KB";
          $rx=$rx_perf;
          $tx=$tx_perf;
          $rx_format="KB/s"; 
          $tx_format="KB/s"; 

          if ( $rx_perf > 1024 ) {
             $rx=number_format($rx_perf/1024,3,".","");
             $rx_format="MB/s"; }


          if ( $tx_perf > 1024 ) {
             $tx=number_format($tx_perf/1024,3,".","");
             $tx_format="MB/s"; }
  
        }
        if ( is_numeric($rx) === false ) {
          echo 'UNKNOWN - no connection | rx=0;;;; tx=0;;;;' . PHP_EOL; 
          return_status(3); }
        if ($rx < 0 )
           { $rx = 0; 
             $rx_perf = 0; }
        if ($tx < 0 )
           { $tx = 0; 
             $tx_perf = 0; }
        echo 'OK - rx: ' . $rx . ' ' . $rx_format . ' - tx: ' .  $tx . ' ' . $tx_format . ' ' . $connection . ' | rx=' . $rx_perf . $perf_format . ';;;; tx=' . $tx_perf . $perf_format . ';;;;' . PHP_EOL;
        return_status(0);
      }
    }   
    switch ($result) {
      case 1:
        echo 'WARNING - no client found | rx=0;;;; tx=0;;;;| rx=0;;;; tx=0;;;;' . PHP_EOL; return_status(1);
        break;
      case 2:
        echo 'CRITICAL - no client found | rx=0;;;; tx=0;;;;' . PHP_EOL; return_status(2);
        break;
      default:
        echo 'OK - no client found | rx=0;;;; tx=0;;;;' . PHP_EOL; return_status(0);
    }
    break;

  case 'ap':
  case 'switch':
  case 'ap_name':
  case 'switch_name':
  case 'ap_unifi':
  case 'switch_unifi':
  case 'ap_unifi_name':
  case 'switch_unifi_name':
    $online=0;
    $offline=0;
    $ap_count=0;
    if ( $mode == 'ap' || $mode == 'ap_name' || $mode == 'ap_unifi' || $mode == 'ap_unifi_name' ) {
      $type='uap';
      $type_name='AP';
    } 
    else {
      $type='usw';
      $type_name='Switch';
    }      


    if (!is_array($clients_name)) $clients_name=[];
    if ( $mode == 'ap' || $mode == 'switch' || $mode == 'ap_name' || $mode == 'switch_name' ) {
      $sum=0;
      foreach ($aps_array as $ap) {
        $no_ap = explode(',',@$ap_mode);
        if ( $ap_mode == '' or ( array_search($ap->name,$no_ap) === false and array_search($ap->hostname,$no_ap) === false and array_search($ap->ip,$no_ap) === false )) {
          $sum = $sum + 1;
        
          if ($ap->type == $type ) {
            if ( $ap->state  == 1 ) {
               $online = $online + 1; }
            else { 
               $clients='';
               $offline = $offline + 1; 
               if ( @$ap->name != '' ) {
                 $clients_name[] = @$ap->name; }
               elseif ( @$ap->hostname != '' ) {
                 $clients_name[] = @$ap->hostname; }
               else {
                 $clients_name[] = @$ap->ip; }
            }
          }
        }
      } 
      if ( $offline > 0 ) {
        sort($clients_name);
        $sort=0;
        foreach($clients_name as $client) {
          $sort=$sort+1;
          if ( $sort == 1 )
            { $clients = ' (' . $client; }
          else
            { $clients = $clients  . ', ' . $client; }
        }
        $offline_ap=$clients . ')'; 
      }
      foreach($site_array as $site_list) {
        if ( $siteid == $site_list->name ) {
           $siteid=$type_name . ' ['.$site_list->desc . ']: ';
        }
      }
   }
   else {
      foreach ($site_array as $site_list) {
        $siteid=$site_list->name;
        $unifi_connection_site = new UniFi_API\Client($user, $pass, $url, $siteid , "");
        $loginresults_login = $unifi_connection_site->login();
        $aps_array = $unifi_connection_site->list_devices();
        foreach ($aps_array as $ap) {
          $no_ap = explode(',',$ap_mode);
          if ( $ap_mode == '' or ( array_search($ap->name,$no_ap) === false and array_search($ap->hostname,$no_ap) === false and array_search($ap->ip,$no_ap) === false )) {
            if ($ap->type == $type ) {
              if ( $ap->state  == 1 ) {
                $online = $online + 1; }
              else { 
                $clients='';
                $offline = $offline + 1; 
                if ( @$ap->name != '' ) {
                  $clients_name[] = @$ap->name; }
                elseif ( @$ap->hostname != '' ) {
                  $clients_name[] = @$ap->hostname; }
                else {
                  $clients_name[] = @$ap->ip; }
              
               }
             }
          }
        }
      }
      if ( $offline > 0 ) {
        sort($clients_name);
        $sort=0;
        foreach($clients_name as $client) {
          $sort=$sort+1;
          if ( $sort == 1 )
            { $clients = ' (' . $client; }
          else
            { $clients = $clients  . ', ' . $client; }
        }

        $offline_ap=$clients . ')'; }

      $siteid=$type_name.': ';
    }

    if ( $crit < $offline ) {
           $status = 'CRITICAL';
           $status_code = 2; }
    elseif ( $warn < $offline ) {
           $status = 'WARNING';
           $status_code = 1; }
    else { $status = 'OK';
           $status_code = 0; }
    $ap_count = $online + $offline; 
    if ( $mode == 'ap_name' || $mode == 'ap_unifi_name' ) {
      echo $status . ' - ' . $siteid . $ap_count . ' (Online: ' . $online . ', Offline: ' . $offline . $offline_ap . ') | AP=' . $ap_count . ' Online=' . $online . ' Offline=' . $offline . ';' . $warn . ';' . $crit . ';;' . PHP_EOL;
    }
    else {
      echo $status . ' - ' . $siteid . $ap_count . ' (Online: ' . $online . ', Offline: ' . $offline . ') | AP=' . $ap_count . ' Online=' . $online . ' Offline=' . $offline . ';' . $warn . ';' . $crit . ';;' . PHP_EOL;
    }
    return_status($status_code);
    break;
  
  case 'client_uplink':
    foreach ($aps_array_clients as $client) {
      if ( $ap_mode == $client->name || $ap_mode == $client->ip ) {
        if ( $client->is_wired === false ) {
          foreach ($aps_array as $apname) {
            if ($client->ap_mac === $apname->mac) {
                $uplink=$apname->name;
                break;
            }
          }
         if ( $client->radio == 'na' ) {
           $wifi='5GHz'; }
         else { 
           $wifi='2GHz'; } 
           
         $status='OK';
         echo $status . ' - Uplink: ' . @$uplink . ' (' . $client->essid . '/ ' . $wifi . ') - signal: ' . @$client->signal . ' dBm  tx: ' . number_format(@$client->tx_rate/1000,1,'.','') . ' Mbps  rx: ' . number_format(@$client->rx_rate/1000,1,'.','') . ' Mbps | tx=' . number_format(@$client->tx_rate/1000,1,'.','') . 'Mbps;' . $warn . ';' . $crit . ';; rx=' . number_format(@$client->rx_rate/1000,1,'.','') . 'Mbps;' . $warn . ';' . $crit . ';; Signal=' . abs(@$client->signal) .'dBm' .  PHP_EOL;
         return_status(0); }
      }
    }
    switch ($result) {
      case 0:
        echo 'OK - no client found | tx=0Mbps;'  . $warn . ';' . $crit . ';; rx=0Mbps;' . $warn . ';' . $crit . ';; Signal=0dBm' . PHP_EOL; return_status(0);
        break;
      case 1:
        echo 'WARNING - no client found | tx=0Mbps;'  . $warn . ';' . $crit . ';; rx=0Mbps;' . $warn . ';' . $crit . ';; Signal=0dBm' . PHP_EOL; return_status(1);
        break;
      case 2:
        echo 'CRITICAL - no client found | tx=0Mbps;'  . $warn . ';' . $crit . ';; rx=0Mbps;' . $warn . ';' . $crit . ';; Signal=0dBm' . PHP_EOL; return_status(2);
    }

    break;

  case 'clients_wifi_console':
    echo '-----------------+------------------------------+------------------------------+----------------------------------------+-----------------------------------------------------------------------------------' . PHP_EOL;
    echo 'Mac              |Hostname                      |Alias (Device Name)           |Mac                Accesspoint          |ssid             wifi       signal        tx_rate             rx_rate' . PHP_EOL;
    echo '-----------------+------------------------------+------------------------------+----------------------------------------+-----------------------------------------------------------------------------------' . PHP_EOL;
    foreach ($aps_array_clients as $ap) {
      if ( $ap->is_wired === false ) {	
        foreach ($aps_array as $apname) {
          if ($ap->ap_mac === $apname->mac) {
            if ( @$ap_mode === @$apname->name || @$ap_mode === "" ) {
            echo @$ap->mac . '|' . str_pad(@$ap->hostname, 30, ' ') . '|' . str_pad(@$ap->name,30,' ') . '|' .  str_pad(@$apname->mac . ' ('. @$apname->name. ')',40,' ') . '|' . str_pad('ssid='.@$ap->essid ,17," ") . str_pad('wifi='.@$ap->radio ,10," ") . str_pad(' signal=' . @$ap->signal . 'dBm',15," ") .  str_pad('tx_rate=' . sprintf("%'. 5.1f",number_format(@$ap->tx_rate/1000)) . ' Mbps',18,' ')  . '  rx_rate=' . sprintf("%'. 5.1f",@$ap->rx_rate/1000) . " Mbps" . PHP_EOL; 
           }
          }
         }
       }
    }
    return_status(0);
    break;

  case 'clients_console':
    echo '-----------------+------------------------------+------------------------------+----------------------------------------' . PHP_EOL;
    echo 'Mac              |Hostname                      |Alias (Device Name)           |IP/Network' . PHP_EOL;
    echo '-----------------+------------------------------+------------------------------+----------------------------------------' . PHP_EOL;
    foreach ($aps_array_clients as $ap) {
       echo @$ap->mac . '|' . str_pad(@$ap->hostname, 30, ' ') . '|' . str_pad(@$ap->name,30,' ') . '|' . str_pad('ip='.@$ap->ip ,20," "). ' ' . str_pad('network='.@$ap->network ,25," ") . ' ' .  PHP_EOL; 
   }
   return_status(0);
   break;

  case 'devices_console':
    echo '-----------------+------------------------------+------------------------------+----------------------------------------' . PHP_EOL;
    echo 'Mac              |Model                         |Alias (Device Name)           |IP' . PHP_EOL;
    echo '-----------------+------------------------------+------------------------------+----------------------------------------' . PHP_EOL;
    foreach ($aps_array as $ap) {
       $device = $unifi_connection->list_device_name_mappings()->{$ap->model};
       $model=$device->display;
       echo @$ap->mac . '|' . str_pad(@$model, 30, ' ') . '|' . str_pad(@$ap->name,30,' ') . '|' . str_pad('ip='.@$ap->ip ,20," ").  ' ' .  PHP_EOL; 
   }
   return_status(0);

  case 'site_console':
    echo '---------+------------------------------------' .PHP_EOL;
    echo 'Site ID  | Site Name' . PHP_EOL;
    echo '---------+------------------------------------' .PHP_EOL;
    foreach ($site_array as $site_list) {
      echo  str_pad($site_list->name,8,' ') . ' | ' . $site_list->desc . PHP_EOL;
    }
    return_status(0); 
    break;

  case 'controller':
    foreach ($server as $controller) {
       if ( @$controller->version !== "" ) { 
         if ( $controller->update_available === false )
           { 
             echo 'OK - Unifi-Version: ' . $controller->version . PHP_EOL; 
             return_status(0); }
         else {   
           switch ($result) {
             case 1:
               echo 'WARNING - Unifi update available' . PHP_EOL; return_status(1);
               break;
             case 2:
               echo 'CRITICAL - Unifi update available' . PHP_EOL; return_status(2);
               break;
             default:
               echo 'OK - Unifi update available' . PHP_EOL; return_status(0);
           }
         }
       }
       else
         { echo 'UNKNOWN - Unifi-Version';
         return_status(3); }
    }
    break;

    case 'mem':
       foreach ($aps_array as $ap) {
       if ($ap->name === $ap_mode || $ap->ip === $ap_mode || $ap->mac === $ap_mode ) {
         if ( empty(@$ap->sys_stats->mem_total) === false ) {
           $total=$ap->sys_stats->mem_total;
           $perc=$ap->sys_stats->mem_total/100;
           $used_perc=$ap->{"system-stats"}->mem;
           $used_cached=$ap->sys_stats->mem_used;
           $free=$total-$used_cached;
           $used=number_format($used_perc*$perc,0,'','');
           if ($used_perc == '' ) 
             { $used=$ap->sys_stats->mem_used; }
           else
             { $caches=$used_cached-$used; }
           $total=number_format($total/1024/1024,3,'.','');
           $used=number_format($used/1024/1024,3,'.','');
           $free=number_format($free/1024/1024,3,'.','');
           $caches=number_format($caches/1024/1024,3,'.','');


           $warn = (int)$warn;
           $crit = (int)$crit;
           if ( $used >= ($total/100 * $crit) )
             { $status = 'CRITICAL' ;
               $status_code = 2; }
           elseif  ( $used >= ($total/100 * $warn) )
             { $status = 'WARNING' ;
               $status_code = 1; }
           else 
             { $status = 'OK' ;
               $status_code = 0; }
           $crit = number_format($total / 100  * $crit,3);
           $warn = number_format($total / 100 * $warn,3);

           echo $status . ' - ' . $used . ' MB (' . $total . ' MB) | Total=' . $total . 'MB;' . $warn. ';' . $crit . ';0;' . $total. ' Used=' . $used . 'MB;;;; Free=' . $free . 'MB;;;; Caches=' . $caches. 'MB;;;;' . PHP_EOL;
           return_status($status_code); 
           }
           else 
             { echo 'UNKNOWN - no memory | Total=0MB;' . $warn. ';' . $crit . ';0;0 Used=0;;;; Free=0;;;; Caches=0;;;;' . PHP_EOL;
               return_status(3); }
           }
      }
      echo 'UNKNOWN - no accesspoint/switch found!' . PHP_EOL;
      return_status(3);
      break;

    case 'mem%':
       foreach ($aps_array as $ap) {
       if ($ap->name === $ap_mode || $ap->ip === $ap_mode || $ap->mac === $ap_mode ) {
         if ( is_numeric(@$ap->sys_stats->mem_total) || empty($ap->{"system-stats"}->mem) === false  ) {
           $total=@$ap->sys_stats->mem_total;
           $perc=@$ap->sys_stats->mem_total/100;
           $used_perc=number_format($ap->{"system-stats"}->mem,3,'.','');

           if ( $total == "" ) { $total=1; }

           if ( $used_perc == "" ) 
             {            
              $used=$ap->sys_stats->mem_used;
              $used_perc=$used/$total*100;
              $free=$total-$used;
              $caches=0;
             }
           else
             {
              $used_cached=@$ap->sys_stats->mem_used;
              $used=number_format($used_perc*$perc,0,'','');
              $free=$total-$used_cached;
              $caches=$used_cached-$used;
              }

           $used_test=$used/$total*100;
           $free_perc=$free/$total*100;
           $caches_perc=$caches/$total*100;

           $used_test=number_format($used_test,3,'.','');
           $free_perc=number_format($free_perc,3,'.','');
           $caches_perc=number_format($caches_perc,3,'.','');
           $used=number_format($used/1024/1024,3,'.','');


           $warn = (int)$warn;
           $crit = (int)$crit;
           if ( $used_perc >= $crit )
             { $status = 'CRITICAL' ;
               $status_code = 2; }
           elseif  ( $used_perc >= $warn )
             { $status = 'WARNING' ;
               $status_code = 1; }
           else 
              { $status = 'OK' ;
               $status_code = 0; }
           echo $status . ' - ' . $used_perc . '% (' . $used . ' MB) | Total=100%;' . $warn . ';' . $crit . ';0;100 Used=' . $used_perc . '%;;;; Free=' . $free_perc . '%;;;; Caches=' . $caches_perc . '%;;;;' . PHP_EOL;
           return_status($status_code);
           }
           else 
             { echo 'UNKNOWN - no memory | Total=0%;' . $warn. ';' . $crit . ';0;0 Used=0%;;;; Free=0%;;;; Caches=0%;;;;'. PHP_EOL;
               return_status(3); }
         }
      }
      echo 'UNKNOWN - no accesspoint/switch found!' . PHP_EOL;
      return_status(3);
      break;

 case 'temperature':
       foreach ($aps_array as $ap) {
       if ($ap->name === $ap_mode || $ap->ip === $ap_mode || $ap->mac === $ap_mode ) {
         if ( is_numeric(@$ap->general_temperature) ) {
           $warn = (int)$warn;
           $crit = (int)$crit;
           if ( @$ap->general_temperature >= $crit )
             { $status = 'CRITICAL' ;
               $status_code = 2; }
           elseif  ( @$ap->general_temperature >= $warn )
             { $status = 'WARNING' ;
               $status_code = 1; }
           else
             { $status = 'OK' ;
             $status_code = 0; }
           echo $status . ' - Temperature: ' . $ap->general_temperature . ' °C | temperature=' . $ap->general_temperature . ';' . $warn . ';' .$crit . ';;' .  PHP_EOL;
           return_status($status_code);
           }
         else
           { echo 'UNKNOWN - no temperature | temperature=0;' . $warn . ';' .$crit . ';;'  . PHP_EOL;
             return_status(3); }
         }
       }
       echo 'UNKNOWN - no accesspoint/switch found!' . PHP_EOL;
       return_status(3);
       break;

 case 'udm_temperature':
       foreach ($aps_array as $ap) {
         if ($ap->type === 'udm' && ( $ap->name === $ap_mode || $ap->ip === $ap_mode || $ap->mac === $ap_mode )) {
           $warn=preg_split('/,/' ,$warn);
           $crit=preg_split('/,/',$crit);
           $i=0;
           $state=0;
           while ($i < 3):
             $temperatur_name[$i]=@$ap->temperatures[$i]->name;
             $temperatur[$i]=@$ap->temperatures[$i]->value;
             if ( is_numeric($temperatur[$i]) ) {
               if ( $temperatur[$i] >= $crit[$i] )
                 { $status = 'CRITICAL' ;
                   $status_code = 2; 
                   $state=2; }
               elseif  ( $temperatur[$i] >= $warn[$i] && $state < 2 )
                 { $status = 'WARNING' ;
                   $status_code = 1; 
                   $state=1; } 
               elseif ( $state == 0 && $state == 0 ) 
                 { $status = 'OK' ;
                   $status_code = 0; }
             }
           
             $i++;
           endwhile;

           if ( empty($warn[1]) && empty($crit[1]) ) 
             { $status = 'OK' ;
               $status_code = 0; }
              
           echo $status . ' - Temperature (' . $temperatur_name[0] . ': '. $temperatur[0] .' °C ';  
           echo ', ' .  $temperatur_name[1] . ': '. $temperatur[1] .' °C ';  
           echo ', ' .  $temperatur_name[2] . ': '. $temperatur[2] .' °C) ';  
           echo '| ' . $temperatur_name[0] . '=' . $temperatur[0] . ';' . $warn[0] . ';' . $crit[0] . ';;' ;
           echo ' ' . $temperatur_name[1] . '=' . $temperatur[1] . ';' . $warn[1] . ';' . $crit[1] . ';;' ;
           echo ' ' . $temperatur_name[2] . '=' . $temperatur[2] . ';' . $warn[2] . ';' . $crit[2] . ';;' .  PHP_EOL;
           return_status($status_code);
         }
       }
       echo 'UNKNOWN - no accesspoint/switch found!' . PHP_EOL;
       return_status(3);
       break;

    case 'cpu':
       foreach ($aps_array as $ap) {
       if ( $ap->name === $ap_mode|| $ap->ip === $ap_mode || $ap->mac === $ap_mode  ) {
         $warn = (int)$warn;
         $crit = (int)$crit;
         if ( is_numeric(@$ap->sys_stats->loadavg_1) ) {
           if ( intval($ap->{"system-stats"}->cpu) >= $crit )
             { $status = 'CRITICAL' ;
               $status_code = 2; }
           elseif  ( $ap->{"system-stats"}->cpu >= $warn )
             { $status = 'WARNING' ;
               $status_code = 1; }
           else 
             { $status = 'OK' ;
             $status_code = 0; }

         echo $status . ' - ' . $ap->{"system-stats"}->cpu . '% (load average: ' .  $ap->sys_stats->loadavg_1 . ', ' .  $ap->sys_stats->loadavg_5 . ', ' .  $ap->sys_stats->loadavg_15 .') | load=' . $ap->sys_stats->loadavg_1 . ';;;; load5=' . $ap->sys_stats->loadavg_5 . ';;;; load15=' . $ap->sys_stats->loadavg_15 . ';;;; ' . PHP_EOL;
         return_status($status_code);
         }
        }
       }
       echo 'UNKNOWN - no accesspoint/switch found!' . PHP_EOL;
       return_status(3);
       break;

    case 'load':
       foreach ($aps_array as $ap) {

         if ( $ap->name === $ap_mode || $ap->ip === $ap_mode || $ap->mac === $ap_mode ) {
         $warn=preg_split('/,/' ,$warn);
         $crit=preg_split('/,/',$crit);
             if ( $ap->sys_stats->loadavg_1 >= $crit[0] or $ap->sys_stats->loadavg_5 >= $crit[1] or $ap->sys_stats->loadavg_15 >= $crit[2] ) {
                 $status = 'CRITICAL';
                 $status_code=2; }
             elseif ( $ap->sys_stats->loadavg_1 >= $warn[0] or $ap->sys_stats->loadavg_5 >= $warn[1] or $ap->sys_stats->loadavg_15 >= $warn[2] ) {
                 $status = 'WARNING';
                 $status_code=1; }
             else {
                 $status = 'OK';
                 $status_code = 0; }
           echo $status . ' - load average: ' .  $ap->sys_stats->loadavg_1 . ', ' .  $ap->sys_stats->loadavg_5 . ', ' .  $ap->sys_stats->loadavg_15 .' | load=' . $ap->sys_stats->loadavg_1 . ';' . $warn[0] .';' . $crit[0] . ';; load5=' . $ap->sys_stats->loadavg_5 . ';' . $warn[1] . ';' . $crit[1] .';;  load15=' . $ap->sys_stats->loadavg_15 . ';' . $warn[2] .';' . $crit[2] .';;  ' . PHP_EOL;
           return_status($status_code);
         }
       }
       echo 'UNKNOWN - no accesspoint/switch found!' . PHP_EOL;
       return_status(3);
       break;

    case 'cpu%':
       foreach ($aps_array as $ap) {
       if ( $ap->name === $ap_mode || $ap->ip === $ap_mode || $ap->mac === $ap_mode ) {
         $warn = (int)$warn;
         $crit = (int)$crit;
         if ( strval($ap->{"system-stats"}->cpu) >= $crit )
           { $status = 'CRITICAL' ;
              $status_code = 2; }
         elseif  ( strval($ap->{"system-stats"}->cpu) >= $warn )
           { $status = 'WARNING' ;
             $status_code = 1; }
         else
           { $status = 'OK' ;
           $status_code = 0; }

         echo $status . ' - ' . ltrim($ap->{"system-stats"}->cpu) . '% | cpu=' . ltrim($ap->{"system-stats"}->cpu) . '%;' . $warn . ';' . $crit . ';0;100' . PHP_EOL;
         return_status($status_code);
         }
       }
       echo 'UNKNOWN - no accesspoint/switch found!' . PHP_EOL;
       return_status(3);
       break;

    case 'uplink':     
    foreach ($aps_array as $ap) {
    if ($ap->type === 'uap' && ( $ap->name === $ap_mode || $ap->ip === $ap_mode || $ap->mac === $ap_mode) ) {
      $uplink_type = @$ap->uplink->type;
      if ( $uplink_type == 'wire' ) {
         $uplink_mac = @$ap->uplink->uplink_mac; 
         if ( @$ap->uplink->speed < $crit )
            { $status = 'CRITICAL'; 
              $status_code = 2;}
         elseif ( @$ap->uplink->speed < $warn )
            { $status = 'WARNING'; 
              $status_code = 1;}
         else
            { $status = 'OK'; 
              $status_code = 0;}
        } 
      else { 
         $uplink_mac = @$ap->uplink_ap_mac; 
         if ( @$ap->uplink->tx_rate/1000 <= $crit || @$ap->uplink->rx_rate/1000 <= $crit )
            { $status = 'CRITICAL'; 
              $status_code = 2;}
         elseif ( @$ap->uplink->tx_rate/1000 <= $warn ||  @$ap->uplink->rx_rate/1000 <= $warn )
            { $status = 'WARNING'; 
              $status_code = 1;}
         else
            { $status = 'OK'; 
              $status_code = 0;}
           }
   
      foreach ($aps_array_ap as $uplink_ap) {
              if ( @$uplink_mac === @$uplink_ap->mac )
                {
                 $uplink = @$uplink_ap->name;
                }
  
           }
          
       if ( $uplink_type == 'wireless' ) {
        echo $status . ' - Uplink: ' . @$uplink . ' (' . $uplink_type . ') - signal: ' . @$ap->uplink->signal . ' dBm  tx: ' . number_format(@$ap->uplink->tx_rate/1000,1,'.','') . ' Mbps  rx: ' . number_format(@$ap->uplink->rx_rate/1000,1,'.','') . ' Mbps | tx=' . number_format(@$ap->uplink->tx_rate/1000,1,'.','') . 'Mbps;' . $warn . ';' . $crit . ';; rx=' . number_format(@$ap->uplink->rx_rate/1000,1,'.','') . 'Mbps;' . $warn . ';' . $crit . ';; Signal=' . abs(@$ap->uplink->signal) . 'dBm' .  PHP_EOL;
         }
       else { echo $status . ' - Uplink: ' . @$uplink . ' (' . $uplink_type . ') - speed:' . @$ap->uplink->speed . ' Mbps | speed=' . @$ap->uplink->speed . ';' . $warn . ';' . $crit . ';;' . PHP_EOL;
       }
        return_status($status_code); }
      }
       echo 'UNKNOWN - no accesspoint/switch found!' . PHP_EOL;
       return_status(3);
      break;


    case 'clients_wifi':
    case 'clients_wifi_unifi':
    $W6=0;
    $W5=0;
    $W4=0;
    $W3=0;
    $wifi=0;
    if ( $mode == 'clients_wifi' ) {
      foreach($site_array as $site_list) {
        if ( $siteid == $site_list->name ) {
          $sitename='wifi ['.$site_list->desc . ']: ';
          $site_id=$site_list->_id;
          $unifi_connection_site_new = new UniFi_API\Client($user, $pass, $url, $siteid , "");
          $loginresults_login = $unifi_connection_site_new->login();
          $aps_array_clients = $unifi_connection_site_new->list_clients();
          foreach ($aps_array_clients as $ap) {
            if ( $site_id == $ap->site_id ) {
              if (  @$ap->is_wired === false )  {
                $wifi=$wifi + 1; 
                switch (@$ap->radio_proto) {
                  case 'ax':
                    $W6=$W6+1;
                    break;
                  case 'ac':
                    $W5=$W5+1;
                    break;
                  case 'ng':
                    $W4=$W4+1;
                    break;
                  default:
                    $W3=$W3+1;
                }
              } 
            }
          }
        }
      }
    }
    elseif ( $mode == 'clients_wifi_unifi' ) {
      $sitename='wifi: ';
      foreach ($aps_array_clients as $ap) {
        if (  @$ap->is_wired === false )  {
          $wifi=$wifi + 1; 
          switch (@$ap->radio_proto) {
            case 'ax':
              $W6=$W6+1;
              break;
            case 'ac':
              $W5=$W5+1;
              break;
            case 'ng':
              $W4=$W4+1;
              break;
            default:
              $W3=$W3+1;
          }
        }
      }
    }

    echo 'OK - ' . $sitename . $wifi .' ('; 
    echo 'wifi6='. $W6 .', wifi5='. $W5 .', wifi4='. $W4 . ') | wifi=' . $wifi . ';;;; wifi6=' . $W6 . ' wifi5=' . $W5 . ' wifi4=' . $W4 .  PHP_EOL;
    return_status(0);
    break;


    case 'clients_all':
    case 'clients_unifi':
      $total=0;
      $wired=0;
      $wifi=0;
      if ( $mode == 'clients_all' ) {
        foreach($site_array as $site_list) {
          if ( $siteid == $site_list->name ) {
            $site_id=$site_list->_id;
            $sitename='clients ['.$site_list->desc . ']: ';
            $unifi_connection_site_new = new UniFi_API\Client($user, $pass, $url, $siteid , "");
            $loginresults_login = $unifi_connection_site_new->login();
            $aps_array_clients = $unifi_connection_site_new->list_clients();
            foreach ($aps_array_clients as $ap) {
              if ( $site_id == $ap->site_id ) {
                $total=$total+1;
                if (  @$ap->is_wired === true )  {
                  $wired=$wired + 1; }
                else {
                  $wifi=$wifi + 1; }
              }
            }
          }
        }
      }

      elseif ( $mode == 'clients_unifi' ) {
        foreach ($aps_array_clients as $ap) {
          $total=$total+1;
          if (  @$ap->is_wired === true )  {
            $wired=$wired + 1; }
          else {
            $wifi=$wifi + 1; }
        }
      $sitename='clients: ';
      }
    
    echo 'OK - ' . $sitename . $total . ' (wired: ' . $wired . ', wifi: ' . $wifi . ') | clients=' . $total . ';;;; wired=' . $wired . ';;;; wifi=' . $wifi . ';;;;' .  PHP_EOL; 
    return_status(0);
    break;


    case ( $mode === 'clients_count' || $mode === 'clients_name' || $mode === 'clients_name_guest' || $mode === 'clients_count_guest'):
      $TG=0;
      $FG=0;
      $TG_client=0;
      $FG_client=0;
      $TG_guest=0;
      $FG_guest=0;
      $sum=0;
      $user=0;
      $guest=0;
      $state=@$unifi_connection->list_device_states();
      $status_code=0;

      if ( $ap_mode != '' ) {
        foreach ($aps_array as $ap) {
          if ( $ap->name === $ap_mode || $ap->ip === $ap_mode || $ap->mac === $ap_mode) { 
            $ap_state = @$ap->state; 
            switch ($status_ap[$ap_state]) {
              case 'WARNING':
                echo 'WARNING - ' . $ap->name . ' is ' . $state[$ap_state] . PHP_EOL;
                return_status(1);
                break;
              case 'CRITICAL':
                echo 'CRITICAL - ' . $ap->name . ' is ' . $state[$ap_state] . PHP_EOL;
                return_status(2);
                break;
              case 'OK'; 
                $status='OK';
                $status_code=0;
                break;
              default: 
                echo 'UNKNOWN - ' .  $ap->name . ' state is unknown ' . PHP_EOL; 
                return_status(3);
            }  
       
            $mac = @$ap->mac; 
            
            if (!is_array($clients_name)) $clients_name=[];
            if ( $mode === 'clients_name' || $mode == 'clients_name_guest' ) {
              
              foreach ($aps_array_clients as $ap_clients) {
                if ( @$mac === @$ap_clients->ap_mac ) { 

                   if ( @$ap_clients->name != '' ) {
                     $client=@$ap_clients->name; }
                   elseif ( @$ap_clients->hostname != '' ) {
                     $client=@$ap_clients->hostname; }
                   else {
                     $client=@$ap_clients->ip; }

                   if ( $ap_clients->is_guest === true )
                    {
                      $client = $client . '(G)'; 
                      $guest=$guest+1; }
                   else
                    { $user=$user+1;}

                   if ( $ap_clients->radio == 'na' ) {
                     $clients_name[] = $client . '(5)'; }
                   elseif ( $ap_clients->radio == 'ng' ) {
                     $clients_name[] = $client . '(2)'; }

                }
              }
              sort($clients_name);
              foreach($clients_name as $client) {
                   $sum=$sum+1;
                   if ( $sum == 1 ) 
                     { $clients = ' - ' . $client; }
                   else
                     { $clients = $clients  . ', ' . $client; }
              }
              
            }
          }


          if ($ap->type === 'uap' && ($ap->name === $ap_mode || $ap->ip === $ap_mode || $ap->mac === $ap_mode) ) { 
          $sum=0; 
            foreach ($aps_array_clients as $ap_clients) {
              if ( $mac === $ap_clients->ap_mac ) {  
                $sum = $sum + 1;
                if ( $ap_clients->radio == 'na' and $ap_clients->is_guest === true )
                  { $FG = $FG + 1;
                    $FG_guest = $FG_guest + 1; } 
                elseif ( $ap_clients->radio == 'na' and $ap_clients->is_guest === false )
                  { $FG = $FG + 1;
                    $FG_client = $FG_client + 1; }
                elseif ( $ap_clients->radio == 'ng' and $ap_clients->is_guest === true ) 
                  { $TG = $TG + 1; 
                    $TG_guest = $TG_guest + 1; } 
                elseif ( $ap_clients->radio == 'ng' and $ap_clients->is_guest === false ) 
                  { $TG = $TG + 1; 
                    $TG_client = $TG_client + 1; } 
              }
            }
          }
        }
      }
    else {
      $status = 'OK';
      foreach ($aps_array_clients as $ap_clients) {
        if ( @$ap_clients->is_wired === false ) {
          $sum = $sum + 1;
          if ( $mode === 'clients_name' || $mode == 'clients_name_guest' ) {
            if ($sum == 1 ) {
              $clients = ' - '; }
            else {
              $clients = $clients . ', '; }
            if ( @$ap_clients->name != '' ) {
              $clients = $clients . @$ap_clients->name; }
            elseif ( @$ap_clients->hostname != '' ) {
              $clients = $clients . @$ap_clients->hostname; }
            else {
              $clients = $clients . @$ap_clients->mac; }
            if ( $ap_clients->radio == 'na' ) {
               $clients = $clients . '(5)'; }
            elseif ( $ap_clients->radio == 'ng' ) {
               $clients = $clients . '(2)'; }
            if ( $ap_clients->is_guest === true &&  $mode == 'clients_name_guest' )
              { $clients = $clients . '(G)';} 
          }

          if ( $ap_clients->is_guest === true )
            { $guest=$guest+1; }
          else
            { $user=$user+1;}

          if ( $ap_clients->radio == 'na' and $ap_clients->is_guest === true )
            { $FG=$FG + 1; 
              $FG_guest = $FG_guest + 1; }
          elseif ( $ap_clients->radio == 'na' and $ap_clients->is_guest === false )
            { $FG = $FG + 1;
              $FG_client = $FG_client + 1; }
          elseif ( $ap_clients->radio == 'ng' and $ap_clients->is_guest === true )
            { $TG = $TG + 1; 
              $TG_guest = $TG_guest + 1; }
          elseif ( $ap_clients->radio == 'ng' and $ap_clients->is_guest === false )
            { $TG = $TG + 1;
              $TG_client = $TG_client + 1; }
        }
      }
    }
    foreach($site_array as $site_list) {
      if ( $siteid == $site_list->name ) {
         $siteid='['.$site_list->desc . ']: ';
      }
   }

    if ( $mode === 'clients_count_guest' || $mode === 'clients_name_guest' ) { 
      echo $status . ' - wifi clients ' . $siteid . $sum . '/ User:' . $user . '/ Guest:' . $guest . ' (2GHz: ' . $TG . '/U:' . $TG_client . '/G:' . $TG_guest . ', 5GHz: ' . $FG . '/U:' . $FG_client . '/G:' . $FG_guest . ')' . @$clients . ' | clients=' . $sum . ';;;; 2GHz=' . $TG . ';;;; 5GHz=' . $FG . ';;;; User=' . $user . ';;;; 2GhzU=' . $TG_client . ';;;; 5GhzU=' . $FG_client . ';;;; Guest=' . $guest . ';;;; 2GhzG=' . $TG_guest . ';;;; 5GhzG=' . $FG_guest . ';;;;' . PHP_EOL;
          }
    else {
      echo $status . ' - wifi clients ' . $siteid . $sum . ' (2GHz: ' . $TG . ', 5GHz: ' . $FG . ')' . @$clients . ' | clients=' . $sum . ';;;; 2GHz=' . $TG . ' 5GHz=' . $FG  . PHP_EOL;
    }
    return_status($status_code);
    break;

  case 'clients_count_unifi':
  case 'clients_count_guest_unifi':
  case 'clients_count_ssid':
  case 'clients_count_guest_ssid':
    $status='OK';
    $sum=0;
    $wifi_guest=0;
    $wifi_user=0;
    $FG=0;
    $FG_guest=0;
    $FG_client=0;
    $TG=0;
    $TG_guest=0; 
    $TG_client=0;
    foreach ($site_array as $site_list) {
      $siteid=$site_list->name;
      $unifi_connection_site = new UniFi_API\Client($user, $pass, $url, $siteid , "");
      $loginresults_login = $unifi_connection_site->login();
      $aps_array_clients = $unifi_connection_site->list_clients();
      foreach ($aps_array_clients as $ap_clients) {
         if ( @$ap_clients->is_wired === false && @$ap_mode === @$ap_clients->essid) {
          $client='SSID ' . @$ap_mode . ' clients';
          $sum=$sum + 1;
          if ( $ap_clients->is_guest === true )
              { $wifi_guest=$wifi_guest+1; }
            else
              { $wifi_user=$wifi_user+1;}

          if ( $ap_clients->radio == 'na' and $ap_clients->is_guest === true )
            { $FG=$FG+1;
              $FG_guest = $FG_guest + 1; }
          elseif ( $ap_clients->radio == 'na' and $ap_clients->is_guest === false )
            { $FG = $FG + 1;
              $FG_client = $FG_client + 1; }
          elseif ( $ap_clients->radio == 'ng' and $ap_clients->is_guest === true )
            { $TG = $TG + 1;
              $TG_guest = $TG_guest + 1; }
          elseif ( $ap_clients->radio == 'ng' and $ap_clients->is_guest === false )
            { $TG = $TG + 1;
              $TG_client = $TG_client + 1; }
        }
        elseif ( @$ap_clients->is_wired === false  && ( $mode === 'clients_count_guest_unifi' || $mode === 'clients_count_unifi' ) )  {
          $client='clients';
          $sum=$sum + 1;
          if ( $ap_clients->is_guest === true )
              { $wifi_guest=$wifi_guest+1; }
            else
              { $wifi_user=$wifi_user+1;}

          if ( $ap_clients->radio == 'na' and $ap_clients->is_guest === true )
            { $FG=$FG+1;
              $FG_guest = $FG_guest + 1; }
          elseif ( $ap_clients->radio == 'na' and $ap_clients->is_guest === false )
            { $FG = $FG + 1;
              $FG_client = $FG_client + 1; }
          elseif ( $ap_clients->radio == 'ng' and $ap_clients->is_guest === true )
            { $TG = $TG + 1;
              $TG_guest = $TG_guest + 1; }
          elseif ( $ap_clients->radio == 'ng' and $ap_clients->is_guest === false )
            { $TG = $TG + 1;
              $TG_client = $TG_client + 1; }
        }
      }
    }
    
    if ( @$client == '' ) {
      $client=@$ap_mode . ' clients';
    }
    if ( $mode === 'clients_count_guest_unifi' or $mode === 'clients_count_guest_ssid'  ) { 
      echo $status . ' - ' . $client .': ' . $sum . '/ User:' . $wifi_user . '/ Guest:' . $wifi_guest . ' (2GHz: ' . $TG . '/U:' . $TG_client . '/G:' . $TG_guest . ', 5GHz: ' . $FG . '/U:' . $FG_client . '/G:' . $FG_guest . ') | clients=' . $sum . ';;;; 2GHz=' . $TG . ';;;; 5GHz=' . $FG . ';;;; User=' . $wifi_user . ';;;; 2GhzU=' . $TG_client . ';;;; 5GhzU=' . $FG_client . ';;;; Guest=' . $wifi_guest . ';;;; 2GhzG=' . $TG_guest . ';;;; 5GhzG=' . $FG_guest . ';;;;' . PHP_EOL;
            }
      else {
        echo $status . ' - ' . $client . ': ' . $sum . ' (2GHz: ' . $TG . ', 5GHz: ' . $FG . ') | clients=' . $sum . ';;;; 2GHz=' . $TG . ' 5GHz=' . $FG  . PHP_EOL;
      }
      return_status($status_code);
    break;

    
  case 'experience':
    foreach ($aps_array as $ap) {
        if ($ap->type === 'uap' && ( $ap->name === $ap_mode || $ap->ip === $ap_mode || $ap->mac === $ap_mode) ) { 
          $experience = $ap->satisfaction;
          if ( $experience === -1  ) {
             echo 'OK - no clients' . PHP_EOL;
             return_status(0); }
         $warn=(int)$warn;
         $crit=(int)$crit;
         if ( $experience <= $warn )
           { $status = 'WARNING' ;
             $status_code = 1; }
         elseif  ( $experience <= $crit )
           { $status = 'CRTITICAL' ;
             $status_code = 2; }
         else
           { $status = 'OK' ;
             $status_code = 0; }
         echo $status . ' - WIFI Experience: ' . $experience . '% | Experience=' . $experience . '%;' . $warn . ';' . $crit . ';;' . PHP_EOL;
         return_status($status_code);
        }
      }
    echo 'UNKNOWN - no Accesspoint found!' . PHP_EOL;
    return_status(3);
    break;

  case 'client_experience':
    foreach ($aps_array_clients as $client) {
        if ( $ap_mode == $client->name || $ap_mode == $client->ip ) {
          $experience = $client->satisfaction;
          if ( $experience === -1  ) {
             echo 'OK - no clients' . PHP_EOL;
             return_status(0); }
         $warn=(int)$warn;
         $crit=(int)$crit;
         if ( $experience <= $warn )
           { $status = 'WARNING' ;
             $status_code = 1; }
         elseif  ( $experience <= $crit )
           { $status = 'CRTITICAL' ;
             $status_code = 2; }
         else
           { $status = 'OK' ;
             $status_code = 0; }
         echo $status . ' - WIFI Experience: ' . $experience . '% | Experience=' . $experience . '%;' . $warn . ';' . $crit . ';;' . PHP_EOL;
         return_status($status_code);
        }
      }

    
    
    case 'channels':
    foreach ($aps_array as $ap) {
        if ($ap->type === 'uap' && ( $ap->name === $ap_mode || $ap->ip === $ap_mode || $ap->mac === $ap_mode )) {
          if ( $ap->radio_table[0]->radio == "ng" ) {
            $wifi_channel = $ap->radio_table_stats[0]->channel + ( $ap->radio_table_stats[0]->extchannel * 2 );
            $channel[0]=array('2GHz',
                             $ap->radio_table[0]->channel,
                             $ap->radio_table[0]->ht,
                             $wifi_channel,
                             $ap->radio_table_stats[0]->channel,
                             $ap->radio_table_stats[0]->extchannel,);
            if ( $channel_m[$ap->radio_table_stats[1]->channel][$ap->radio_table[1]->ht] != '') {
              $wifi_channel=$channel_m[$ap->radio_table_stats[1]->channel][$ap->radio_table[1]->ht]; } 
            else { 
              $wifi_channel= $ap->radio_table_stats[1]->channel; } 
            $channel[1]=array('5GHz',
                             $ap->radio_table[1]->channel,
                             $ap->radio_table[1]->ht,
                             $wifi_channel,
                             $ap->radio_table_stats[1]->channel,
                             $ap->radio_table_stats[1]->extchannel,);
          }
          else { 
            $wifi_channel = $ap->radio_table_stats[1]->channel + ( $ap->radio_table_stats[1]->extchannel * 2 );
            $channel[0]=array('2GHz',
                             $ap->radio_table[1]->channel,
                             $ap->radio_table[1]->ht,
                             $wifi_channel,
                             $ap->radio_table_stats[1]->channel,
                             $ap->radio_table_stats[1]->extchannel,);
            if ( $channel_m[$ap->radio_table_stats[1]->channel][$ap->radio_table[1]->ht] != '' ) {
              $wifi_channel=$channel_m[$ap->radio_table_stats[0]->channel][$ap->radio_table[0]->ht];}  
            else { 
              $wifi_channel= $ap->radio_table_stats[0]->channel; } 
             
            $channel[1]=array('5GHz',
                             $ap->radio_table[0]->channel,
                             $ap->radio_table[0]->ht,
                             $wifi_channel,
                             $ap->radio_table_stats[0]->channel,
                             $ap->radio_table_stats[0]->extchannel,);
          }
          echo 'OK - ' . $channel[0][0] . ': ' . $channel[0][1] . ' (' . $channel[0][3] . '(' . $channel[0][4] . ',' . preg_replace('/\+0/','0',sprintf('%+1d',$channel[0][5])) . ')) HT' . $channel[0][2];
          echo ' - ' . $channel[1][0] . ': ' . $channel[1][1] . ' (' . $channel[1][3] . '(' . $channel[1][4] . ',' . preg_replace('/\+0/','0',sprintf('%+1d', $channel[1][5])) . ')) VHT' . $channel[1][2];
          echo '| 2GHz=' . $channel[0][3] . ' 5GHz=' . $channel[1][3] . PHP_EOL;
          return_status(0);
     }
    }
    echo 'UNKNOWN - no accesspoint found!' . PHP_EOL;
    return_status(3);
    break;

  case 'transfer':
  case 'transfer+':
  case 'switch_transfer':
  case 'switch_transfer+':
    $switch_name="";
    $port=$switch_port - 1;
    foreach ($aps_array as $ap) {
        if ( $ap->name === $ap_mode || $ap->ip === $ap_mode || $ap->mac === $ap_mode ) {
          if ( $ap->type === 'uap' ) { 
            $rx_start=$ap->{"rx_bytes"};
            $tx_start=$ap->{"tx_bytes"};
          }
          elseif ( $ap->type === 'usw') {
            $rx_start=$ap->{"port_table"}[$port]->{"rx_bytes"};
            $tx_start=$ap->{"port_table"}[$port]->{"tx_bytes"};
            $switch_port_up=$ap->{"port_table"}[$port]->up;
            if ( $mode == 'switch_transfer' || $mode == 'switch_transfer+' )
              { 
               if ( $switch_port_up === false ) 
                 {
                  echo 'CRITICAL - Port ' . $switch_port . ': down ' . PHP_EOL;
                  return_status(2);
                 }
              }
              if ( $ap->{"port_table"}[$port]->is_uplink === true ) 
                {
                 $uplink_mac=$ap->{"port_table"}[$port]->mac_table[1]->mac;
                 foreach ($aps_array_ap as $uplink_ap) {
                    if ( @$uplink_mac === @$uplink_ap->mac )
                      {
                       $uplink = @$uplink_ap->name;
                      }
                 }
                 $switch_port_name=' Port ' . $switch_port . ': Uplink ' . $uplink .  ' (' . $ap->{"port_table"}[$port]->speed .  ' Mbit/s) -';
                }
              else 
                {
                 $switch_port_name=' Port ' . $switch_port . ': ' . $ap->{"port_table"}[$port]->name . ' (' . $ap->{"port_table"}[$port]->speed .  ' Mbit/s) -';
                }
            
            if ( @$ap->{"port_table"}[$port]->speed == '' ) {
              echo 'UNKNOWN - Port ' . $switch_port .  PHP_EOL;
              return_status(3);
            }
          }
          $time_start=$ap->stat->sw->time;
          $uptime_start=$ap->uptime;
          sleep(30);
          $unifi_connection_new  = new UniFi_API\Client($user, $pass, $url, $siteid , "6.0.45");
          $loginresults = $unifi_connection_new->login();
          $aps_new_array = $unifi_connection_new->list_devices();
          foreach ($aps_new_array as $ap_new) {
            if ( $ap_new->name === $ap_mode ) {
              if ( $ap_new->type === 'uap' ) { 
                $rx_stop=$ap_new->{"rx_bytes"};
                $tx_stop=$ap_new->{"tx_bytes"};
              }
              elseif ( $ap_new->type === 'usw') {
                $rx_stop=$ap_new->{"port_table"}[$port]->{"rx_bytes"};
                $tx_stop=$ap_new->{"port_table"}[$port]->{"tx_bytes"};
              }
              $time_stop=$ap_new->stat->sw->time;
              $uptime_stop=$ap_new->uptime;
              break;
            }
          }
          $rx=$rx_stop - $rx_start;
          $tx=$tx_stop - $tx_start;   

          $seconds=$uptime_stop - $uptime_start;
          $seconds=60;
          $time_seconds=$time_stop - $time_start;

          $rx_byte=number_format($rx/$seconds/1024,3,".","");
          $rx_byte_perf=number_format($rx/$seconds/1024,3,".","");
          $rx_byte_format='KB/s';
          $tx_byte=number_format($tx/$seconds/1024,3,".","");
          $tx_byte_perf=number_format($tx/$seconds/1024,3,".","");
          $tx_byte_format='KB/s';
          $rx_bit=number_format($rx/$seconds*8/1000,3,".","");
          $rx_bit_perf=number_format($rx/$seconds*8/1000,3,".","");
          $rx_bit_format='Kbit/s';
          $tx_bit=number_format($tx/$seconds*8/1000,3,".","");
          $tx_bit_perf=number_format($tx/$seconds*8/1000,3,".","");
          $tx_bit_format='Kbit/s';

          if ( $rx_byte > 1024 ) {
             $rx_byte=number_format($rx/$seconds/1024/1024,3,".",""); 
             $rx_byte_format='MB/s'; }
          if ( $tx_byte > 1024 ) {
             $tx_byte=number_format($tx/$seconds/1024/1024,3,".",""); 
             $tx_byte_format='MB/s'; }
             
          if ( $rx_bit > 1000 ) {
             $rx_bit=number_format($rx/$seconds*8/1000/1000,3,".",""); 
             $rx_bit_format='Mbit/s'; }
          if ( $tx_bit > 1024 ) {
             $tx_bit=number_format($tx/$seconds*8/1000/1000,3,".",""); 
             $tx_bit_format='Mbit/s'; }
           
          if ($mode == "transfer" || $mode == "switch_transfer" ) {
            if ( $rx_bit < 0 )
               { $rx_bit = 0; 
                 $rx_bit_perf = 0;}
            if ( $tx_bit < 0 )
               { $tx_bit = 0; 
                 $tx_bit_perf = 0;}
            echo 'OK -' . $switch_port_name . ' rx: ' . $rx_bit . ' ' . $rx_bit_format . ' - tx: ' . $tx_bit . ' ' . $tx_bit_format . ' | rx=' . $rx_bit_perf . 'Kbit;;;; tx=' . $tx_bit_perf . 'Kbit;;;;' .  PHP_EOL; 
          }
          elseif ( $mode == "transfer+" || $mode == "switch_transfer+" ) {
            if ( $rx_byte < 0 )
               { $rx_byte = 0; 
                 $rx_byte_perf = 0;}
            if ( $tx_byte < 0 )
               { $tx_byte = 0; 
                 $tx_byte_perf = 0;}
            echo 'OK -' . $switch_port_name . ' rx: ' . $rx_byte . ' ' . $rx_byte_format . ' - tx: ' . $tx_byte . ' ' . $tx_byte_format . ' | rx=' . $rx_byte_perf . 'KB;;;; tx=' . $tx_byte_perf . 'KB;;;;' .  PHP_EOL; }
          if ( $logfile === true ) {
            echo $time_start . PHP_EOL;
            $datum=date("d:m:Y H:i:s"); 
            file_put_contents('unifi.log', ' ' . $datum . PHP_EOL ,FILE_APPEND);
            file_put_contents('unifi.log', $switch_port_name . ' rx: ' . $rx_byte . ' ' . $rx_byte_format . ' - tx: ' . $tx_byte . ' ' . $tx_byte_format . PHP_EOL  ,FILE_APPEND );
            file_put_contents('unifi.log', $switch_port_name . ' seconds: ' . $seconds . '/' . $time_seconds  . PHP_EOL ,FILE_APPEND );
            file_put_contents('unifi.log', $switch_port_name . ' rx: ' . $rx_start  . '/' . $rx_stop . ' ' . $rx .  PHP_EOL ,FILE_APPEND );
            file_put_contents('unifi.log', $switch_port_name . ' tx: ' . $tx_start  . '/' . $tx_stop . ' ' . $tx . PHP_EOL,FILE_APPEND );
            file_put_contents('unifi.log', '--------------------------------------------------------------------------------'  . PHP_EOL,FILE_APPEND );
          }
          return_status(0);
        }
    }
    echo 'UNKNOWN - no Accesspoint/Swtich found!' . PHP_EOL;
    return_status(3);
    break; 


  case 'utilisation':
    foreach ($aps_array as $ap) {
        if ($ap->type === 'uap' && ( $ap->name === $ap_mode || $ap->ip === $ap_mode || $ap->mac === $ap_mode ) ) {
          if ( $ap->radio_table[0]->radio == "ng" ) {
            $interference=$ap->radio_table_stats[0]->cu_total - $ap->radio_table_stats[0]->cu_self_rx - $ap->radio_table_stats[0]->cu_self_tx;
            $channel[0]=array('2GHz',
                             $ap->radio_table_stats[0]->cu_total,
                             $ap->radio_table_stats[0]->cu_self_rx,
                             $ap->radio_table_stats[0]->cu_self_tx,
                             $interference,);
            $interference=$ap->radio_table_stats[1]->cu_total - $ap->radio_table_stats[1]->cu_self_rx - $ap->radio_table_stats[1]->cu_self_tx;
            $channel[1]=array('5GHz',
                             $ap->radio_table_stats[1]->cu_total,
                             $ap->radio_table_stats[1]->cu_self_rx,
                             $ap->radio_table_stats[1]->cu_self_tx,
                             $interference,);
          }
          else {
            $interference=$ap->radio_table_stats[1]->cu_total - $ap->radio_table_stats[1]->cu_self_rx - $ap->radio_table_stats[1]->cu_self_tx;
            $channel[0]=array('2GHz',
                             $ap->radio_table_stats[1]->cu_total,
                             $ap->radio_table_stats[1]->cu_self_rx,
                             $ap->radio_table_stats[1]->cu_self_tx,
                             $interference,);
            $interference=$ap->radio_table_stats[0]->cu_total - $ap->radio_table_stats[0]->cu_self_rx - $ap->radio_table_stats[0]->cu_self_tx;
            $channel[1]=array('5GHz',
                             $ap->radio_table_stats[0]->cu_total,
                             $ap->radio_table_stats[0]->cu_self_rx,
                             $ap->radio_table_stats[0]->cu_self_tx,
                             $interference,);
          }
          $warn=preg_split('/,/' , $warn);
          $crit=preg_split('/,/',$crit);
          if ( $channel[0][1] >= $crit[0] || $channel[1][1] >= $crit[1] )
            { $status = 'CRITICAL' ;
              $status_code = 2; }
          elseif ( $channel[0][1] >= $warn[0] || $channel[1][1] >= $warn[1] )
            { $status = 'WARNING' ;
              $status_code = 1; }
          else
            { $status = 'OK' ;
              $status_code = 0; }
          echo $status . ' - ' . $channel[0][0] . ' Utilized: ' . $channel[0][1] . '% (rx Frames: ' . $channel[0][2] . '%, tx Frames: ' . $channel[0][3] . '%, Interference: ' . $channel[0][4] . '%)';
          echo ' - ' . $channel[1][0] . ' Utilized: ' . $channel[1][1] . '% (rx Frames: ' . $channel[1][2] . '%, tx Frames: ' . $channel[1][3] . '%, Interference: ' . $channel[1][4] . '%)';
          echo '| 2GHz=' . $channel[0][1] . '%;' . $warn[0] . ';' . $crit[0] . ';; rx=' . $channel[0][2] . '%;;;; tx=' . $channel[0][3] . '%;;;; Interference_2G=' . $channel[0][4] . '%;;;;'; 
          echo ' 5GHz=' . $channel[1][1] . '%;' . $warn[1] . ';' . $crit[1] . ';; rx=' . $channel[1][2] . '%;;;; tx=' . $channel[1][3] . '%;;;; Interference_5G=' . $channel[1][4] . '%;;;;' . PHP_EOL;
          return_status($status_code);
     }
    }
    echo 'UNKNOWN - no accesspoint found!' . PHP_EOL;
    return_status(3);
    break;

    case 'update':
      foreach ($aps_array as $ap) {
        if ( $ap->name === $ap_mode || $ap->ip === $ap_mode || $ap->mac === $ap_mode ) {
          $update_status=0;
          $status = 'OK';
          $status_code = 0;
          $version_full=$ap->version;
          $version_new_full=$ap->upgrade_to_firmware;

            if ( $ap->upgradable == 1 ) {
              $version=preg_split('/\./' ,$version_full);
              $version_new=preg_split('/\./' ,$version_new_full);
              if ($warn != '' ) {
                $warn=preg_split('/,/' ,$warn); }
              else { $warn=array(99,999,9999); }
              if ($crit != '' ) { 
                $crit=preg_split('/,/' ,$crit); }  
              else { $crit=array(99,999,9999); }

              
              if ( ($version_new[0] - $version[0]) >= $crit[0] ) {
                $status = 'CRITICAL';
                $status_code=2; 
                $update_status=1; }
              elseif ( ($version_new[0] - $version[0]) >= $warn[0] ) {
                $status = 'WARNING';
                $status_code=1; 
                $update_status=2;}
              elseif ( $version_new[0] == $version[0] ) {
                if ( ($version_new[1] - $version[1]) >= $crit[1] ) {
                  $status = 'CRITICAL';
                  $status_code=2; 
                  $update_status=3; }
                elseif ( ($version_new[1] - $version[1]) >= $warn[1] ) {
                  $status = 'WARNING';
                  $status_code=1;
                  $update_status=4;}
                elseif ( $version_new[1] == $version[1]) {
                  if ( ($version_new[2] - $version[2]) >= $crit[2] ) {
                    $status = 'CRITICAL';
                    $status_code=2; 
                    $update_status=5; }
                  elseif ( ($version_new[2] - $version[2]) >= $warn[2] ) {
                    $status = 'WARNING';
                    $status_code=1; 
                    $update_status=6;}
                }
              }
            }

            if ( str_replace('.','',$version_full) > str_replace('.','',$version_new_full) && @$version_new_full != '' )
               { $upgrade='downgradable'; } 
            else
               { $upgrade='upgradable'; } 
            
            
            if ( $update_status > 0 ) {
                echo $status . ' - firmware ' . $upgrade . ' from version ' . $version_full . ' to ' . $version_new_full . PHP_EOL;
              }
            else {
              echo $status . ' - firmware version is ' . $version_full; 
              if ( $version_full != $version_new_full and $ap->upgradable == 1 ) 
                { echo ' (upgrade version: ' . $version_new_full . ')'; }
              echo PHP_EOL;
            }

            return_status($status_code);
        }
      }
      echo 'UNKNOWN - no accesspoint/switch found!' . PHP_EOL;
      return_status(3);
      break;

     case 'client_uptime':
       foreach ($aps_array_clients as $client) {
         if ( $ap_mode == $client->name || $ap_mode == $client->ip ) {
          if ( fct_uptime(@$client->uptime) === false ) {
             echo "UNKNOWN - no client uptime found";
             return_status(3); }
          else {
            echo 'OK - ' . fct_uptime(@$client->uptime) . PHP_EOL;
            return_status(0);
           }
         }
       }
       echo 'CRITICAL - no client found' . PHP_EOL;
       return_status(2);
       break;


     case 'uptime':
      foreach ($aps_array as $ap) {
        if ( $ap->name === $ap_mode || $ap->ip === $ap_mode || $ap->mac === $ap_mode )
          {
           if ( fct_uptime(@$ap->uptime) === false ) {
             echo "UNKNOWN - no uptime found";
             return_status(3); }
           else {
           foreach ($site_array as $site_list) {
              if ( $siteid == $site_list->name ) 
                { $site_name=$site_list->desc; 
                  break;}
           }
           $device = $unifi_connection->list_device_name_mappings()->{$ap->model};
           $model=$device->display;
           echo 'OK - ' . $model .' [' . $site_name . '] - ' . fct_uptime(@$ap->uptime) . PHP_EOL;
           return_status(0);
           }
        }
      }
      echo 'CRITICAL - no accesspoint/switch found' . PHP_EOL;
      return_status(2);
      break;
     

    case 'lte_failover': 
      foreach ($aps_array as $ap) {
        if ( $ap->name === $ap_mode || $ap->ip === $ap_mode || $ap->mac === $ap_mode )
          {
            if ( @$ap->lte_failover == '' ) { 
              echo 'OK - mode ' . $ap->lte_failover_mode . ': off | failover=1' . PHP_EOL;
              return_status(0);
            }
            else {
              if ( $ap->lte_failover == 1 ) {
                 $faileover="on";
              }
              echo 'CRITICAL - mode ' . $ap->lte_failover_mode . ': ' . $failover . ' | failover=0' . PHP_EOL;
              resturn_status(2);
            }
              
        }
      }
      echo 'CRITICAL - no accesspoint/switch found' . PHP_EOL;
      return_status(2);
      break;


    case 'lte': 
      foreach ($aps_array as $ap) {
        if ( $ap->name === $ap_mode || $ap->ip === $ap_mode || $ap->mac === $ap_mode )
          {
            if ( @$ap->lte_connected == 'yes' ) { 
              echo 'OK - ' . $ap->lte_mode . ' ' . $ap->lte_band . ' [' . $ap->lte_networkoperator . '] | connected=1' . PHP_EOL;
              return_status(0);
            }
            else {
              echo 'CRITICAL - ' . $ap->lte_mode . ' ' . $ap->lte_band . ' [' . $ap->lte_networkoperator . '] | connected=0' . PHP_EOL;
              return_status(2);
            }
              
        }
      }
      echo 'CRITICAL - no accesspoint/switch found' . PHP_EOL;
      return_status(2);
      break;

     
    case 'lte_uplink': 
      foreach ($aps_array as $ap) {
        if ( $ap->name === $ap_mode || $ap->ip === $ap_mode || $ap->mac === $ap_mode )
          {
           $warn=preg_split('/,/' ,$warn);
           $crit=preg_split('/,/',$crit);
           $i=0;
           $state=0;
           $lte=array($ap->lte_rssi,$ap->lte_rsrq,$ap->lte_rsrp);
           while ($i < 3):
             if ( is_numeric($lte[$i]) ) {
               if ( $lte[$i] <= $crit[$i] )
                 { $status = 'CRITICAL' ;
                   $status_code = 2; 
                   $state=2; }
               elseif  ( $lte[$i] <= $warn[$i] && $state < 2 )
                 { $status = 'WARNING' ;
                   $status_code = 1; 
                   $state=1; } 
               elseif ( $state == 0 && $state == 0 ) 
                 { $status = 'OK' ;
                   $status_code = 0; }
             }
           
             $i++;
           endwhile;
           if ( empty($warn[1]) && empty($crit[1]) ) 
             { $status = 'OK' ;
               $status_code = 0; }
           echo $status . ' - LTE-Uplink - RSSI: ' . $ap->lte_rssi . ' dBm, RSRQ: ' . $ap->lte_rsrq . ' db, RSRP: ' . $ap->lte_rsrp . 'dBm | ';
           echo 'RSSI=' . $ap->lte_rssi . ';' . $warn[0] . ';' . $crit[0] . ';; ';
           echo 'RSRQ=' . $ap->lte_rsrq . ';' . $warn[1] . ';' . $crit[1] . ';; ';
           echo 'RSRP=' . $ap->lte_rsrp . ';' . $warn[2] . ';' . $crit[2] . ';; ' . PHP_EOL;
           return_status($status_code);
      
        }
      }
      echo 'CRITICAL - no accesspoint/switch found' . PHP_EOL;
      return_status(2);
      break;

    case 'alarms_count': 
      if ( $alarms_count[0]->count > 0 ) {
        echo 'CRITICAL - Alarms: ' . $alarms_count[0]->count . ' active (Archiv: ' . $alarms_archived[0]->count . ')| Alarms=' . $alarms_count[0]->count . ';;;;' . PHP_EOL;
        return_status(2);
      } 
      else {
        echo 'OK - no active Alarms (Archiv: ' . $alarms_archived[0]->count . ') | Alarms=0;;;;' . PHP_EOL;
        return_status(0);
      }
      break;

    default:
      echo "Error - please check help - /usr/bin/php check_unifi.php -h" . PHP_EOL;
      return_status(3);
}  
?>
