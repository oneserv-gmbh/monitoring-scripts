#!/usr/bin/env python3
# Name          : check_ccu_temp.py
# Date          : 20260513
# Author        : Erik Exner - exner@oneserv.de
# Summary       : This python script checks the CCU temperature and humidity via XML-API
# License       : Apache 2.0
# Min. Python   : 3.8


import argparse
import sys
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET


def exit_unknown(message):
    print(f"UNKNOWN - {message}")
    sys.exit(3)


def fetch_value(base_url, sid, datapoint_id, timeout):
    if sid:
        full_url = (
            f"{base_url.rstrip('/')}/state.cgi"
            f"?sid={sid}"
            f"&datapoint_id={datapoint_id}"
        )
    else:
        full_url = (
            f"{base_url.rstrip('/')}/state.cgi"
            f"?datapoint_id={datapoint_id}"
        )
    try:
        with urllib.request.urlopen(full_url, timeout=timeout) as response:
            xml_data = response.read()
    except Exception as e:
        exit_unknown(f"HomeMatic API nicht erreichbar: {e}")
    try:
        root = ET.fromstring(xml_data)
        datapoint = root.find("datapoint")
        if datapoint is None:
            exit_unknown(f"Kein datapoint-Element in Antwort für {datapoint_id}")
        value = datapoint.attrib.get("value")
        if value is None:
            exit_unknown(f"Kein value-Attribut in Antwort für {datapoint_id}")
        return float(value)
    except ET.ParseError as e:
        exit_unknown(f"XML konnte nicht geparst werden: {e}")
    except ValueError as e:
        exit_unknown(f"Wert ist keine Zahl: {e}")

def evaluate_range(value, warn_min, warn_max, crit_min, crit_max):
    if value < crit_min or value > crit_max:
        return 2

    if value < warn_min or value > warn_max:
        return 1

    return 0


def main():
    parser = argparse.ArgumentParser(
        description="Check HomeMatic temperature and humidity via XML-API"
    )

    parser.add_argument("--url", required=True, help="z.B. http://172.16.21.2/addons/xmlapi")
    parser.add_argument("--sid", required=False, help="HomeMatic XML-API SID, z.B. @3aMaCQrNfc@")

    parser.add_argument("--temperature-id", required=True)
    parser.add_argument("--humidity-id", required=True)

    parser.add_argument("--temp-warn-min", type=float, default=18)
    parser.add_argument("--temp-warn-max", type=float, default=28)
    parser.add_argument("--temp-crit-min", type=float, default=10)
    parser.add_argument("--temp-crit-max", type=float, default=35)

    parser.add_argument("--hum-warn-min", type=float, default=30)
    parser.add_argument("--hum-warn-max", type=float, default=70)
    parser.add_argument("--hum-crit-min", type=float, default=20)
    parser.add_argument("--hum-crit-max", type=float, default=80)

    parser.add_argument("--timeout", type=int, default=10)

    args = parser.parse_args()

    temperature = fetch_value(
        args.url,
        args.sid,
        args.temperature_id,
        args.timeout,
    )

    humidity = fetch_value(
        args.url,
        args.sid,
        args.humidity_id,
        args.timeout,
    )

    temp_state = evaluate_range(
        temperature,
        args.temp_warn_min,
        args.temp_warn_max,
        args.temp_crit_min,
        args.temp_crit_max,
    )

    hum_state = evaluate_range(
        humidity,
        args.hum_warn_min,
        args.hum_warn_max,
        args.hum_crit_min,
        args.hum_crit_max,
    )

    state = max(temp_state, hum_state)

    state_text = {
        0: "OK",
        1: "WARNING",
        2: "CRITICAL",
        3: "UNKNOWN",
    }[state]

    print(
        f"{state_text} - Temperatur: {temperature:.2f} C, Luftfeuchte: {humidity:.0f} % "
        f"| temperature={temperature:.2f};{args.temp_warn_min}:{args.temp_warn_max};"
        f"{args.temp_crit_min}:{args.temp_crit_max} "
        f"humidity={humidity:.0f};{args.hum_warn_min}:{args.hum_warn_max};"
        f"{args.hum_crit_min}:{args.hum_crit_max}"
    )

    sys.exit(state)


if __name__ == "__main__":
    main()