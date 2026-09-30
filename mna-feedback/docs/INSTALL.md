# MNA Feedback — installation guide

MNA Feedback is a self-contained WordPress plugin. It needs no external service, subscription or page builder, and every site it is installed on keeps its own records.

## Requirements

| | Minimum |
|---|---|
| WordPress | 6.6 (6.9+ for the optional AI agent abilities) |
| PHP | 8.1, with the GD extension for screenshots (standard on most hosts) |
| Browser (reviewers) | Current Chrome, Edge, Firefox or Safari, desktop or mobile |

Pretty permalinks are not required. Multisite is supported: each site keeps separate records.

## Install

1. In wp-admin go to **Plugins → Add New → Upload Plugin**, choose `mna-feedback-1.1.0.zip`, then **Install Now** and **Activate**.
   - On a multisite network you can network-activate it or activate it per site; each site gets its own tables either way. Sites added later are set up automatically while the plugin is network-active, and people can review several sites of the network in one browser — each site has its own review session.
2. A **Feedback** menu appears in wp-admin, and a **Feedback** item appears in the toolbar for team members.

Activation creates eight database tables (`{prefix}mnafb_items`, `_replies`, `_activity`, `_reviewers`, `_links`, `_sessions`, `_attachments`, `_reads`), a private folder under `wp-content/uploads/` for screenshots, and gives administrators the manager role.

## First review round

1. **Create a share link** — *Feedback → Share links*. Give it a label (for example "Client review — October"), optionally a starting page and an expiry date, then **Create link** and **Copy** the address.
2. **Send the link** to your stakeholders. When they open it they enter their name once (email is optional) and the review panel opens. They get a private return link so they can continue on another device.
3. **Add your team** — *Feedback → People*. Give WordPress users the **Implementer** role (triage, assign, move cards) or **Manager** role (also links, people, settings, exports, Trash). Administrators are always managers.
4. **Review.** Team members click **Feedback** in the toolbar (or *Open review mode* in wp-admin) to turn the overlay on while signed in.

## Using the overlay

| Action | Desktop | Phone / tablet |
|---|---|---|
| Leave a comment on an element | **Comment** mode, click the element | **Comment** mode, tap the element |
| Comment on the whole page | *Comment on the whole page* (panel footer) or *Whole page* in the hint bar | same |
| Choose an element by keyboard | In Comment mode: arrow keys (↑ larger area, ↓ inside, ← → neighbours), **Enter** | — |
| Widen or narrow the selection | ⤢ / ◎ buttons in the composer | same |
| Attach a screenshot | Button, paste (Ctrl/⌘+V) or drag and drop — PNG, JPEG or WebP; larger images are resized to fit 2 MB | Button |
| Post | **Post comment** or Ctrl/⌘+Enter | **Post comment** |
| See everything | **Board** — Open / In progress / Done columns, filters, drag and drop or ⋯ → *Move to* | Board with status tabs |
| See which device a comment came from | Phone / tablet / desktop icon on the card; the comment's **Device** row gives the model, system, browser and screen size | same |
| Only phone (or tablet, desktop) feedback | Board → **Any device** filter | Board → **Filter** |
| Find a comment's pin | *Show on page* in the comment | same |
| Close the topmost layer | **Esc** | ✕ |

Unsent comments, replies and edits are saved in the browser as you type. If the connection drops or the page reloads, the panel offers to restore them.

**Device details** are recorded automatically with every comment and reply: phone, tablet or desktop; the model where the browser shares it (iPhone, iPad, Pixel 8 …); operating system and browser with versions; screen and window size, pixel ratio and touch support. iPads that present themselves as Macs are recognised by their touch screen. Comments left before version 1.1 show an estimate from the browser details they stored, marked *estimated*.

## Updating

Upload the new ZIP the same way (WordPress offers to replace the installed version) or use your usual deployment. Database changes run automatically and records are never removed by an update.

## Deactivating and removing

