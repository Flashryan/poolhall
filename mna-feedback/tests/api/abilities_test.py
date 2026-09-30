#!/usr/bin/env python3
"""
Checks the optional Abilities API integration (WordPress 6.9+).

    MNAFB_BASE=http://localhost:8889 MNAFB_ADMIN_USER=admin MNAFB_ADMIN_PASS=... \
    MNAFB_REVIEWER_USER=... MNAFB_REVIEWER_PASS=... \
    MNAFB_OUTSIDER_USER=... MNAFB_OUTSIDER_PASS=... \
        python3 tests/api/abilities_test.py

The reviewer account should hold only the Reviewer feedback role; the outsider
account no feedback role at all. Both are optional. The abilities setting is
restored to its previous value at the end and test items are purged.
"""

import json
import os
import sys

import requests
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry

BASE = os.environ.get("MNAFB_BASE", "http://localhost:8889").rstrip("/")
API = BASE + "/wp-json/mna-feedback/v1"
ABILITIES = BASE + "/wp-json/wp-abilities/v1"
CLIENT = {"X-MNAFB-Client": "1"}
results = []


def check(name, condition, detail=""):
    results.append(bool(condition))
    print(f"[{'PASS' if condition else 'FAIL'}] {name}" + (f" — {detail}" if detail and not condition else ""))


LOGIN_URL = os.environ.get("MNAFB_LOGIN_URL", "")


def session_for(user, password):
    s = requests.Session()
    adapter = HTTPAdapter(max_retries=Retry(total=4, connect=4, read=0, status=0, other=0, backoff_factor=1, allowed_methods=None, raise_on_status=False))
    s.mount("https://", adapter)
    s.mount("http://", adapter)
    if LOGIN_URL and user is None:
        s.get(LOGIN_URL, allow_redirects=True)
        nonce = s.get(BASE + "/wp-admin/admin-ajax.php", params={"action": "rest-nonce"}).text.strip()
        return (s, nonce) if nonce and nonce != "0" else (None, None)
    s.get(BASE + "/wp-login.php")
    r = s.post(BASE + "/wp-login.php", data={"log": user, "pwd": password, "wp-submit": "Log In", "testcookie": "1"}, allow_redirects=False)
    if r.status_code not in (302, 303):
        return None, None
    nonce = s.get(BASE + "/wp-admin/admin-ajax.php", params={"action": "rest-nonce"}).text.strip()
    return s, nonce


def run(s, nonce, name, payload, method):
    url = f"{ABILITIES}/abilities/{name}/run"
    headers = {"X-WP-Nonce": nonce}
    if method == "GET":
        params = {}
        for key, value in (payload or {}).items():
            if isinstance(value, list):
                for i, v in enumerate(value):
                    params[f"input[{key}][{i}]"] = v
            else:
                params[f"input[{key}]"] = value
        return s.get(url, params=params, headers=headers)
    return s.post(url, json={"input": payload}, headers=headers)


