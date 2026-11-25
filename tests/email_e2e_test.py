#!/usr/bin/env python3
"""Email-to-ticket end-to-end test (AGENTS Next Step 3).

Flow:
  1. login
  2. create an incident (triggers the outbound ticket_created notification)
  3. POST a raw RFC822 reply whose subject contains the ticket number
     -> must be appended as a comment on the SAME ticket (exercises the
        extractTicketNumber fix for bracketed subjects)
  4. POST a raw RFC822 mail with no ticket number -> new ticket, source=email
  5. verify both through the API, then delete the test tickets

Usage: python3 tests/email_e2e_test.py   (stdlib only, run from repo root)
"""
import json
import sys
import urllib.request
import urllib.error
import http.cookiejar

BASE = "https://itsm.governit.dk/api"
ADMIN = ("sb@governit.dk", "Skyservice20!")
SENDER = "alice@democorp.com"  # existing demo user (customer 2)

jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))


import os
INBOUND_TOKEN = os.environ.get("INBOUND_EMAIL_TOKEN", "")  # required: /email/receive fails closed


def call(method, path, body=None, raw=None, content_type="application/json", extra_headers=None):
    if raw is not None:
        data = raw.encode()
    elif body is not None:
        data = json.dumps(body).encode()
    else:
        data = None
    headers = {"Content-Type": content_type, "Accept": "application/json"}
    headers.update(extra_headers or {})
    req = urllib.request.Request(BASE + path, data=data, method=method, headers=headers)
    try:
        with opener.open(req, timeout=30) as resp:
            return resp.status, json.loads(resp.read().decode() or "{}")
    except urllib.error.HTTPError as e:
        raw_body = e.read().decode()
        try:
            return e.code, json.loads(raw_body)
        except json.JSONDecodeError:
            return e.code, {"raw": raw_body}


failures = []


def check(label, cond, detail=""):
    print(f"[{'PASS' if cond else 'FAIL'}] {label}" + (f" — {detail}" if detail and not cond else ""))
    if not cond:
        failures.append(label)


def delete_quiet(ticket_id):
    call("DELETE", f"/tickets/{ticket_id}")


# 1. login
status, body = call("POST", "/auth/login", {"email": ADMIN[0], "password": ADMIN[1]})
check("login", status == 200, f"status={status}")

# 2. outbound: create an incident
status, body = call("POST", "/tickets", {
    "type": "incident",
    "title": "EMAIL-E2E-TEST outbound notification probe",
    "description": "Automated email end-to-end test (tests/email_e2e_test.py)",
    "priority": "low",
})
check("create incident 201", status == 201, f"status={status} {body.get('message')}")
orig = body.get("data") or {}
orig_id, orig_number = orig.get("id"), orig.get("ticket_number")
check("ticket number issued", bool(orig_number), f"ticket={orig_number}")
if not orig_id:
    print("cannot continue without a ticket"); sys.exit(1)

# 3. inbound reply with bracketed ticket number in subject
reply_body = (
    f"From: {SENDER}\r\n"
    f"To: support@itsm.governit.dk\r\n"
    f"Subject: Re: [{orig_number}] EMAIL-E2E-TEST outbound notification probe\r\n"
    f"Message-ID: <e2e-reply-001@democorp.com>\r\n"
    f"Content-Type: text/plain; charset=UTF-8\r\n"
    f"\r\n"
    f"This is the e2e email reply. It must land on the original ticket.\r\n"
)
status, body = call("POST", "/email/receive", extra_headers={"X-Inbound-Token": INBOUND_TOKEN}, raw=reply_body,
                    content_type="text/plain")
check("inbound reply accepted", status == 200, f"status={status} {body.get('message')}")
reply_ticket = body.get("data") or {}
check("reply matched the original ticket", reply_ticket.get("id") == orig_id,
      f"got id={reply_ticket.get('id')} expected={orig_id}")

# reply must be visible as a comment on the ticket
status, body = call("GET", f"/tickets/{orig_id}")
comments = (body.get("data") or {}).get("comments") or []
bodies = [c.get("body", "") for c in comments]
check("reply appended as comment",
      any("e2e email reply" in b and f"[Email from {SENDER}]" in b for b in bodies),
      f"comments={bodies}")

# 4. inbound mail with no ticket number -> new ticket
new_body = (
    f"From: {SENDER}\r\n"
    f"To: support@itsm.governit.dk\r\n"
    f"Subject: EMAIL-E2E-NEW inbound ticket from email\r\n"
    f"Message-ID: <e2e-new-001@democorp.com>\r\n"
    f"Content-Type: text/plain; charset=UTF-8\r\n"
    f"\r\n"
    f"Printer on floor 4 is making strange noises.\r\n"
)
status, body = call("POST", "/email/receive", extra_headers={"X-Inbound-Token": INBOUND_TOKEN}, raw=new_body,
                    content_type="text/plain")
check("inbound new mail 201", status == 201, f"status={status} {body.get('message')}")
new_ticket = body.get("data") or {}
new_id = new_ticket.get("id")
check("new ticket created from email", bool(new_id))
if new_id:
    check("new ticket source=email", new_ticket.get("source") == "email",
          f"source={new_ticket.get('source')}")
    check("new ticket title from subject",
          new_ticket.get("title") == "EMAIL-E2E-NEW inbound ticket from email",
          f"title={new_ticket.get('title')}")
    check("new ticket customer resolved (DemoCorp)",
          new_ticket.get("customer_name") == "DemoCorp" or new_ticket.get("customer_id") == 2,
          f"customer={new_ticket.get('customer_name') or new_ticket.get('customer_id')}")

# 5. cleanup
status, _ = call("DELETE", f"/tickets/{orig_id}")
check("delete original ticket", 200 <= status < 300, f"status={status}")
if new_id:
    status, _ = call("DELETE", f"/tickets/{new_id}")
    check("delete inbound ticket", 200 <= status < 300, f"status={status}")

print("-" * 40)
if failures:
    print(f"FAILED: {len(failures)}: {failures}")
    sys.exit(1)
print("ALL EMAIL E2E TESTS PASSED")
