#!/usr/bin/python3
# Name          : check_portainer_license.py
# Date          : 20250213
# Author        : Erik Exner - erik.exner@it-exner.de
# Summary       : This python script checks the portainer license expiration date
# License       : Apache 2.0
# Min. Python   : 3.8

import sys
import requests
import json
import argparse
import urllib3
import datetime

urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

parser = argparse.ArgumentParser(description="check_portainer_license")
parser.add_argument("-H", "--host", dest="host", help="The portainer monitoring url with http:// | https:// and port", required=True)
parser.add_argument("-t", "--token", dest="token", help="The api token to use, found in Webfrontend", required=True)
parser.add_argument("-k", "--insecure", dest="insecure", help="Dont verify the ssl-certificate", default=False, action=argparse.BooleanOptionalAction)
args = parser.parse_args()

endpoint = "/api/licenses"
verifySSL = True

if args.host:
    if args.insecure:
        verifySSL = False
    x = requests.get(args.host + endpoint, verify=verifySSL, headers={"X-API-Key":args.token})
    if x.status_code == 200:
        data = json.loads(x.text)
        expiresAt = data[0]['expiresAt']
        expiresAt_dt = datetime.datetime.fromtimestamp(expiresAt).strftime('%Y-%m-%d')
        expiresAt_dt = datetime.datetime.strptime(expiresAt_dt, '%Y-%m-%d').date() 
        
        now = datetime.date.today() 
        
        delta = expiresAt_dt - now
        delta = str(delta).split(' ')[0] # remove unwanted time information

        if int(delta) >= 30:
            print("OK: License expires in " + str(delta) + " days - "+ str(expiresAt_dt) +". Lehnt euch zurück und genießt die Ruhe vor dem Auslaufen.")
            sys.exit(0)
        else:
            print("CRITICAL: License expires in " + str(delta) + " days- "+ str(expiresAt_dt) +"! Nehmt die Beine in die Hand und verlängert die Lizenz.")
            sys.exit(2)
    elif x.status_code == 401:
        print("Wrong Token: " + str(x.status_code))
        sys.exit(3) #Unknown
    else:
        print("Something gone wrong: " + str(x.status_code))
        sys.exit(3) #Unknown
else:
    print("Missing arguments")
    sys.exit(3) #Unknown