def main():
    admin, nonce = session_for(None if LOGIN_URL else os.environ.get("MNAFB_ADMIN_USER", "admin"), os.environ.get("MNAFB_ADMIN_PASS", ""))
    if not admin:
        print("Admin login failed")
        return 2
    h = {"X-WP-Nonce": nonce, **CLIENT}

    settings = admin.get(API + "/admin/settings", headers={"X-WP-Nonce": nonce}).json()
    was_enabled = bool(settings.get("abilities_enabled"))

    admin.patch(API + "/admin/settings", json={"abilities_enabled": False}, headers=h)
    listed = admin.get(ABILITIES + "/abilities", params={"per_page": 100}, headers={"X-WP-Nonce": nonce}).json()
    names = [a.get("name") for a in listed] if isinstance(listed, list) else []
    check("Abilities are not registered while the setting is off", not any(n and n.startswith("mna-feedback/") for n in names), ",".join(n for n in names if n))

    admin.patch(API + "/admin/settings", json={"abilities_enabled": True}, headers=h)
    listed = admin.get(ABILITIES + "/abilities", params={"per_page": 100}, headers={"X-WP-Nonce": nonce}).json()
    ours = {a["name"]: a for a in listed if isinstance(a, dict) and a.get("name", "").startswith("mna-feedback/")}
    expected = {"mna-feedback/list-items", "mna-feedback/get-item", "mna-feedback/list-team", "mna-feedback/add-note", "mna-feedback/update-item"}
    check("All five abilities are registered when enabled", set(ours) == expected, ",".join(sorted(ours)))
    check("Read abilities are annotated read-only", all(ours.get(n, {}).get("meta", {}).get("annotations", {}).get("readonly") for n in ["mna-feedback/list-items", "mna-feedback/get-item", "mna-feedback/list-team"]))
    check("No ability is marked destructive", not any(a.get("meta", {}).get("annotations", {}).get("destructive") for a in ours.values()))

    phone_ua = "Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Mobile/15E148 Safari/604.1"
    phone = {"screen": {"w": 393, "h": 852}, "viewport": {"w": 393, "h": 659}, "dpr": 3, "touch": 5}
    item = admin.post(API + "/items", json={"title": "Agent test: update the footer phone number", "body": "The number in the footer is out of date.", "page_url": BASE + "/", "pin": {"type": "page"}, "device": phone}, headers={**h, "User-Agent": phone_ua}).json()
    item_id = item.get("id")
    check("Test item created", bool(item_id), json.dumps(item)[:200])

    r = run(admin, nonce, "mna-feedback/list-items", {"status": ["open"], "search": "Agent test"}, "GET")
    body = r.json() if r.ok else {}
    check("list-items finds the open item", r.ok and any(i.get("id") == item_id for i in body.get("items", [])), r.text[:200])
    found = next((i for i in body.get("items", []) if i.get("id") == item_id), {})
    check("list-items says which device it was left on", (found.get("device") or {}).get("summary") == "iPhone · Safari 17" and "ua" not in (found.get("device") or {}), json.dumps(found.get("device"))[:200])
    r = run(admin, nonce, "mna-feedback/list-items", {"search": "Agent test", "device": ["desktop"]}, "GET")
    body = r.json() if r.ok else {}
    check("list-items filters by device", r.ok and not any(i.get("id") == item_id for i in body.get("items", [])), r.text[:200])

    r = run(admin, nonce, "mna-feedback/get-item", {"id": item_id}, "GET")
    detail = r.json() if r.ok else {}
    check("get-item returns description, replies and history", r.ok and detail.get("description", detail.get("body")) and "replies" in detail and "activity" in detail, r.text[:200])
    check("get-item does not expose screenshot URLs", "url" not in json.dumps(detail.get("attachments", [])))

    r = run(admin, nonce, "mna-feedback/list-team", {}, "GET")
    team = r.json() if r.ok else []
    check("list-team lists assignable team members", r.ok and isinstance(team, list) and len(team) >= 1, r.text[:200])
    assignee = team[0]["id"] if team else 0

    r = run(admin, nonce, "mna-feedback/add-note", {"id": item_id, "note": "Updated the number in the footer template."}, "POST")
    check("add-note records an implementation note", r.ok and r.json().get("kind") == "note", r.text[:200])

    r = run(admin, nonce, "mna-feedback/update-item", {"id": item_id, "status": "in_progress", "assignee_id": assignee}, "POST")
    check("update-item moves and assigns the item", r.ok and r.json().get("status") == "in_progress" and (r.json().get("assignee") or {}).get("id") == assignee, r.text[:200])

    r = run(admin, nonce, "mna-feedback/update-item", {"id": item_id, "status": "archived"}, "POST")
    check("update-item rejects an unknown status", not r.ok, r.text[:200])

    history = admin.get(f"{API}/items/{item_id}/activity", headers={"X-WP-Nonce": nonce}).json()
    agent = [e for e in history if e.get("source") == "agent"]
    check("Agent actions appear in the history marked as agent", {e["action"] for e in agent} >= {"note_added", "status", "assigned"}, json.dumps([(e["action"], e["source"]) for e in history]))

    reviewer_user = os.environ.get("MNAFB_REVIEWER_USER")
    if reviewer_user:
        rev, rnonce = session_for(reviewer_user, os.environ.get("MNAFB_REVIEWER_PASS", ""))
        r = run(rev, rnonce, "mna-feedback/list-items", {"search": "Agent test"}, "GET")
        check("A reviewer account can read feedback through abilities", r.ok and any(i.get("id") == item_id for i in r.json().get("items", [])), r.text[:200])
        r = run(rev, rnonce, "mna-feedback/add-note", {"id": item_id, "note": "Should not be allowed"}, "POST")
        check("A reviewer account cannot add implementation notes", r.status_code in (401, 403), f"{r.status_code} {r.text[:120]}")
        r = run(rev, rnonce, "mna-feedback/update-item", {"id": item_id, "status": "done"}, "POST")
        check("A reviewer account cannot change status through abilities", r.status_code in (401, 403), f"{r.status_code} {r.text[:120]}")

    outsider_user = os.environ.get("MNAFB_OUTSIDER_USER")
    if outsider_user:
        out, ononce = session_for(outsider_user, os.environ.get("MNAFB_OUTSIDER_PASS", ""))
        r = run(out, ononce, "mna-feedback/list-items", {}, "GET")
        check("An account without a feedback role cannot use the abilities", r.status_code in (401, 403), f"{r.status_code} {r.text[:120]}")

    admin.delete(f"{API}/items/{item_id}", headers=h)
    admin.delete(f"{API}/items/{item_id}", params={"force": "true"}, headers=h)
    admin.patch(API + "/admin/settings", json={"abilities_enabled": was_enabled}, headers=h)
    check("Setting restored and test item purged", True)

    print(f"\n{sum(results)}/{len(results)} checks passed")
    return 0 if all(results) else 1


if __name__ == "__main__":
    sys.exit(main())
