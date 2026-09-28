# MNA Feedback — test results

Version 1.0.1, tested 28 September 2026.

## Environments

| | Plain WordPress (local) | Multisite network (local) | sstest (staging) |
|---|---|---|---|
| WordPress | 7.1.2 | 7.1.2, sub-directory network, 3 sites | 7.1.2 |
| PHP | 8.4.19 | 8.4.19 | 8.5.11 |
| Database | SQLite (Database Integration 3.0.2) | SQLite | MySQL |
| Web server | PHP built-in server | PHP built-in server | nginx, WordPress.com Atomic edge cache, persistent object cache |
| Theme / builders | Twenty Twenty-Five (block theme) | Twenty Twenty-Five | Hello Elementor, Elementor 4.2.4, Elementor Pro 4.2.3 |
| Other plugins | none | none | WooCommerce 11.1, Gravity Forms, Jetpack and 20 others |
| Plugin installed from | source (symlink) **and** the release ZIP | source, network-activated | the release ZIP (SHA-256 checked) |
| Browser | Chromium 141 (Playwright 1.56) | Chromium 141 | Chromium 141 through the environment's proxy |

PHP 8.1 compatibility was checked statically with PHPCompatibility (PHP_CodeSniffer, `testVersion 8.1-`). The only findings are a note, repeated for each `trim()` call, that PHP 8.6 adds the form-feed character to `trim()`'s default characters; that does not affect the plugin. (Version 1.0.0 used a `true` return type, which needs PHP 8.2; 1.0.1 fixes it.)

## Automated suites

| Suite | What it covers | Plain WordPress | Multisite | sstest |
|---|---|---|---|---|
| `tests/api/smoke_test.py` | Share links, joining, identities, items, conflicts, permissions, replies, notes, unread, screenshots, cross-site protections, sync, Trash, return links, exports, revocation | **73/73** (source and ZIP install) | — | **73/73** (on the 1.0.0 build; 1.0.1's server changes are type declarations and multisite-only caching and cookie names, covered by the local runs) |
| `tests/e2e/overlay.e2e.mjs` | The interface in a real browser: joining, pins, anchoring under scroll/resize/DOM changes, unavailable elements, persistence, keyboard, page comments, screenshots, editing, conflicts, offline recovery, drafts, identities, replies, live refresh, board drag and drop, Move to, reopen, Trash, layouts at 5 widths, touch | **40/40** (source and ZIP install) | — | see *Rate limiting* below |
| `tests/api/abilities_test.py` | Abilities API: registration toggle, annotations, all five abilities, agent history, reviewer and outsider limits | **18/18** | — | exercised live through Novamira (below) |
| `tests/api/multisite_test.py` | Records, share links, guest sessions, cookie names and the page loader kept separate per site | — | **23/23** | — |
| `tests/e2e/site-integrity.e2e.mjs` | Layout unchanged, Elementor anchoring, navigation, WooCommerce basket and checkout, forms | layout and navigation pass (no Elementor or WooCommerce there) | — | **10/11** — every site check passes; the one failure was the test's own clean-up (it did not recognise the site's custom basket remove link; fixed in the script) |

Run them with the commands in each file's header. The browser suites need Playwright; the API suites need Python 3 with `requests`.

## Acceptance checks