- **Deactivate:** the overlay disappears; all records are kept.
- **Delete:** records are kept unless a manager has ticked *Permanently delete all feedback … when the plugin is deleted* under *Feedback → Settings → Data* (checked per site on multisite).
- **Delete everything now:** *Feedback → Settings → Delete all feedback data*, then type `DELETE ALL FEEDBACK`. Export first if you might need the records.

## Exports

*Feedback → Settings → Export* downloads every item (including Trash) as **CSV** (one row per item, spreadsheet-safe, with *Device type*, *Device* and *Device details* columns) or **JSON** (items with replies, notes, devices, full history and screenshot details). The same data is available to managers at `GET /wp-json/mna-feedback/v1/admin/export?format=csv|json`.

Personal data requests are handled by WordPress's own tools (*Tools → Export / Erase Personal Data*): a reviewer's identity, comments and replies (with the device each was left on) are exported by email address, and erasure anonymises their name and email and removes the full browser strings kept with their comments and sessions.

## AI agents (Novamira) — optional

On WordPress 6.9 or newer (which includes the Abilities API), a manager can switch on *Feedback → Settings → AI agents*. Agents signed in as a team member then get five abilities in the **Site feedback** category:

| Ability | What it does | Needs |
|---|---|---|
| `mna-feedback/list-items` | List feedback with status, priority, device (phone, tablet, desktop), page, assignee and search filters; each item says which device it was left on | Reviewer or above |
| `mna-feedback/get-item` | One item with description, element details, device, replies, notes, screenshots metadata and full history | Reviewer or above |
| `mna-feedback/list-team` | Team members who can be assigned work | Reviewer or above |
| `mna-feedback/add-note` | Add an implementation note | Implementer or above |
| `mna-feedback/update-item` | Change status, priority or assignee | Implementer or above |

They use the same permission rules as the interface, never change site content, and every change appears in the item's history marked **via AI agent**.

## Hosting notes and troubleshooting

- **Page caching / CDN:** nothing to configure. Public pages only get a tiny loader that is identical for everyone. If an optimisation plugin combines or delays inline scripts, exclude the script with id `mnafb-loader` (it is already marked `data-no-optimize`, `data-no-defer`, `nowprocket` and `data-cfasync="false"`).
- **Security plugins that restrict the REST API:** allow the `mna-feedback/v1` namespace for logged-out visitors — guest reviewers use it with their session cookie. The plugin authorises every request itself.
- **Screenshots on Nginx:** screenshots are encrypted on disk with a per-site key and only ever decrypted by the API, so they stay private even where `.htaccess` is ignored. The storage folder also has an unguessable name and `.htaccess` / `web.config` rules; on Nginx you can additionally add `location ~* /wp-content/uploads/mna-feedback- { deny all; }`.
- **The overlay does not appear after opening a link:** the browser must allow cookies for the site. Opening the link in a private window works; embedded browsers that block cookies do not.
- **"Your review access has ended":** the link was revoked, expired or replaced, or the reviewer was removed. Create or share a current link.
- **Screenshots fail to upload:** the server needs PHP's GD extension with PNG/JPEG/WebP support.

## Building from source

```bash
cd mna-feedback
npm install
npm run typecheck   # TypeScript
npm run build       # bundles src/app into assets/app.js
npm run package     # writes dist/mna-feedback-<version>.zip
python3 tests/api/smoke_test.py   # API checks against a running site (see TESTING.md)
```

### Filters for developers

| Filter | Purpose |
|---|---|
| `mnafb_allowed_hosts` | Extra hostnames whose pages can hold feedback (domain-mapped sites) |
| `mnafb_allowed_origins` | Extra origins allowed to call the API |
| `mnafb_print_loader` | Return false to skip the loader on specific requests |
| `mnafb_joins_per_hour` | Identities one network address may create per hour (default 30) |
| `mnafb_privacy_erase_content` | Return true to also remove a person's comment text on privacy erasure |
