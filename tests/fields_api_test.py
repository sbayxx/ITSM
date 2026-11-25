#!/usr/bin/env python3
"""Fields-aware request/response test (AGENTS Next Step 6).

Covers: catalog item exposes `fields`, a service request can be created with
dynamic-field answers, the detail response carries both `catalog_item.fields`
and the stored `type_data.fulfillment_data`, and cleanup removes the ticket.

Usage: python3 tests/fields_api_test.py   (stdlib only, run from repo root)
"""
import json
import sys
import urllib.request
import urllib.error
import http.cookiejar

BASE = "https://itsm.governit.dk/api"
ADMIN = ("sb@governit.dk", "Skyservice20!")
CATALOG_ID = 4  # Hardware Request (requires_approval=1, has fields)
ANSWERS = {
    "device_type": "Laptop",
    "justification": "fields-api-test: replacement for broken laptop",
    "needed_by": "2026-10-15",
}

jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))


def call(method, path, body=None):
    data = json.dumps(body).encode() if body is not None else None
    req = urllib.request.Request(
        BASE + path, data=data, method=method,
        headers={"Content-Type": "application/json", "Accept": "application/json"},
    )
    try:
        with opener.open(req, timeout=30) as resp:
            return resp.status, json.loads(resp.read().decode() or "{}")
    except urllib.error.HTTPError as e:
        raw = e.read().decode()
        try:
            return e.code, json.loads(raw)
        except json.JSONDecodeError:
            return e.code, {"raw": raw}


failures = []


def check(label, cond, detail=""):
    status = "PASS" if cond else "FAIL"
    print(f"[{status}] {label}" + (f" — {detail}" if detail and not cond else ""))
    if not cond:
        failures.append(label)


def as_list(v):
    if isinstance(v, list):
        return v
    if isinstance(v, str):
        try:
            parsed = json.loads(v)
            return parsed if isinstance(parsed, list) else []
        except json.JSONDecodeError:
            return []
    return []


# 1. login
status, body = call("POST", "/auth/login", {"email": ADMIN[0], "password": ADMIN[1]})
check("login", status == 200, f"status={status}")

# 2. catalog item exposes usable field definitions
status, body = call("GET", "/service-catalog")
items = body.get("data") or []
item = next((i for i in items if i.get("id") == CATALOG_ID), None)
check("catalog list 200", status == 200, f"status={status}")
check("catalog item 4 present", item is not None)
fields = as_list(item.get("fields")) if item else []
check("item 4 has fields array", len(fields) >= 2, f"raw={item.get('fields') if item else None}")
check("fields have name+label+type",
      all(all(k in f for k in ("name", "label", "type")) for f in fields) if fields else False)

# 3. create a service request with dynamic answers
status, body = call("POST", "/tickets", {
    "type": "service_request",
    "title": "FIELDS-API-TEST dynamic field round trip",
    "description": "Automated fields-aware API test (tests/fields_api_test.py)",
    "service_catalog_item_id": CATALOG_ID,
    "type_data": {"dynamic_fields": ANSWERS},
})
check("create request 201", status == 201, f"status={status} body={body.get('message')}")
ticket = body.get("data") or {}
ticket_id = ticket.get("id")

# 4. detail response carries catalog_item.fields and the stored answers
if ticket_id:
    status, body = call("GET", f"/tickets/{ticket_id}")
    detail = body.get("data") or {}
    check("detail 200", status == 200)
    cat = detail.get("catalog_item")
    check("catalog_item attached", isinstance(cat, dict) and cat.get("id") == CATALOG_ID,
          f"catalog_item={cat}")
    check("catalog_item.fields present", len(as_list((cat or {}).get("fields"))) >= 2)
    stored = detail.get("type_data", {})
    stored = stored.get("fulfillment_data") if isinstance(stored, dict) else None
    stored = stored if isinstance(stored, dict) else (json.loads(stored) if isinstance(stored, str) and stored else {})
    check("answers stored in fulfillment_data",
          all(stored.get(k) == v for k, v in ANSWERS.items()),
          f"stored={stored}")

    # 5. cleanup
    status, body = call("DELETE", f"/tickets/{ticket_id}")
    check("cleanup delete 2xx", 200 <= status < 300, f"status={status}")
    status, body = call("GET", f"/tickets/{ticket_id}")
    check("ticket gone after delete", status == 404, f"status={status}")
else:
    check("ticket_id exists", False)

print("-" * 40)
if failures:
    print(f"FAILED: {len(failures)}: {failures}")
    sys.exit(1)
print("ALL FIELDS TESTS PASSED")
