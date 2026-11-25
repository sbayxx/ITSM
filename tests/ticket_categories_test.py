#!/usr/bin/env python3
"""Ticket category tree: endpoint shape, category/subcategory validation on create and update,
legacy free-text values stay editable, cleanup.

Usage: python3 tests/ticket_categories_test.py   (stdlib only; clear rate-limiter locks first)
"""
import json, sys, urllib.request, urllib.error, http.cookiejar

BASE = "https://itsm.governit.dk/api"
ADMIN = ("sb@governit.dk", "Skyservice20!")
DEMOCORP = 2
jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
failures = []


def call(method, path, body=None):
    data = json.dumps(body).encode() if body is not None else None
    req = urllib.request.Request(BASE + path, data=data, method=method,
                                 headers={"Content-Type": "application/json", "Accept": "application/json"})
    try:
        with opener.open(req, timeout=60) as r:
            return r.status, json.loads(r.read().decode() or "{}")
    except urllib.error.HTTPError as e:
        raw = e.read().decode()
        try:
            return e.code, json.loads(raw)
        except json.JSONDecodeError:
            return e.code, {"raw": raw}


def check(label, cond, detail=""):
    print(("[PASS] " if cond else "[FAIL] ") + label + ("" if cond or not detail else f" -- {detail}"))
    if not cond:
        failures.append(label)


s, b = call("POST", "/auth/login", {"email": ADMIN[0], "password": ADMIN[1]})
check("admin login", s == 200, str(b)[:150])

s, b = call("GET", "/ticket-categories?type=incident")
inc = b.get("data") or []
check("incident tree has 9 categories", s == 200 and len(inc) == 9, f"{s} {len(inc)}")
hw = next((c for c in inc if c["name"] == "Hardware"), None)
check("Hardware has subcategories incl. Server", hw is not None and any(x["name"] == "Server" for x in hw["children"]))
check("no 'Other' bucket in incident tree", not any("other" in c["name"].lower() for c in inc))

s, b = call("GET", "/ticket-categories?type=service_request")
check("service request tree is separate", s == 200 and any(c["name"] == "Hardware request" for c in b.get("data") or []))
s, b = call("GET", "/ticket-categories?type=change")
check("change tree has 6 categories", s == 200 and len(b.get("data") or []) == 6, str(len(b.get("data") or [])))
s, b = call("GET", "/ticket-categories?type=bogus")
check("unknown type -> 422", s == 422, str(s))
s, b = call("GET", "/ticket-categories?type=problem")
check("problem uses incident tree", s == 200 and len(b.get("data") or []) == 9)

created = []
s, b = call("POST", "/tickets", {"type": "incident", "title": "VTest category", "description": "x",
            "priority": "medium", "customer_id": DEMOCORP, "category": "Hardware", "subcategory": "Server"})
check("create with valid pair", s in (200, 201), f"{s} {str(b)[:200]}")
tid = (b.get("data") or {}).get("id")
created.append(tid)
check("stored category/subcategory", (b.get("data") or {}).get("category") == "Hardware"
      and (b.get("data") or {}).get("subcategory") == "Server")

s, b = call("POST", "/tickets", {"type": "incident", "title": "VTest bad", "description": "x",
            "priority": "medium", "customer_id": DEMOCORP, "category": "Other"})
check("create with 'Other' rejected (422)", s == 422, str(s))
s, b = call("POST", "/tickets", {"type": "incident", "title": "VTest mismatch", "description": "x",
            "priority": "medium", "customer_id": DEMOCORP, "category": "Network", "subcategory": "Server"})
check("subcategory from another category rejected (422)", s == 422, str(s))
s, b = call("POST", "/tickets", {"type": "change", "title": "VTest change", "description": "x",
            "priority": "medium", "customer_id": DEMOCORP, "category": "Hardware"})
check("incident-only category rejected for change (422)", s == 422, str(s))

if tid:
    s, b = call("PUT", f"/tickets/{tid}", {"category": "Network", "subcategory": "Wireless (Wi-Fi)"})
    check("update to valid pair", s == 200, f"{s} {str(b)[:150]}")
    s, b = call("PUT", f"/tickets/{tid}", {"category": "Network", "subcategory": "Server"})
    check("update with mismatched subcategory rejected (422)", s == 422, str(s))
    s, b = call("PUT", f"/tickets/{tid}", {"title": "VTest category renamed"})
    check("unrelated edit with untouched category succeeds", s == 200, str(s))

# legacy free-text value already stored on an existing ticket must not block unrelated edits.
# Pick an existing ticket whose category is not in the tree (seeded demo data uses free text).
s, b = call("GET", "/tickets?customer_id=%d" % DEMOCORP)
legacy = None
for t in (b.get("data") or []):
    if t.get("category") and t.get("type") == "incident":
        legacy = t
        break
if legacy:
    s, b = call("PUT", f"/tickets/{legacy['id']}", {"title": legacy["title"] + " "})
    check("legacy category kept, unrelated edit succeeds", s == 200, f"{s} {str(b)[:150]}")
    call("PUT", f"/tickets/{legacy['id']}", {"title": legacy["title"]})  # restore title
else:
    check("found an existing ticket with a legacy category", False, "none in demo data")

for t in created:
    if t:
        call("DELETE", f"/tickets/{t}")
print()
print("ALL PASSED" if not failures else f"{len(failures)} FAILED")
sys.exit(1 if failures else 0)
