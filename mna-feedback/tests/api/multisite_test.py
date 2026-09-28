#!/usr/bin/env python3
"""
Multisite checks for MNA Feedback: each site on a network keeps its own
records, share links, guest sessions and cookies.

Run against a sub-directory network where the plugin is network-active and a
super admin can sign in:

    MNAFB_BASE=http://localhost MNAFB_SITES=/,/second/ \
    MNAFB_ADMIN_USER=admin MNAFB_ADMIN_PASS=... python3 tests/api/multisite_test.py

Everything the test creates is deleted again at the end.
"""

import os
import re
import sys
import time

import requests

BASE = os.environ.get("MNAFB_BASE", "http://localhost").rstrip("/")
SITES = [s.strip() for s in os.environ.get("MNAFB_SITES", "/,/second/").split(",") if s.strip()]
ADMIN_USER = os.environ.get("MNAFB_ADMIN_USER", "admin")
ADMIN_PASS = os.environ.get("MNAFB_ADMIN_PASS", "")
CLIENT = {"X-MNAFB-Client": "1"}

results = []


def check(name, ok, detail=""):
    results.append(ok)
    print(f"[{'PASS' if ok else 'FAIL'}] {name}" + ("" if ok or not detail else f" — {detail}"))


def site_url(site, path=""):
    return BASE + "/" + site.strip("/") + ("/" if site.strip("/") else "") + path.lstrip("/")


def api(site, route):
    return site_url(site, "wp-json/mna-feedback/v1/" + route.lstrip("/"))


class Team:
    """A signed-in WordPress user with one REST nonce per site."""

    def __init__(self):
        self.s = requests.Session()
        self.nonces = {}

    def login(self):
        self.s.get(BASE + "/wp-login.php")
        r = self.s.post(
            BASE + "/wp-login.php",
            data={"log": ADMIN_USER, "pwd": ADMIN_PASS, "wp-submit": "Log In", "testcookie": "1", "redirect_to": BASE + "/wp-admin/"},
            allow_redirects=False,
        )
        return r.status_code in (302, 303)

    def nonce(self, site):
        if site not in self.nonces:
            r = self.s.get(api(site, "session/nonce"), headers=CLIENT)
            self.nonces[site] = r.json().get("nonce") if r.ok else None
        return self.nonces[site]

    def call(self, method, site, route, body=None):
        headers = {**CLIENT, "X-WP-Nonce": self.nonce(site) or ""}
        return self.s.request(method, api(site, route), json=body, headers=headers, allow_redirects=False)


class Guest:
    def __init__(self):
        self.s = requests.Session()
        self.csrf = {}

    def join(self, site, link_url, name):
        self.s.get(link_url, allow_redirects=False)
        r = self.s.post(api(site, "session"), json={"name": name}, headers=CLIENT)
        if r.status_code == 201:
            self.csrf[site] = r.json().get("csrf")
        return r

    def get(self, site, route):
        return self.s.get(api(site, route), params={"_mnafb": str(time.time())})

    def post(self, site, route, body):
        return self.s.post(api(site, route), json=body, headers={**CLIENT, "X-MNAFB-Token": self.csrf.get(site) or ""})


