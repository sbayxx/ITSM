#!/usr/bin/env python3
"""Vendor flow test: send-to-vendor (API + mail), vendor reply API, inbound mail token,
status rules, tenant isolation and cleanup.

Prerequisites (run once, from the repo root):
  - INBOUND_EMAIL_TOKEN env var = the token configured in backend/.env on the server
  - the test double tests/vendor_mock.php uploaded to the web root as /vendor_mock.php
    (it is used as the vendor API, see MOCK_API). Delete it again after the run.
  - clear rate-limiter locks on the server first (5 logins / 15 min per IP)

Usage: INBOUND_EMAIL_TOKEN=... python3 tests/vendor_api_test.py
"""
import json
import os
import sys
import time
import urllib.request
import urllib.error
import http.cookiejar

BASE = "https://itsm.governit.dk/api"
ADMIN = ("sb@governit.dk", "Skyservice20!")
TOKEN = os.environ.get("INBOUND_EMAIL_TOKEN", "")
MOCK_API = "https://itsm.governit.dk/vendor_mock.php"
MAIL_VENDOR = "vendor-test@governit.dk"   # not a real mailbox; bounce is expected
DEMOCORP, TESTORG = 2, 3

jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
failures = []


def call(method, path, body=None, raw=None, headers=None, base=BASE, use_cookies=True):
    data = raw.encode() if raw is not None else (json.dumps(body).encode() if body is not None else None)
    h = {"Content-Type": "application/json", "Accept": "application/json"}
    h.update(headers or {})
    req = urllib.request.Request(base + path, data=data, method=method, headers=h)
    op = opener if use_cookies else urllib.request.build_opener()
    try:
        with op.open(req, timeout=60) as resp:
            return resp.status, json.loads(resp.read().decode() or "{}")
    except urllib.error.HTTPError as e:
        raw_body = e.read().decode()
        try:
            return e.code, json.loads(raw_body)
        except json.JSONDecodeError:
            return e.code, {"raw": raw_body}


def check(label, cond, detail=""):
    print(("[PASS] " if cond else "[FAIL] ") + label + ("" if cond or not detail else f" -- {detail}"))
    if not cond:
        failures.append(label)


def data(body):
    return body.get("data") or {}


def vendor_key_call(method, path, key, body=None):
    return call(method, "/v1/vendor" + path, body=body, headers={"Authorization": f"Bearer {key}"}, use_cookies=False)


def inbound(from_addr, subject, text, token=TOKEN):
    """Sends a real RFC822 message, the same format the mail gateway forwards."""
    raw = (f"From: {from_addr}\r\n"
           f"To: support@itsm.governit.dk\r\n"
           f"Subject: {subject}\r\n"
           f"Message-ID: <t{time.time_ns()}@test>\r\n"
           f"Content-Type: text/plain; charset=UTF-8\r\n"
           f"\r\n{text}\r\n")
    hdr = {"Content-Type": "text/plain"}
    if token is not None:
        hdr["X-Inbound-Token"] = token
    return call("POST", "/email/receive", raw=raw, headers=hdr)


created_tickets, created_vendors = [], []

# ---------- setup ----------
s, b = call("POST", "/auth/login", {"email": ADMIN[0], "password": ADMIN[1]})
check("admin login", s == 200, str(b)[:200])

s, b = call("POST", "/vendors", {"customer_id": DEMOCORP, "name": "VTest API DemoCorp",
            "integration_mode": "api", "api_base_url": MOCK_API})
check("create API vendor (DemoCorp)", s == 201, str(b)[:300])
api_vendor = data(b)["id"]; created_vendors.append(api_vendor)

s, b = call("POST", "/vendors", {"customer_id": DEMOCORP, "name": "VTest Mail DemoCorp",
            "integration_mode": "email", "email": MAIL_VENDOR})
check("create mail vendor (DemoCorp)", s == 201, str(b)[:300])
mail_vendor = data(b)["id"]; created_vendors.append(mail_vendor)

s, b = call("POST", "/vendors", {"customer_id": TESTORG, "name": "VTest TestOrg",
            "integration_mode": "api", "api_base_url": MOCK_API})
check("create vendor for other tenant (TestOrg)", s == 201, str(b)[:300])
other_vendor = data(b)["id"]; created_vendors.append(other_vendor)