| Requirement | Result | Evidence |
|---|---|---|
| Feedback survives refresh | ✅ | e2e "Feedback survives a refresh" |
| …another browser session | ✅ | e2e "The same identity resumes in another browser session" (return link) |
| …plugin updates | ✅ | Plain WP: installed the 1.0.0 ZIP, added a record, updated from the 1.0.1 ZIP — record intact, installed files identical to the ZIP, API and browser suites pass. sstest: updated 1.0.0 → 1.0.1 with WordPress's upgrader, then to the final 1.0.1 build while the team was using it — every table's row count identical before and after each update (latest: 11 items, 7 replies, 53 history entries, 17 identities, 8 links, 11 sessions, 1 screenshot, 18 read markers), same file key, the existing screenshot still decrypts, AI-agent setting kept |
| …deactivation / reactivation | ✅ | Plain WP: 2 records before, 2 after deactivating (tables untouched), 2 after reactivating |
| …uninstall (default) | ✅ | Plain WP: deleted the plugin, tables kept; reinstalling brought the records back |
| Explicit purge removes everything | ✅ | Opt-in "delete on uninstall": tables, settings, file key and capabilities removed; fresh install starts empty |
| Multisite sites keep separate records | ✅ | Network activation created `wp_mnafb_*`, `wp_2_mnafb_*`, `wp_3_mnafb_*` tables, settings, capabilities and a distinct file key per site; a site added after activation was set up automatically; `multisite_test.py` 23/23; in a browser, joining one site's review did not bring the interface up on another |
| Link revocation blocks access | ✅ | API "Revoked link ends guest A's session" and "…second device's session too" (both sites); e2e "Revoking the link ends access in the open tab" |
| Reviewers cannot edit another identity's comments | ✅ | API 403 on edit/delete of another guest's item and reply; e2e: a second reviewer **with the same name** gets no Edit or Delete |
| Pins usable after scrolling | ✅ | e2e: pin moves exactly with the page (±12px) |
| …resizing | ✅ | e2e: pin stays on its element at 1100px after being placed at 1280px |
| …dynamic content changes | ✅ | e2e: 150px inserted above the element; pin moved by the same amount |
| …Elementor layout changes | ✅ | site-integrity on sstest (Elementor 4.2.4): the pin recorded the heading widget's Elementor ID; when the widget was moved to another container the pin followed it; when its text was rewritten the pin stayed; when it was removed the card showed "Original element unavailable" |
| Unidentifiable element → "Original element unavailable" | ✅ | e2e: element replaced; pin hidden, card and detail show the message with the saved context |
| Layouts at 320, 390, 768, 1280, 1440px | ✅ | e2e "Layout at …px": panel and board within the viewport, no overflow, 44px touch targets and 16px fields on phones; screenshots below |
| Touch create / edit / delete / restore / move / reopen | ✅ | e2e touch comment at 390px; board moves and reopen via menus (tap-friendly) |
| Keyboard create / edit / delete / restore / move / reopen | ✅ | e2e arrow-key element selection + Ctrl+Enter; "Move to" menu driven by keyboard; Escape handling |
| Concurrent edits keep work, with recovery options | ✅ | e2e: manager edits during a reviewer's edit → both versions shown, "Use my version" saves; API 409 with current version |
| Failed requests keep work, with recovery options | ✅ | e2e: offline post keeps the text, "Try again" succeeds; unsent comment offered after reload. Host throttling (429) while the tool starts is retried automatically, then explained with a Reload button |
| Public visitors cannot retrieve comments or screenshots | ✅ | API: 401 for items and screenshots without a session; screenshots encrypted at rest (verified on disk) |
| Private data never in cached HTML | ✅ | Public page contains only the identical loader (no nonce, no data); all API responses `private, no-store` (sstest edge: MISS) |
| Ordinary visitors don't see review controls | ✅ | e2e "Ordinary visitors do not get the review interface" (both sites) |
| Elementor, WooCommerce, navigation, forms, checkout keep working | ✅ | site-integrity on sstest: page layout identical with the review tool present, hidden and with the board open (34 content elements compared); header navigation; add to basket; checkout fields accept input; the Contact Us form accepts input; no errors from the review tool |
| Novamira can read a task and record progress without changing site content | ✅ | sstest, through the Novamira MCP connection (below) |

## Novamira on sstest

With *AI agents* switched on, the five abilities appeared through the Novamira MCP adapter with their schemas and annotations. As the connected administrator:

1. `mna-feedback/list-items` (status open, search) found the task; `mna-feedback/list-team` listed eight team members with assignable ids.
2. `mna-feedback/add-note` recorded an implementation note.
3. `mna-feedback/update-item` moved the item to In progress and assigned it.
4. `mna-feedback/get-item` returned the description, the note and the history: `created` (ui), `note_added`, `status`, `assigned` — the last three with source **agent**.
5. The About Us page was unchanged: same `post_modified` (2026-09-23 10:53:30) and the same hash of its content and Elementor data before and after.

