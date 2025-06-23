#!/usr/bin/env python3
# script to check the cameras in zoneminder
# created by Ringo Gierth rgi@oneserv.de
import requests
import sys
import argparse
import re

BYTES_IN_MB = 1024 * 1024


def get_token(base_url, username, password):
    try:
        resp = requests.post(
            f"{base_url}/host/login.json",
            data={"user": username, "pass": password},
            timeout=5
        )
        resp.raise_for_status()
        data = resp.json()
        token = data.get("access_token")
        if not token:
            print("CRITICAL - No access_token returned")
            sys.exit(2)
        return token
    except Exception as e:
        print(f"UNKNOWN - Login error: {e}")
        sys.exit(3)


def check_daemon(base_url, token):
    try:
        url = f"{base_url}/host/daemonCheck.json?token={token}"
        resp = requests.get(url, timeout=5)
        resp.raise_for_status()
        if str(resp.json().get("result")) != "1":
            print("CRITICAL - ZoneMinder daemon is NOT running")
            return 2
        print("OK - ZoneMinder daemon is running")
        return 0
    except Exception as e:
        print(f"UNKNOWN - API call to daemonCheck failed: {e}")
        return 3


def check_cameras(base_url, token):
    try:
        url = f"{base_url}/monitors.json?token={token}"
        resp = requests.get(url, timeout=5)
        resp.raise_for_status()
        data = resp.json()

        bad = []
        good = []
        perf_items = []
        healthy = 0
        unhealthy = 0

        for entry in data.get("monitors", []):
            mon = entry.get("Monitor", {})
            st = entry.get("Monitor_Status", {})
            name = mon.get("Name", "Unknown")
            fn = mon.get("Function", "")
            en = str(mon.get("Enabled", "0"))
            conn = st.get("Status", "Unknown")
            fps = float(st.get("CaptureFPS", 0.0))
            bw_mb = int(st.get("CaptureBandwidth", 0)) / BYTES_IN_MB

            line = (f"{name} - Status: {conn}, Function: {fn}, Enabled: {en}, "
                    f"FPS: {fps:.2f}, BW: {bw_mb:.2f}MB/s")
            key = re.sub(r"\W+", "_", name)
            perf_items.append(f"{key}_fps={fps:.2f}")
            perf_items.append(f"{key}_bw={bw_mb:.2f}MB/s")

            if en == "1":
                if conn != "Connected" or fn.lower() == "none":
                    bad.append(f"[BAD] {line}")
                    unhealthy += 1
                else:
                    good.append(f"[GOOD] {line}")
                    healthy += 1

        perf_items.insert(0, f"healthy={healthy}")
        perf_items.insert(1, f"unhealthy={unhealthy}")
        perfdata = " ".join(perf_items)

        if bad:
            print("CRITICAL - Cameras with issues:\n" + "\n".join(bad + good) + f"\n| {perfdata}")
            return 2
        if good:
            print("OK - All enabled cameras are healthy:\n" + "\n".join(good) + f"\n| {perfdata}")
            return 0
        print("WARNING - No enabled cameras found\n| healthy=0 unhealthy=0")
        return 1

    except Exception as e:
        print(f"UNKNOWN - Error checking cameras: {e}")
        return 3


if __name__ == "__main__":
    parser = argparse.ArgumentParser(
        description="Check ZoneMinder daemon & camera status via API",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  # Both checks:
  check_zoneminder.py --base-url https://server/zm/api \
                     --username user --password pass

  # Daemon only:
  check_zoneminder.py --mode daemon --base-url https://server/zm/api \
                     --username user --password pass

  # Cameras only:
  check_zoneminder.py --mode cameras --base-url https://server/zm/api \
                     --username user --password pass
"""
    )
    parser.add_argument("--mode", choices=["daemon","cameras","all"], default="all",
                        help="Which check to run")
    parser.add_argument("--base-url", required=True,
                        help="ZoneMinder API base URL (e.g., https://server/zm/api)")
    parser.add_argument("--username", required=True, help="API username")
    parser.add_argument("--password", required=True, help="API password")
    args = parser.parse_args()

    token = get_token(args.base_url, args.username, args.password)

    if args.mode == "daemon":
        sys.exit(check_daemon(args.base_url, token))
    elif args.mode == "cameras":
        sys.exit(check_cameras(args.base_url, token))
    else:
        d = check_daemon(args.base_url, token)
        c = check_cameras(args.base_url, token)
        sys.exit(max(d, c))