s, b = call("POST", "/vendors", {"customer_id": DEMOCORP, "name": "Bad SSRF",
            "integration_mode": "api", "api_base_url": "https://127.0.0.1/x"})
check("reject internal api_base_url (SSRF guard)", s == 422, f"{s} {str(b)[:200]}")

s, b = call("POST", f"/vendors/{api_vendor}/api-key")
check("issue API key", s == 201 and str(data(b).get("api_key", "")).startswith("vk_"), str(b)[:200])
key_api = data(b).get("api_key", "")
s, b = call("POST", f"/vendors/{other_vendor}/api-key")
key_other = data(b).get("api_key", "")

# ---------- ticket A: API path ----------
s, b = call("POST", "/tickets", {"type": "incident", "title": "VTest API ticket", "description": "x",
            "priority": "medium", "customer_id": DEMOCORP})
check("create incident (DemoCorp)", s in (200, 201), str(b)[:300])
ta = data(b)
tid_a, num_a = ta.get("id"), ta.get("ticket_number")
created_tickets.append(tid_a)

s, b = call("POST", f"/tickets/{tid_a}/vendor-request", {"vendor_id": api_vendor, "message": "Please look at this"})
check("send to API vendor -> 200", s == 200, str(b)[:300])
check("status becomes awaiting_vendor", data(b).get("status") == "awaiting_vendor", str(data(b).get("status")))
check("external_ref stored from vendor API", str(data(b).get("external_ref", "")).startswith("EXT-"), str(data(b).get("external_ref")))
check("vendor_id stored on ticket", data(b).get("vendor_id") == api_vendor)

s, b = call("POST", f"/tickets/{tid_a}/vendor-request", {"vendor_id": api_vendor, "message": "again"})
check("cannot resend while awaiting (any status allowed? must not crash)", s in (200, 422), f"{s}")

# ---------- vendor API ----------
s, b = vendor_key_call("GET", "/tickets", "vk_wrong")
check("invalid API key -> 401", s == 401, str(s))
s, b = call("GET", "/v1/vendor/tickets", base=BASE, use_cookies=False)
check("no bearer token -> 401", s == 401, str(s))

s, b = vendor_key_call("GET", "/tickets", key_api)
nums = [t.get("ticket_number") for t in (b.get("data") or [])]
check("vendor key lists its own ticket", num_a in nums, str(nums))

s, b = vendor_key_call("GET", "/tickets", key_other)
check("other tenant's vendor key does NOT see the ticket", num_a not in [t.get("ticket_number") for t in (b.get("data") or [])])

s, b = vendor_key_call("POST", f"/tickets/{num_a}/update", key_other, {"comment": "intrusion"})
check("other tenant's vendor key cannot update (404)", s == 404, str(s))

s, b = vendor_key_call("POST", f"/tickets/{num_a}/update", key_api, {"status": "resolved"})
check("resolve before in_progress is rejected (409)", s == 409, f"{s} {str(b)[:150]}")

s, b = vendor_key_call("POST", f"/tickets/{num_a}/update", key_api, {"comment": "We picked it up", "external_ref": "VEND-42"})
check("vendor comment moves awaiting_vendor -> in_progress", s == 200 and data(b).get("status") == "in_progress", str(b)[:200])

s, b = vendor_key_call("POST", f"/tickets/{num_a}/update", key_api, {"status": "resolved", "comment": "Fixed"})
check("vendor resolves from in_progress", s == 200 and data(b).get("status") == "resolved", str(b)[:200])

s, b = vendor_key_call("POST", f"/tickets/{num_a}/update", key_api, {"status": "closed"})
check("vendor cannot set closed (422 or 409)", s in (409, 422), str(s))

s, b = call("GET", f"/tickets/{tid_a}")
t = data(b)
check("ticket external_ref is the vendor's reference", t.get("external_ref") == "VEND-42", str(t.get("external_ref")))
vcomments = [c for c in (t.get("comments") or []) if c.get("source") == "vendor"]
check("vendor comment stored with source=vendor", len(vcomments) >= 1, str([c.get("source") for c in t.get("comments") or []]))
check("system note about the send is internal", any(c.get("source") == "system" and c.get("is_internal") in (1, True) for c in t.get("comments") or []))

