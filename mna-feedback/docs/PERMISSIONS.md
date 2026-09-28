# MNA Feedback — permissions guide

## Roles

| | Reviewer | Implementer | Manager |
|---|:-:|:-:|:-:|
| See the shared review space (all feedback on the site) | ✓ | ✓ | ✓ |
| Create feedback (pins, page comments, screenshots¹) | ✓ | ✓ | ✓ |
| Reply | ✓ | ✓ | ✓ |
| Edit or delete **their own** comments and replies | ✓ | ✓ | ✓ |
| Change the priority of their own feedback | ✓ | ✓ | ✓ |
| Reopen finished work (Done → Open) | ✓ | ✓ | ✓ |
| Triage: set priority on anyone's feedback | | ✓ | ✓ |
| Assign feedback to team members | | ✓ | ✓ |
| Move cards through every status and reorder the board | | ✓ | ✓ |
| Add implementation notes | | ✓ | ✓ |
| Edit or delete anyone's comments and replies | | | ✓ |
| Trash: see, restore, delete permanently | | | ✓ |
| Share links: create, expire, revoke, replace | | | ✓ |
| People: grant team roles, remove reviewers | | | ✓ |
| Settings, branding, exports, delete all data | | | ✓ |

¹ Managers can turn guest screenshot uploads off under *Settings → Review tool*.

### Who holds which role

- **Guests** who join through a share link are always **reviewers**.
- **WordPress administrators** are always **managers** (on multisite, super admins too).
- Other WordPress users get a role on *Feedback → People → Team*. This is stored as a per-user grant and never changes the site's WordPress roles.
- The roles map to capabilities, so role-editor plugins can grant them too:

| Capability | Meaning |
|---|---|
| `mnafb_review` | Reviewer |
| `mnafb_implement` | Implementer (with `mnafb_review`) |
| `mnafb_manage` | Manager (with both of the above) |

WordPress users without any of these capabilities are treated like ordinary visitors. If they open a share link they join as a guest reviewer.

## Share links

A share link (`https://site/?mna-review=…`) lets anyone who has it join as a reviewer after entering their name.

| Action | Effect |
|---|---|
| **Expiry** | After the chosen day (site time zone) the link stops admitting people, and sessions created through it end. |
| **Revoke** | Immediately ends every session created through the link and stops new joins. Can be switched back on later. |
| **Replace** | Issues a new address; the old one stops working. People already reviewing keep going. Use this when a link has been forwarded too widely. |

Opening a link moves its token into an HttpOnly cookie and redirects to the same page without it, so the token does not linger in the address bar, history or `Referer` headers. Link visits are never cached.

## Reviewer identities

- Names are self-reported. Every person who joins gets a **distinct internal identity** tied to their browser session — two people who both type "Sam" are separate and cannot edit or delete each other's comments.
- After joining, reviewers are shown a **private return link** (also under *⋯ → Continue on another device*). Opening it on another device resumes the same identity. They can replace it at any time, which invalidates the old one.
- Managers can **remove access** for a reviewer on *People → Reviewers*; their sessions end immediately and their comments stay on the board.

## How access is enforced

- **Sessions:** a random 256-bit token in an HttpOnly, `SameSite=Lax` cookie (Secure on HTTPS). Only its hash is stored. Sessions extend while in use and end after the configured number of idle days (default 30).
- **Every API request** is authorised on the server. Guests' writes must also carry a per-session CSRF token; team members' requests carry WordPress's REST nonce. Requests from other origins, sibling subdomains or JSONP are refused, and state-changing requests must include a header that other sites cannot send.
- **No private data in public pages.** Pages contain only a loader that is identical for everyone. Feedback, names and screenshots are fetched from the API with `Cache-Control: private, no-store`, so neither page caches nor CDNs can store them. The loader only runs in browsers that are in review mode; ordinary visitors never download the interface.
- **Screenshots** are decoded and re-encoded on upload (which strips hidden metadata such as location), **encrypted on disk** with a random per-site key (libsodium), stored under an unguessable folder name with random file names, and decrypted and served only through the API to people allowed to see the item — so they stay private even on servers such as Nginx that ignore `.htaccess`.
- **Rate limits:** reviewers are limited to 150 changes per 10 minutes, and one network address can create at most 30 identities per hour through share links.
- **Multisite:** every site on a network has its own tables, links, reviewers, sessions and screenshot key, and its own cookie names. A link, session token or screenshot from one site grants nothing on another, and reviewing one site never affects a session on another.
- **Revision checks:** edits carry the values they started from. If someone else changed the same field in the meantime, the server refuses the edit and the interface shows both versions instead of silently overwriting.

## Trash and deletion

- Deleting a comment or reply moves it to **Trash**. Reviewers can delete only their own; managers can delete anything.
- Only **managers** can see Trash, restore items, delete them permanently or empty Trash.
- Removing everything permanently requires a manager's explicit action (typed confirmation), or the opt-in *delete data on uninstall* setting.

## AI agents

The optional abilities act as the WordPress user the agent signs in as and apply the same rules: reading needs the reviewer capability, notes and status/assignment changes need implementer. Every agent action is recorded in the item's history with the source **agent**. The abilities cannot change website content.
