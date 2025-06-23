#!/usr/bin/env python3

import requests
import json
from optparse import OptionParser

#    User Vars
XoApiUri     = '/rest/v0'
Debug = False

#    Script Vars - do not edit
XoServerProto   = ''
XoServerUrl     = ''
XoAuthToken     = ''

XoCritPools     = []
XoOutputText    = ''

Timeout = 20


def getData(ApiUri, ReqType, Custom404 = False):
    try:
        if ReqType == "get":
                req = requests.get(str(XoCompleteUrl) + ApiUri, cookies=Cookies, timeout=Timeout)
        elif ReqType == "post":
            req = requests.post(str(XoCompleteUrl) + ApiUri, cookies=Cookies, timeout=Timeout)
        else:
            print('Error: Unknown Request Type!')
            exit(3)

        req.raise_for_status()
        return req.text
    except requests.HTTPError as ex:
        if req.status_code == 404 and Custom404 == True:
            return '{ "name_label": "NOT_FOUND" }'
        else:
            print('Error while getting Data: ' + str(ex))
            exit(3)
    except requests.Timeout:
            print('Error while getting Data: Timeout')
            exit(3)

def debugPrint(Text):
    if Debug == True:
        print('[DEBUG] ' + Text)

def parse_opts():
    parser = OptionParser()
    parser.add_option("--protocol")
    parser.add_option("--url")
    parser.add_option("--token")
    return parser.parse_args()


if __name__ == '__main__':
    (options, args) = parse_opts()

    XoServerProto   = options.protocol
    XoServerUrl     = options.url
    XoAuthToken     = options.token

    XoCompleteUrl     = str(XoServerProto) + '://' + str(XoServerUrl) + str(XoApiUri)
    Cookies = { 'authenticationToken': str(XoAuthToken) }

    XoPools = getData('/pools' ,'get')

    jsn_list = json.loads(XoPools)
    for pool in jsn_list:
        #debugPrint(str(pool))
        pool_uuid = str(pool).replace('/rest/v0','')
        XoPoolsDetails = getData(pool_uuid ,'get')
        XoPoolsDetailsJsonList = json.loads(XoPoolsDetails)
        #debugPrint(str(XoPoolsDetailsJsonList))
        XoPoolName = str(XoPoolsDetailsJsonList['name_label'])

        XoPoolsDetailsMissingPatches = getData(pool_uuid + '/missing_patches','get')
        #debugPrint(str(XoPoolsDetailsMissingPatches))
        XoPoolsDetailsMissingPatchesJsonList = json.loads(XoPoolsDetailsMissingPatches)
        if not XoPoolsDetailsMissingPatchesJsonList:
            debugPrint(XoPoolName + ': ok')
        else:
            debugPrint(XoPoolName + ': critical')
            XoCritPools.append(XoPoolName + ' count: ' + str(len(XoPoolsDetailsMissingPatchesJsonList)))

    if len(XoCritPools) > 0:
        XoOutputText = str(XoCritPools)
        print('CRITICAL - ' + XoOutputText)
        exit(2)
    else:
        print('OK - Super Arbeit Jungs (& Mädels)!')
        exit(0)

    print("Hier sollte man niemals landen... Glückwunsch zu der Leistung...")
    exit(3)