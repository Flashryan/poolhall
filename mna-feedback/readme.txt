=== MNA Feedback ===
Contributors: mnadigital
Tags: feedback, review, annotations, client approval, kanban
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A review overlay for stakeholders: pin comments to any part of a page, discuss them, and track the work on a Trello-style board. Records stay in this site's database.

== Description ==

MNA Feedback adds a private review layer on top of your live site. It is not part of the website: ordinary visitors never see it, and it never changes your pages or Elementor documents.

* **Pin comments to anything.** Switch to Comment mode, click an element and describe the change. Pins follow their element through scrolling, resizing, dynamic content and Elementor layout edits. If an element disappears, the comment says so instead of pointing at something unrelated.
* **Side panel and board.** A collapsible panel lists this page's feedback. The board shows everything as Open, In progress and Done, with drag and drop and an accessible "Move to" menu.
* **Discussion and history.** Replies, implementation notes, screenshots, assignments, unread markers and a full history of edits and status changes.
* **Share links.** Reviewers join from a link by entering their name — no account needed. Links can expire and can be revoked or replaced at any time.
* **Roles.** Reviewers comment and reopen finished work, implementers triage and move cards, managers control links, people, settings, exports and Trash.
* **Safe with caching.** Public pages stay identical for everyone; all private data is loaded through an authorised, uncacheable REST API.
* **Your data.** Everything is stored in this site's database. Records survive updates, deactivation and uninstall unless a manager chooses to delete them. CSV and JSON exports, and WordPress privacy export/erasure, are built in.
* **Optional AI agent abilities.** On WordPress 6.9+ a manager can let agents (for example Novamira) read feedback, add implementation notes, assign items and change status. Agents cannot change site content through these abilities, and everything they do is recorded.

No external service, subscription or page builder is required.

== Installation ==

1. Upload the ZIP under Plugins → Add New → Upload Plugin, then activate.
2. Go to Feedback → Share links and create a link.
3. Send the link to your reviewers. Team members (implementers and managers) use "Open review mode" or the Feedback item in the toolbar.

See INSTALL.md and PERMISSIONS.md in the source repository for the full guides.

== Frequently Asked Questions ==

= Does it work with page caching and CDNs? =

Yes. The only thing added to public pages is a tiny loader that is the same for every visitor. The interface loads only in browsers that are in review mode, and all feedback is fetched from the REST API with no-store headers.

= What happens to feedback if I deactivate or delete the plugin? =

Nothing — records are kept and come back when the plugin is activated again. To remove everything, use Feedback → Settings → Delete all feedback data, or tick "Permanently delete all feedback … when the plugin is deleted" before deleting it.

= Is it multisite compatible? =

Yes. Each site keeps its own tables, links, people and settings.

== Changelog ==

= 1.0.1 =
* Runs on PHP 8.1 as documented (1.0.0 used a return type that needs PHP 8.2).
* Multisite: network activation now gives every site its own screenshot key, and review cookies, unsent drafts and preferences are kept per site, so reviewing one site never signs you out of another or turns review mode on elsewhere.

= 1.0.0 =
* First release.
