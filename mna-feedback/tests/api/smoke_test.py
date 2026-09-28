#!/usr/bin/env python3
"""
End-to-end API checks for MNA Feedback against a running WordPress site.

    MNAFB_BASE=http://localhost:8889 MNAFB_ADMIN_USER=admin MNAFB_ADMIN_PASS=... \
        python3 tests/api/smoke_test.py

Creates a share link, joins as two guest reviewers with the same name, and
exercises items, conflicts, permissions, replies, screenshots, Trash, sync,
exports and link revocation. Everything it creates is cleaned up at the end
(the share link is revoked and the test items are purged).
"""

import json
import os
import struct
import sys
import time
import zlib
import requests
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry


def resilient(session):
    """Retry only failures to connect (the request never reached the site), never reads or writes."""
    adapter = HTTPAdapter(max_retries=Retry(total=4, connect=4, read=0, status=0, other=0, backoff_factor=1, allowed_methods=None, raise_on_status=False))
    session.mount("https://", adapter)
    session.mount("http://", adapter)
    return session

BASE = os.environ.get("MNAFB_BASE", "http://localhost:8889").rstrip("/")
ADMIN_USER = os.environ.get("MNAFB_ADMIN_USER", "admin")
ADMIN_PASS = os.environ.get("MNAFB_ADMIN_PASS", "")
# Alternatively a one-time login URL (for example from Novamira's admin access link).
LOGIN_URL = os.environ.get("MNAFB_LOGIN_URL", "")
API = BASE + "/wp-json/mna-feedback/v1"
CLIENT = {"X-MNAFB-Client": "1"}

results = []


def check(name, condition, detail=""):
    results.append((name, bool(condition)))
    status = "PASS" if condition else "FAIL"
    print(f"[{status}] {name}" + (f" — {detail}" if detail and not condition else ""))
    return condition


def png_bytes(width=64, height=40):
    rows = b""
    for y in range(height):
        rows += b"\x00" + b"".join(bytes([(x * 4) % 256, (y * 6) % 256, 180]) for x in range(width))
    def chunk(kind, data):
        c = struct.pack(">I", len(data)) + kind + data
        return c + struct.pack(">I", zlib.crc32(kind + data) & 0xFFFFFFFF)
    return b"\x89PNG\r\n\x1a\n" + chunk(b"IHDR", struct.pack(">IIBBBBB", width, height, 8, 2, 0, 0, 0)) + chunk(b"IDAT", zlib.compress(rows)) + chunk(b"IEND", b"")


class Client:
    def __init__(self, label):
        self.label = label
        self.s = resilient(requests.Session())
        self.s.headers["User-Agent"] = f"mnafb-smoke/{label}"
        self.nonce = None
        self.csrf = None

    def headers(self, write=False):
        h = dict(CLIENT) if write else {}
        if self.nonce:
            h["X-WP-Nonce"] = self.nonce
        if self.csrf and write:
            h["X-MNAFB-Token"] = self.csrf
        return h

    def get(self, path, **kw):
        headers = {**self.headers(), **kw.pop("headers", {})}
        # Reads are safe to repeat: ride out a dropped connection or gateway hiccup.
        for attempt in range(3):
            r = self.s.get(API + path, headers=headers, allow_redirects=False, **kw)
            if r.status_code in (502, 503, 504) or (r.status_code == 200 and not r.content):
                time.sleep(1 + attempt)
                continue
            return r
        return r

    def send(self, method, path, body=None, **kw):
        headers = {**self.headers(True), **kw.pop("headers", {})}
        if body is not None:
            return self.s.request(method, API + path, json=body, headers=headers, allow_redirects=False, **kw)
        return self.s.request(method, API + path, headers=headers, allow_redirects=False, **kw)


def login(client):
    if LOGIN_URL:
        r = client.s.get(LOGIN_URL, allow_redirects=True)
        return r.ok and any(c.name.startswith("wordpress_logged_in") for c in client.s.cookies)
    client.s.get(BASE + "/wp-login.php")
    r = client.s.post(
        BASE + "/wp-login.php",
        data={"log": ADMIN_USER, "pwd": ADMIN_PASS, "wp-submit": "Log In", "testcookie": "1", "redirect_to": BASE + "/wp-admin/"},
        allow_redirects=False,
    )
    return r.status_code in (302, 303)


