#!/usr/bin/env python3
# pylint: disable=invalid-name
"""
Icinga/Nagios plugin to check Gitlab personal access tokens expiration date.

Copyright (c) 2024 Benjamin Renard <brenard@easter-eggs.com>

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License version 3
as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
"""
import argparse
import datetime
import logging
import sys
import traceback

import humanize
import requests
from dateutil.parser import isoparse
from requests.exceptions import HTTPError

parser = argparse.ArgumentParser()

parser.add_argument("-d", "--debug", action="store_true")
parser.add_argument("-v", "--verbose", action="store_true")
parser.add_argument(
    "-U",
    "--url",
    help="Gitlab URL",
)
parser.add_argument(
    "-T",
    "--access-token",
    help="Gitlab access token (use to authenticate on API)",
)
parser.add_argument(
    "-G",
    "--group-id",
    help="If you want to check a group access token, specify the group ID",
)
parser.add_argument(
    "-P",
    "--project-id",
    help="If you want to check a project access token, specify the project ID",
)
parser.add_argument(
    "-u", "--user-id", type=int, help="Gitlab user ID to limit check on this user's access tokens"
)
parser.add_argument(
    "-t", "--timeout", type=int, help="Specify timeout for HTTP requests (default: 20)", default=20
)
parser.add_argument(
    "-w", "--warning", type=int, help="Warning threshold in days (default: 7)", default=7
)
parser.add_argument(
    "-c", "--critical", type=int, help="Critical threshold in days (default: 2)", default=2
)


options = parser.parse_args()

if not options.url:
    parser.error("Gitlab URL is missing. Please specify it using -U/--url parameter.")

if not options.access_token:
    parser.error(
        "Gitlab access token is missing. Please specify it using -T/--access-token parameter."
    )

if sum(1 for x in filter(lambda x: x, [options.user_id, options.group_id, options.project_id])) > 1:
    parser.error(
        "You can specify only one of user ID, group ID and project ID. "
        "What type of access token you want to check?"
    )

logging.basicConfig(
    level=logging.DEBUG if options.debug else (logging.INFO if options.verbose else logging.WARNING)
)

warning_limit = datetime.timedelta(days=options.warning)
critical_limit = datetime.timedelta(days=options.critical)
now = datetime.datetime.now()

errors = []
messages = []
exit_code = 0
exit_code_to_status = {0: "OK", 1: "WARNING", 2: "CRITICAL", 3: "UNKNOWN"}

if options.group_id:
    url = f"{options.url}/api/v4/groups/{options.group_id}/access_tokens"
elif options.project_id:
    url = f"{options.url}/api/v4/projects/{options.project_id}/access_tokens"
else:
    url = f"{options.url}/api/v4/personal_access_tokens"
if options.user_id:
    url += f"?user_id={options.user_id}"
try:
    logging.debug("Get access tokens from %s...", url)
    r = requests.get(url, timeout=options.timeout, headers={"PRIVATE-TOKEN": options.access_token})
    try:
        r.raise_for_status()
    except HTTPError:
        if r.status_code == 401:
            print("UNKNOWN - The access token used does not have the necessary permissions")
        else:
            try:
                data = r.json()
            except ValueError:
                data = {}
            print(
                "UNKNOWN - Fail to retrieve access token info from Gitlab API "
                f"({data.get('message', r.status_code)})"
            )
        sys.exit(3)
    data = r.json()
    logging.debug("Data retrieved (HTTP status code: %d):\n%s", r.status_code, data)
    for access_token in data:
        if not access_token["active"]:
            logging.debug(
                "Access token %s (#%d) is inactive, ignore it",
                access_token["name"],
                access_token["id"],
            )
            continue
        if access_token["revoked"]:
            logging.debug(
                "Access token %s (#%d) is revoked, ignore it",
                access_token["name"],
                access_token["id"],
            )
            continue
        expiration_date = isoparse(access_token["expires_at"])
        expiration_delay = expiration_date - now
        logging.debug(
            "Access token %s (#%d) will expire in %s",
            access_token["name"],
            access_token["id"],
            humanize.naturaltime(expiration_date),
        )
        msg = (
            f"Access token {access_token['name']} ({access_token['id']}) will expire "
            f"{humanize.naturaltime(expiration_date)} ({access_token['expires_at']})"
        )
        messages.append(msg)
        if expiration_delay <= critical_limit:
            errors.append(msg)
            exit_code = 2
        elif expiration_delay <= warning_limit:
            errors.append(msg)
            exit_code = 1 if exit_code < 1 else exit_code
except Exception:  # pylint: disable=broad-except
    logging.debug(
        "Exception occurred retrieving personal access tokens from Gitlab API:\n%s",
        traceback.format_exc(),
    )
    print("UNKNOWN - Exception occurred retrieving personal access tokens from Gitlab API")
    sys.exit(3)


if exit_code == 0:
    print("OK - No access token about to expire")
else:
    print(f"{exit_code_to_status[exit_code]} - {', '.join(errors)}")
print("\n".join(messages))
sys.exit(exit_code)