def main():
    if not ADMIN_PASS:
        print("Set MNAFB_ADMIN_PASS")
        return 2
    if len(SITES) < 2:
        print("MNAFB_SITES needs at least two site paths")
        return 2

    team = Team()
    check("Super admin signs in", team.login())

    created = {site: {"items": [], "links": []} for site in SITES}
    stamp = str(int(time.time()))

    # Each site gets its own item and share link.
    for n, site in enumerate(SITES):
        r = team.call("GET", site, "session")
        check(f"{site}: admin is a manager", r.ok and r.json().get("me", {}).get("role") == "manager", r.text[:200])
        r = team.call("POST", site, "items", {"title": f"Site {n + 1} item {stamp}", "page_url": site_url(site), "pin": {"type": "page"}})
        check(f"{site}: item created", r.status_code == 201, r.text[:200])
        if r.status_code == 201:
            created[site]["items"].append(r.json()["id"])
        r = team.call("POST", site, "admin/links", {"label": f"Multisite test {stamp}", "expires_at": ""})
        check(f"{site}: share link created", r.status_code == 201, r.text[:200])
        if r.status_code == 201:
            created[site]["links"].append(r.json())

    # Records stay on their own site.
    for n, site in enumerate(SITES):
        r = team.call("GET", site, "items")
        titles = [i.get("title", "") for i in (r.json().get("items", []) if r.ok else [])]
        own = f"Site {n + 1} item {stamp}"
        others = [f"Site {m + 1} item {stamp}" for m in range(len(SITES)) if m != n]
        check(f"{site}: lists only its own feedback", own in titles and not any(o in titles for o in others), str(titles))

    first, second = SITES[0], SITES[1]
    link1 = created[first]["links"][0]["url"] if created[first]["links"] else ""
    link2 = created[second]["links"][0]["url"] if created[second]["links"] else ""

    # A link from one site does nothing on another.
    stranger = Guest()
    token2 = re.search(r"mna-review=([A-Za-z0-9_-]+)", link2)
    if token2:
        stranger.s.get(site_url(first) + "?mna-review=" + token2.group(1), allow_redirects=False)
        r = stranger.get(first, "session")
        body = r.json() if r.ok else {}
        check("Second site's share link is not valid on the first site", body.get("authenticated") is False and (body.get("join") or {}).get("state") != "required", r.text[:200])

    # A guest reviewing two sites keeps both sessions, under per-site cookie names.
    guest = Guest()
    r = guest.join(second, link2, "Network Guest")
    check("Guest joins the second site", r.status_code == 201, r.text[:200])
    names = sorted(c.name for c in guest.s.cookies)
    check("Cookie names carry the site ID", any(re.fullmatch(r"mnafb_s_\d+", n) for n in names) and "mnafb_s" not in names, str(names))

    r = guest.get(first, "items")
    check("Second-site guest cannot read the first site's feedback", r.status_code == 401, f"{r.status_code} {r.text[:120]}")
    r = guest.post(first, "items", {"title": "Should not exist", "page_url": site_url(first), "pin": {"type": "page"}})
    check("Second-site guest cannot post on the first site", r.status_code == 401, f"{r.status_code} {r.text[:120]}")

    # A token copied into the first site's cookie name still does not work there.
    session2 = next((c for c in guest.s.cookies if re.fullmatch(r"mnafb_s_\d+", c.name)), None)
    if session2:
        swapped = requests.Session()
        first_id = site_id(team, first)
        swapped.cookies.set(f"mnafb_s_{first_id}", session2.value, domain=session2.domain, path=session2.path)
        r = swapped.get(api(first, "items"))
        check("A session token only works on the site that issued it", r.status_code == 401, f"{r.status_code}")

    r = guest.join(first, link1, "Network Guest")
    check("The same guest joins the first site too", r.status_code == 201, r.text[:200])
    r1 = guest.get(first, "items")
    r2 = guest.get(second, "items")
    t1 = [i["title"] for i in r1.json().get("items", [])] if r1.ok else []
    t2 = [i["title"] for i in r2.json().get("items", [])] if r2.ok else []
    check("Both sessions stay active side by side", r1.ok and r2.ok, f"{r1.status_code} {r2.status_code}")
    check("Each session sees its own site's feedback", f"Site 1 item {stamp}" in t1 and f"Site 2 item {stamp}" in t2 and f"Site 2 item {stamp}" not in t1, f"{t1} {t2}")

    r = guest.post(second, "items", {"title": f"Guest item {stamp}", "page_url": site_url(second), "pin": {"type": "page"}})
    check("Guest posts on the second site", r.status_code == 201, r.text[:200])
    if r.status_code == 201:
        created[second]["items"].append(r.json()["id"])
    r = guest.get(first, "items")
    check("…and it does not appear on the first site", r.ok and f"Guest item {stamp}" not in [i["title"] for i in r.json().get("items", [])])

    # The public loader checks the site's own flag cookie.
    for site in (first, second):
        html = requests.get(site_url(site), cookies={}).text
        sid = site_id(team, site)
        check(f"{site}: loader looks for mnafb_on_{sid}", f"mnafb_on_{sid}=1" in html and f'"site":{sid}' in html, re.search(r"mnafb_on[_0-9]*=1", html).group(0) if re.search(r"mnafb_on[_0-9]*=1", html) else "no loader")

    # Clean up.
    for site in SITES:
        for item_id in created[site]["items"]:
            team.call("DELETE", site, f"items/{item_id}")
            team.call("DELETE", site, f"items/{item_id}?force=true")
        for link in created[site]["links"]:
            team.call("POST", site, f"admin/links/{link['id']}/revoke")
    r = team.call("GET", second, "items")
    check("Test items removed", r.ok and not [i for i in r.json().get("items", []) if stamp in i.get("title", "")])

    passed = sum(results)
    print(f"\n{passed}/{len(results)} checks passed")
    return 0 if passed == len(results) else 1


_site_ids = {}


def site_id(team, site):
    """Blog ID of a site, read from its loader config."""
    if site not in _site_ids:
        html = requests.get(site_url(site)).text
        m = re.search(r'"site":(\d+)', html)
        _site_ids[site] = int(m.group(1)) if m else 0
    return _site_ids[site]


if __name__ == "__main__":
    sys.exit(main())