After the 1.0.1 update, `list-items` still answered through Novamira, including an item pinned in the browser to an Elementor button widget inside a product loop (`pcard22`), reported with its Elementor ID.

## Problems found and fixed during testing

- **PHP 8.1:** 1.0.0 used `true` as a return type (PHP 8.2+). Fixed in 1.0.1.
- **Multisite key:** on network activation the screenshot key was cached from the first site, so other sites got theirs only on first use. Keys, settings and identity caches are now per site.
- **Multisite cookies:** sites on a sub-directory network share a cookie path, so joining one site's review replaced the session for another and switched review mode on across the network. Cookie names and browser storage are now per site.
- **Live updates:** the live-refresh check failed in roughly half of the runs. Logging the reviewer's requests showed polling worked and the server returned the changed item, but the interface discarded it: it decided whether an item had changed from its edit time, revision and unread flag, and timestamps have one-second precision — a second reply landing in the same second as the first looked like no change. Items are now compared by their revision counters and counts. While investigating, two smaller issues were fixed too: after being offline, polling kept its back-off (up to two minutes between checks) until that timer ran out, and a sync request that never answered could stall updates. After the fix the browser suite passed 4 runs out of 4, each showing the change at the first poll (15 seconds).

## Notes and limitations

- **Rate limiting on sstest.** The host's edge answers 429 when one address makes many requests in a short time, and this test environment reaches sstest through one shared address. A fresh load of an Elementor/WooCommerce page makes about a hundred requests in two seconds, which was enough to trip it and block the site's own scripts as well as the plugin. The site-integrity run therefore used a paced mode: images, video and fonts skipped, each page's scripts and stylesheets fetched one at a time into the browser cache before the page was opened, and pauses between pages. The host still answered 2 requests with 429 during the final run, without affecting any check. The full overlay suite was not run on sstest for the same reason; its checks run against plain WordPress, and the API suite (73/73) ran on sstest. Real reviewers are unaffected: they make a handful of requests per page, and the review tool now retries briefly and explains if the site is busy.
- **In use on sstest.** Since installation the team has used the tool on sstest — guests joined through a share link, pinned comments on several pages (including Elementor widgets inside a product loop), replied, and moved cards through the board. Every record was still there after each plugin update (record counts identical before and after).
- **Screenshots and nginx.** nginx ignores `.htaccess`, so screenshots are encrypted at rest with a per-site key and decrypted only by the authorised API. Direct file URLs return ciphertext.
- **Browsers.** Current Chrome, Edge, Firefox and Safari (Safari 14+). The interface uses Shadow DOM and CSS `:where()`. Automated runs used Chromium.
- **Background tabs.** Live refresh pauses while a tab is hidden and resumes (with an immediate refresh) when it is shown again.
- **Multisite database.** The network test used SQLite; table creation goes through WordPress's `dbDelta`, as on MySQL.

## Screenshots

From the plain WordPress run (`docs/screenshots/`):

| | |
|---|---|
| Joining through a share link | `1280-join.png` |
| Page panel with pins | `1280-panel.png`, `1440-panel.png` |
| Pinned element | `1280-pinned.png` |
| Board, and a card being dragged | `1280-board.png`, `1280-board-dragging.png` |
| Screenshot attached to a comment | `1280-screenshot.png` |
| Conflict dialog | `1280-conflict.png` |
| Offline, text kept | `1280-offline.png` |
| Original element unavailable | `1280-unavailable.png` |
| Trash | `1280-trash.png` |
| Access ended after revocation | `1280-access-ended.png` |
| Tablet overlay | `768-panel.png`, `768-board.png` |
| Phone sheet, comment mode and composer | `390-panel.png`, `390-comment-mode.png`, `390-composer-element.png` |
| Phone board | `390-board.png` |
| Small phone | `320-panel.png`, `320-board.png` |