def main():
    if not ADMIN_PASS and not LOGIN_URL:
        print("Set MNAFB_ADMIN_PASS or MNAFB_LOGIN_URL")
        return 2

    admin = Client("admin")
    check("Admin can sign in to WordPress", login(admin))

    r = admin.get("/session")
    check("Signed-in admin without nonce is told to fetch a nonce", r.status_code == 200 and r.json().get("authenticated") is False and r.json().get("wp_login") is True, r.text[:200])

    r = admin.get("/session/nonce")
    check("Nonce endpoint requires the client header", r.status_code == 400)
    r = admin.get("/session/nonce", headers=CLIENT)
    check("Admin gets a REST nonce from /session/nonce", r.status_code == 200 and r.json().get("nonce"), r.text[:200])
    admin.nonce = r.json().get("nonce")

    r = admin.get("/session", params={"url": BASE + "/sample-page/?utm_source=x"})
    me = r.json() if r.ok else {}
    check("Admin session reports manager role", r.ok and me.get("me", {}).get("role") == "manager", r.text[:300])
    check("Page URL is normalised (tracking parameters dropped)", me.get("page", {}).get("url", "").endswith("/sample-page/"), json.dumps(me.get("page")))

    r = admin.send("POST", "/admin/links", {"label": "Smoke test link", "expires_at": ""})
    link = r.json() if r.status_code == 201 else {}
    check("Manager creates a share link", r.status_code == 201 and "mna-review=" in (link.get("url") or ""), r.text[:300])
    link_url = link.get("url", "")

    # --- Guest A joins ---------------------------------------------------------
    a = Client("guest-a")
    r = a.s.get(link_url, allow_redirects=False)
    loc = r.headers.get("Location", "")
    check("Opening the share link redirects and strips the token", r.status_code == 302 and "mna-review" not in loc, f"{r.status_code} {loc}")
    check("Share-link response is not cacheable", "no-store" in r.headers.get("Cache-Control", "") or "no-cache" in r.headers.get("Cache-Control", ""), r.headers.get("Cache-Control", ""))
    check("Pending cookie is HttpOnly", "mnafb_p=" in r.headers.get("Set-Cookie", "") and "httponly" in r.headers.get("Set-Cookie", "").lower())
    check("Review flag cookie set", a.s.cookies.get("mnafb_on") == "1")

    r = a.get("/session")
    body = r.json()
    check("Before joining, /session asks for a name", r.status_code == 200 and body.get("authenticated") is False and body.get("join", {}).get("state") == "required", r.text[:300])

    r = a.send("POST", "/session", {"name": "Sam"}, headers={"X-MNAFB-Client": ""})
    check("Join without the client header is refused", r.status_code == 400, r.text[:200])

    r = a.send("POST", "/session", {"name": "Sam", "email": "sam@example.com", "url": BASE + "/sample-page/"})
    joined = r.json() if r.status_code == 201 else {}
    check("Guest A joins as 'Sam'", r.status_code == 201 and joined.get("me", {}).get("name") == "Sam", r.text[:300])
    check("Join returns a private return link", "mna-return=" in (joined.get("return_link") or ""))
    check("Session cookie is HttpOnly", "httponly" in r.headers.get("Set-Cookie", "").lower())
    a.csrf = joined.get("csrf")
    a_id = joined.get("me", {}).get("id")

    # --- Guest B joins with the same name --------------------------------------
    b = Client("guest-b")
    b.s.get(link_url, allow_redirects=False)
    r = b.send("POST", "/session", {"name": "Sam"})
    b_joined = r.json() if r.status_code == 201 else {}
    b.csrf = b_joined.get("csrf")
    check("Guest B also joins as 'Sam' with a distinct identity", r.status_code == 201 and b_joined.get("me", {}).get("id") != a_id, r.text[:200])

    # --- Items -----------------------------------------------------------------
    page_url = BASE + "/sample-page/"
    item_body = {
        "title": "Make the heading bigger",
        "body": "The hero heading is hard to read on mobile.",
        "priority": "high",
        "page_url": page_url,
        "page_title": "Sample Page",
        "pin": {"type": "element", "x": 0.25, "y": 0.5},
        "anchor": {"v": 1, "css": "main h1", "tag": "h1", "text": "Sample Page"},
        "viewport": {"w": 390, "h": 844},
    }
    r = a.send("POST", "/items", item_body, headers={"X-MNAFB-Token": "wrong"})
    check("Guest write with a wrong CSRF token is refused", r.status_code == 403, r.text[:200])

    r = a.send("POST", "/items", item_body)
    item = r.json() if r.status_code == 201 else {}
    check("Guest A creates an element-pinned item", r.status_code == 201 and item.get("pin", {}).get("type") == "element", r.text[:300])
    item_id = item.get("id")
    check("Author can edit and delete their own item", item.get("can", {}).get("edit") and item.get("can", {}).get("delete"))
    check("Reviewer cannot move an open item", item.get("can", {}).get("statuses") == [])

    r = a.send("POST", "/items", {**item_body, "title": "<script>alert(1)</script>Tidy footer", "pin": {"type": "page"}, "anchor": None})
    page_item = r.json() if r.status_code == 201 else {}
    check("Page-level comment is accepted and markup is stripped", r.status_code == 201 and "<script>" not in page_item.get("title", ""), r.text[:300])

    r = b.get("/items", params={"url": page_url})
    ids = [i["id"] for i in r.json().get("items", [])] if r.ok else []
    check("Guest B sees A's feedback on the page", item_id in ids)
    b_view = next((i for i in r.json().get("items", []) if i["id"] == item_id), {}) if r.ok else {}
    check("Guest B cannot edit A's item (can.edit false)", b_view.get("can", {}).get("edit") is False)

    r = b.send("PATCH", f"/items/{item_id}", {"title": "Hijacked"})
    check("Guest B's edit of A's item is refused", r.status_code == 403, r.text[:200])
    r = b.send("DELETE", f"/items/{item_id}")
    check("Guest B's delete of A's item is refused", r.status_code == 403, r.text[:200])

    # --- Conflicts -------------------------------------------------------------
    r = a.send("PATCH", f"/items/{item_id}", {"title": "Make the hero heading bigger", "expected": {"title": "Make the heading bigger"}})
    check("Edit with matching expected value succeeds", r.ok and r.json().get("title") == "Make the hero heading bigger", r.text[:200])
    r = a.send("PATCH", f"/items/{item_id}", {"title": "Stale edit", "expected": {"title": "Make the heading bigger"}})
    conflict = r.json()
    check("Stale edit returns 409 with the current version", r.status_code == 409 and conflict.get("code") == "mnafb_conflict" and conflict.get("data", {}).get("current", {}).get("title") == "Make the hero heading bigger", r.text[:300])

    r = admin.send("PATCH", f"/items/{item_id}", {"priority": "urgent", "expected": {"priority": "high"}})
    check("Priority change by the team does not conflict with a title edit", r.ok and r.json().get("priority") == "urgent", r.text[:200])

    # --- Workflow --------------------------------------------------------------
    r = a.send("PATCH", f"/items/{item_id}", {"status": "done"})
    check("Reviewer cannot mark an item done", r.status_code == 403)
    r = admin.get("/people")
    team_ids = r.json().get("assignable", []) if r.ok else []
    check("People endpoint lists assignable team members", len(team_ids) >= 1, r.text[:200])
    r = admin.send("PATCH", f"/items/{item_id}", {"status": "in_progress", "assignee_id": team_ids[0] if team_ids else 0})
    check("Implementer moves the item to In progress and assigns it", r.ok and r.json().get("status") == "in_progress" and r.json().get("assignee"), r.text[:200])
    r = admin.send("PATCH", f"/items/{item_id}", {"status": "done"})
    check("Implementer marks it Done", r.ok and r.json().get("status") == "done")
    r = a.get(f"/items/{item_id}")
    check("Reviewer may now reopen (can.statuses == ['open'])", r.ok and r.json().get("can", {}).get("statuses") == ["open"], r.text[:200])
    r = a.send("PATCH", f"/items/{item_id}", {"status": "open"})
    check("Reviewer reopens the item", r.ok and r.json().get("status") == "open", r.text[:200])

    # --- Replies and unread -----------------------------------------------------
    r = b.send("POST", f"/items/{item_id}/replies", {"body": "Agreed — also on tablet."})
    reply = r.json().get("reply", {}) if r.status_code == 201 else {}
    check("Guest B replies", r.status_code == 201 and reply.get("body", "").startswith("Agreed"), r.text[:200])
    r = a.get("/items", params={"url": page_url})
    a_view = next((i for i in r.json().get("items", []) if i["id"] == item_id), {}) if r.ok else {}
    check("Guest A sees the item as unread after B's reply", a_view.get("unread") is True, json.dumps(a_view)[:200])
    a.send("POST", f"/items/{item_id}/read")
    r = a.get("/items", params={"url": page_url})
    a_view = next((i for i in r.json().get("items", []) if i["id"] == item_id), {}) if r.ok else {}
    check("Marking read clears the unread flag", a_view.get("unread") is False)
    r = a.send("PATCH", f"/replies/{reply.get('id')}", {"body": "Changed"})
    check("Guest A cannot edit B's reply", r.status_code == 403)
    r = b.send("PATCH", f"/replies/{reply.get('id')}", {"body": "Agreed — tablet and phone.", "expected_body": "Agreed — also on tablet."})
    check("Guest B edits their reply", r.ok and r.json().get("edited_at"))
    r = admin.send("POST", f"/items/{item_id}/replies", {"body": "Fixed in the theme styles.", "kind": "note"})
    check("Implementer adds an implementation note", r.status_code == 201 and r.json().get("reply", {}).get("kind") == "note")
    r = b.send("POST", f"/items/{item_id}/replies", {"body": "Trying a note", "kind": "note"})
    check("Reviewer 'note' is downgraded to a reply", r.status_code == 201 and r.json().get("reply", {}).get("kind") == "reply")

    r = a.get(f"/items/{item_id}")
    detail = r.json() if r.ok else {}
    actions = [x["action"] for x in detail.get("activity", [])]
    check("History records edits, status, priority, assignment and replies", all(x in actions for x in ["created", "edited", "priority", "status", "assigned", "replied", "note_added", "reply_edited"]), ",".join(actions))

    # --- Screenshots -----------------------------------------------------------
    png = png_bytes()
    r = a.s.post(API + f"/items/{item_id}/attachments", headers=a.headers(True), files={"file": ("shot.png", png, "image/png")})
    att = r.json() if r.status_code == 201 else {}
    check("Guest uploads a PNG screenshot", r.status_code == 201 and att.get("mime") == "image/png", r.text[:300])
    r = a.s.post(API + f"/items/{item_id}/attachments", headers=a.headers(True), files={"file": ("evil.png", b"<?php echo 1; ?>", "image/png")})
    check("Non-image upload is rejected", r.status_code in (400, 415), r.text[:200])
    r = a.s.get(API + f"/attachments/{att.get('id')}", headers=a.headers())
    check("Reviewer can fetch the screenshot through the API", r.status_code == 200 and r.headers.get("Content-Type", "").startswith("image/"), f"{r.status_code} {r.headers.get('Content-Type')}")
    check("Screenshot response is private and not cacheable", "no-store" in r.headers.get("Cache-Control", ""), r.headers.get("Cache-Control", ""))
    r = requests.get(API + f"/attachments/{att.get('id')}")
    check("Public visitor cannot fetch the screenshot", r.status_code == 401, str(r.status_code))
    r = requests.get(API + "/items")
    check("Public visitor cannot list feedback", r.status_code == 401)
    r = requests.get(BASE + "/wp-content/uploads/")
    storage_listing = r.status_code != 200 or "mna-feedback-" not in r.text
    check("Uploads directory does not list the private storage folder", storage_listing)

    # --- Cross-site protections ------------------------------------------------
    r = a.get("/items", headers={"Origin": "https://evil.example"})
    check("Foreign Origin is refused", r.status_code == 403)
    r = a.get("/items", headers={"Sec-Fetch-Site": "cross-site"})
    check("Cross-site fetch metadata is refused", r.status_code == 403)
    r = a.get("/items", params={"_jsonp": "steal"})
    check("JSONP is refused", r.status_code in (400, 403))
    r = a.s.post(API + "/items", json=item_body, headers={"X-MNAFB-Token": a.csrf or ""})
    check("Write without X-MNAFB-Client header is refused", r.status_code == 400)
    r = a.get("/admin/links")
    check("Guest cannot use manager endpoints", r.status_code == 403)

    # --- Sync ------------------------------------------------------------------
    since = time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime(time.time() - 60))
    r = b.get("/sync", params={"since": since})
    synced = [i["id"] for i in r.json().get("items", [])] if r.ok else []
    check("Sync returns recently changed items", item_id in synced, r.text[:200])

    # --- Trash -----------------------------------------------------------------
    r = a.send("DELETE", f"/items/{page_item.get('id')}")
    check("Author moves their item to Trash", r.ok and r.json().get("deleted") is True, r.text[:200])
    r = b.get("/sync", params={"since": since})
    check("Trashed item is reported as removed to reviewers", page_item.get("id") in (r.json().get("removed", []) if r.ok else []))
    r = b.get(f"/items/{page_item.get('id')}")
    check("Reviewer cannot open a trashed item", r.status_code == 404)
    r = admin.get("/items", params={"trashed": "true"})
    check("Manager sees the trashed item", r.ok and page_item.get("id") in [i["id"] for i in r.json().get("items", [])])
    r = a.send("POST", f"/items/{page_item.get('id')}/restore")
    check("Reviewer cannot restore from Trash", r.status_code == 403)
    r = admin.send("POST", f"/items/{page_item.get('id')}/restore")
    check("Manager restores from Trash", r.ok and r.json().get("trashed") is False)

    # --- Profile and return link -------------------------------------------------
    r = a.send("PATCH", "/session", {"name": "Sam Taylor"})
    check("Guest renames themselves", r.ok and r.json().get("me", {}).get("name") == "Sam Taylor")
    r = a.get("/session/return-link")
    return_url = r.json().get("url", "") if r.ok else ""
    check("Guest can retrieve their private return link", "mna-return=" in return_url)
    c = Client("guest-a-second-device")
    r = c.s.get(return_url, allow_redirects=False)
    check("Return link starts a session on another device", r.status_code == 302 and c.s.cookies.get("mnafb_s"))
    r = c.get("/session")
    check("Second device resumes the same identity", r.ok and r.json().get("authenticated") is True and r.json().get("me", {}).get("id") == a_id, r.text[:200])

    # --- Export ----------------------------------------------------------------
    r = admin.get("/admin/export", params={"format": "csv"})
    check("Manager exports CSV", r.ok and r.headers.get("Content-Type", "").startswith("text/csv") and "Make the hero heading bigger" in r.text, r.headers.get("Content-Type", ""))
    r = admin.get("/admin/export", params={"format": "json"})
    check("Manager exports JSON with replies and history", r.ok and any(i.get("replies") and i.get("history") for i in r.json().get("items", [])))

    # --- Revocation --------------------------------------------------------------
    r = admin.send("POST", f"/admin/links/{link.get('id')}/revoke")
    check("Manager revokes the link", r.ok and r.json().get("status") == "revoked")
    r = a.get("/session")
    check("Revoked link ends guest A's session", r.json().get("authenticated") is False and r.json().get("ended") is True, r.text[:200])
    r = c.get("/items")
    check("Revoked link ends the second device's session too", r.status_code == 401)
    d = Client("late-guest")
    d.s.get(link_url, allow_redirects=False)
    r = d.get("/session")
    check("Opening a revoked link explains it has been switched off", r.json().get("authenticated") is False and r.json().get("join", {}).get("reason") == "revoked", r.text[:200])

    # --- Clean up ----------------------------------------------------------------
    for iid in [item_id, page_item.get("id")]:
        admin.send("DELETE", f"/items/{iid}")
        admin.send("DELETE", f"/items/{iid}", params={"force": "true"})
    r = admin.get(f"/items/{item_id}")
    check("Manager purges test items permanently", r.status_code == 404)

    passed = sum(1 for _, ok in results if ok)
    print(f"\n{passed}/{len(results)} checks passed")
    return 0 if passed == len(results) else 1


if __name__ == "__main__":
    sys.exit(main())
