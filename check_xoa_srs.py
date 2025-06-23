#!/usr/bin/env python3

import requests
import json
from optparse import OptionParser
#    User Vars
XoApiUri     = '/rest/v0'
ExcludeTag = 'no_monitoring'
Debug = False

#    Script Vars - do not edit
XoServerProto     = ''
XoServerUrl     = ''
XoAuthToken     = ''

TresholdWarn    = 0
TresholdCrit    = 0

XoWarnSRs    = []
XoCritSRs    = []
XoOutputText    = ''

Timeout = 10


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

def getHostnameOfSR(ContainerId, ContentType, SrType):
    if ContentType == 'iso' and SrType == 'iso':
        XoContainerData = getData('/pools/' + str(ContainerId), 'get', True)
    elif ContentType == 'user' and SrType == 'lvmoiscsi':
        XoContainerData = getData('/pools/' + str(ContainerId), 'get', True)
    elif ContentType == '' and SrType == 'nfs':
        XoContainerData = getData('/pools/' + str(ContainerId), 'get', True)
    elif ContentType == 'user' and SrType == 'nfs':
        XoContainerData = getData('/pools/' + str(ContainerId), 'get', True)
    elif ContentType == 'disk' and SrType == 'udev':
        XoContainerData = getData('/hosts/' + str(ContainerId), 'get', True)
    elif ContentType == 'iso' and SrType == 'udev':
        XoContainerData = getData('/hosts/' + str(ContainerId), 'get', True)
    elif ContentType == 'user' and SrType == 'lvm':
        XoContainerData = getData('/hosts/' + str(ContainerId), 'get', True)
    elif ContentType == 'user' and SrType == 'ext':
        XoContainerData = getData('/hosts/' + str(ContainerId), 'get', True)
    else:
        return 'COMBO_NOT_FOUND'

    XoContainerDataJson = json.loads(XoContainerData)

    return XoContainerDataJson['name_label']


def debugPrint(Text):
    if Debug == True:
        print('[DEBUG] ' + Text)

def parse_opts():
    parser = OptionParser()
    parser.add_option("--protocol")
    parser.add_option("--url")
    parser.add_option("--token")
    parser.add_option("--warning")
    parser.add_option("--critical")
    return parser.parse_args()


if __name__ == '__main__':
    (options, args) = parse_opts()

    XoServerProto   = options.protocol
    XoServerUrl     = options.url
    XoAuthToken     = options.token
    TresholdWarn    = options.warning
    TresholdCrit    = options.critical

    XoCompleteUrl     = str(XoServerProto) + '://' + str(XoServerUrl) + str(XoApiUri)
    Cookies = { 'authenticationToken': str(XoAuthToken) }

    XoSrs = getData('/srs?fields=name_label,$container,size,usage,id,physical_usage,content_type,SR_type&filter=!"tags":"' + ExcludeTag + '"', 'get')

    jsn_list = json.loads(XoSrs)

    for lis in jsn_list:
        percent = 0
        if lis['size'] > 0:
            percent = round((lis['physical_usage']/lis['size'])*100, 3)
        if percent > int(TresholdWarn) and percent < int(TresholdCrit):
            status = 'Warning'
            XoWarnSRs.append('SR-ID: ' + str(lis['id']) + ' | ' + str(percent)  + '% | Name: ' + str(lis['name_label']) + ' | Container: ' + str(getHostnameOfSR(str(lis['$container']), str(lis['content_type']), str(lis['SR_type']))) + ' ('+ str(lis['$container']) + ')')
        elif percent > int(TresholdCrit):
            status = 'Critical'
            XoCritSRs.append('SR-ID: ' + str(lis['id']) + ' | ' + str(percent)  + '% | Name: ' + str(lis['name_label']) + ' | Container: ' + str(getHostnameOfSR(str(lis['$container']), str(lis['content_type']), str(lis['SR_type']))) + ' ('+ str(lis['$container']) + ')')
        else:
            status = 'OK'
        debugPrint('ID: ' + str(lis['id']) + ' | Status: ' + status + ' | Size: ' + str(lis['physical_usage']) + '/' + str(lis['size']) + ' | Percent: ' + str(percent)  + '% | Name: ' + str(lis['name_label']) + ' | Container: ' + str(getHostnameOfSR(str(lis['$container']), str(lis['content_type']), str(lis['SR_type']))) + ' ('+ str(lis['$container']) + ')')


    if len(XoCritSRs) > 0:
        XoOutputText = str(XoCritSRs)
        if len(XoWarnSRs) > 0:
            XoOutputText = XoOutputText + ", WARNING: " + str(XoWarnSRs)
        print('CRITICAL - ' + XoOutputText)
        exit(2)
    if len(XoWarnSRs) > 0:
        XoOutputText = str(XoWarnSRs)
        print('WARNING - ' + str(XoOutputText))
        exit(1)
    if len(XoWarnSRs) == 0 and len(XoCritSRs) == 0:
        print('OK - Super Arbeit Jungs (& Mädels)!')
        exit(0)

    print("Hier sollte man niemals landen... Glückwunsch zu der Leistung...")
    exit(3)
