#!/usr/bin/env python3
import argparse
import re
import sys
import warnings

import requests
from urllib3.exceptions import InsecureRequestWarning

VER_RE = re.compile(r"(\d+)\.(\d+)\.(\d+)")


def parse_ver(s: str):
    m = VER_RE.search(s or "")
    return tuple(map(int, m.groups())) if m else None


def http_get(session: requests.Session, base: str, path: str, timeout: int):
    r = session.get(f"{base}{path}", timeout=timeout)
    if r.status_code != 200:
        # keep output short for monitoring systems
        txt = (r.text or "").replace("\n", " ")
        return None, f"HTTP {r.status_code}: {txt[:200]}"
    return r.json(), None


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--host", required=True, help="FortiGate management IP/FQDN")
    ap.add_argument("--port", type=int, default=443, help="HTTPS port (default: 443)")
    ap.add_argument("--token", required=True, help="FortiOS REST API token")
    ap.add_argument("--insecure", action="store_true", help="Disable TLS certificate verification")
    ap.add_argument(
        "--no-ssl-warn",
        action="store_true",
        help="Suppress urllib3 InsecureRequestWarning (useful with --insecure)",
    )
    ap.add_argument("--timeout", type=int, default=10, help="HTTP timeout in seconds (default: 10)")
    args = ap.parse_args()

    if args.no_ssl_warn:
        warnings.simplefilter("ignore", InsecureRequestWarning)

    base = f"https://{args.host}:{args.port}"

    s = requests.Session()
    s.headers["Authorization"] = f"Bearer {args.token}"
    s.verify = not args.insecure

    status, err = http_get(s, base, "/api/v2/monitor/system/status", args.timeout)
    if err:
        print(f"UNKNOWN - cannot fetch system status: {err}")
        return 3

    # FortiOS responses vary slightly; try common shapes
    installed_raw = (
        status.get("version")
        or (status.get("results") or {}).get("version")
        or (status.get("results") or {}).get("firmware_version")
        or ""
    )
    installed = parse_ver(str(installed_raw).lstrip("v"))
    if not installed:
        print(f"UNKNOWN - cannot parse installed version from: {installed_raw!r}")
        return 3

    fw, err = http_get(s, base, "/api/v2/monitor/system/firmware", args.timeout)
    if err:
        print(f"UNKNOWN - cannot fetch firmware list: {err}")
        return 3

    results = fw.get("results", fw)
    candidates = []

    # handle common shapes
    if isinstance(results, dict):
        if isinstance(results.get("available"), list):
            candidates = results["available"]
        elif isinstance(results.get("images"), list):
            candidates = results["images"]
        elif isinstance(results.get("versions"), list):
            candidates = results["versions"]
    elif isinstance(results, list):
        candidates = results

    maj, mino, pat = installed
    best = installed

    for item in candidates:
        if not isinstance(item, dict):
            continue

        v = None
        if all(k in item for k in ("major", "minor", "patch")):
            try:
                v = (int(item["major"]), int(item["minor"]), int(item["patch"]))
            except Exception:
                v = None
        if not v:
            v = parse_ver(str(item.get("version", "")).lstrip("v"))

        if not v:
            continue

        # only within the same release line (e.g. 7.4.x)
        if v[0] == maj and v[1] == mino and v > best:
            best = v

    if best > installed:
        print(f"WARNING - FortiOS {maj}.{mino}.{best[2]} verfügbar (installiert {maj}.{mino}.{pat})")
        return 1

    print(f"OK - FortiOS {maj}.{mino}.{pat} ist aktuell (Patch-Level)")
    return 0


if __name__ == "__main__":
    sys.exit(main())