s, b = call("POST", f"/tickets/{tid_a}/vendor-request", {"vendor_id": api_vendor, "message": "closed?"})
check("sending a resolved ticket is refused (422)", s == 422, str(s))

# ---------- ticket B: mail path + inbound ----------
s, b = call("POST", "/tickets", {"type": "change", "title": "VTest mail change", "description": "y",
            "priority": "high", "customer_id": DEMOCORP})
tb = data(b); tid_b, num_b = tb.get("id"), tb.get("ticket_number")
created_tickets.append(tid_b)
check("create change (DemoCorp)", tid_b is not None, str(b)[:200])

s, b = call("POST", f"/tickets/{tid_b}/vendor-request", {"email": MAIL_VENDOR, "message": "Please quote"})
check("send by free-text email -> awaiting_vendor", s == 200 and data(b).get("status") == "awaiting_vendor", f"{s} {str(b)[:200]}")

s, b = inbound(MAIL_VENDOR, f"Re: [{num_b}] VTest mail change", "x", token=None)
check("inbound without token -> 401", s == 401, str(s))
s, b = inbound(MAIL_VENDOR, f"Re: [{num_b}] VTest mail change", "x", token="wrong-token")
check("inbound with wrong token -> 401", s == 401, str(s))

s, b = inbound("stranger@example.com", f"Re: [{num_b}] spoof", "let me in")
s2, b2 = call("GET", f"/tickets/{tid_b}")
check("reply from unknown sender does NOT change status", data(b2).get("status") == "awaiting_vendor", str(data(b2).get("status")))

s, b = inbound(MAIL_VENDOR, f"Re: [{num_b}] VTest mail change", "Approved, we will start tomorrow.")
s2, b2 = call("GET", f"/tickets/{tid_b}")
t2 = data(b2)
check("vendor mail reply moves awaiting_vendor -> in_progress", t2.get("status") == "in_progress", str(t2.get("status")))
check("vendor mail reply stored as source=vendor", any(c.get("source") == "vendor" for c in t2.get("comments") or []))

# ---------- negative send rules ----------
s, b = call("POST", f"/tickets/{tid_b}/vendor-request", {"vendor_id": other_vendor, "message": "cross tenant"})
check("vendor of another customer is refused (422)", s == 422, str(s))

s, b = call("POST", f"/tickets/{tid_b}/vendor-request", {"message": "no target"})
check("missing vendor_id and email -> 422", s == 422, str(s))

s, b = call("POST", f"/tickets/{tid_b}/vendor-request", {"email": "not-an-email", "message": "x"})
check("invalid email -> 422", s == 422, str(s))

# ---------- API failure keeps the ticket unchanged ----------
s, b = call("POST", "/vendors", {"customer_id": DEMOCORP, "name": "VTest Dead API",
            "integration_mode": "api", "api_base_url": "https://vendor-unreachable.invalid/api"})
dead = data(b).get("id"); created_vendors.append(dead)
s, b = call("POST", "/tickets", {"type": "problem", "title": "VTest dead vendor", "description": "z",
            "priority": "low", "customer_id": DEMOCORP})
tid_c = data(b).get("id"); created_tickets.append(tid_c)
s, b = call("POST", f"/tickets/{tid_c}/vendor-request", {"vendor_id": dead, "message": "hello"})
check("unreachable API (mode api) -> 502", s == 502, f"{s} {str(b)[:150]}")
s, b = call("GET", f"/tickets/{tid_c}")
check("ticket unchanged after failed API send", data(b).get("status") == "new", str(data(b).get("status")))

# ---------- revoke ----------
s, b = call("DELETE", f"/vendors/{api_vendor}/api-key")
s, b = vendor_key_call("GET", "/tickets", key_api)
check("revoked key -> 401", s == 401, str(s))

# ---------- cleanup ----------
for tid in created_tickets:
    if tid:
        call("DELETE", f"/tickets/{tid}")
for vid in created_vendors:
    if vid:
        call("DELETE", f"/vendors/{vid}")
s, b = call("GET", f"/tickets/{tid_a}")
check("cleanup: test ticket removed", s == 404, str(s))

print()
print(f"{'ALL PASSED' if not failures else str(len(failures)) + ' FAILED'}")
sys.exit(1 if failures else 0)
