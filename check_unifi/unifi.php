<?php
$host='xxx.xxx.xxx.xxx';               # IP or Hostname unifi controller Server
$prot='https';                         # Value http or https
$port='8443';                          # Controller Port
$user='nagios';                        # Loginuser Controller
$pass='nagios';                        # Password Loginuser
$siteid='default';                     # Site ID
$dir_unifi_client='';                  # Directory unifi_client.php

$status_ap=array(0=>'CRITICAL',        #offline
                 1=>'OK',              #connected
                 2=>'WARNING',         #pending adoption
                 4=>'WARNING',         #updating
                 5=>'WARNING',         #provisionig
                 6=>'CRITICAL',        #unreachable
                 7=>'WARNING',         #adopting
                 9 =>'CRITICAL',       #adopting error
                 11=>'CRITICAL'        #isolated
                 );
?>
