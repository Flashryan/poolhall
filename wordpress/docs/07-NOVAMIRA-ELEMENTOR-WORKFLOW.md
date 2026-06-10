# Novamira and Elementor Build Workflow

This is the required implementation workflow for the WordPress presentation
layer. It does not move Giig or application business logic into Elementor.

## 1. Required stack

- WordPress current stable release at build time
- Hello Elementor parent theme
- Version-controlled Hello Elementor child theme
- Elementor Pro
- Elementor 4 Atomic elements enabled on staging where stable
- Poolhall custom integration plugin
- Novamira connected to local/staging only

Novamira must not be installed, connected or granted credentials on production.

## 2. Ownership split

### Elementor owns

- Site Settings: colors, type, spacing and breakpoints
- Global header and footer
- Theme Builder display conditions
- Marketing page layouts and editable copy
- Blog archive and single-post presentation
- Job archive and single-job presentation shells
- Candidate auth and portal presentation shells

### Child theme owns

- Font files and shared asset loading
- Minimal global CSS that cannot be expressed reliably in Site Settings
- Accessibility and no-JavaScript fallbacks
- Theme compatibility hooks

### Custom plugin owns

- Job CPT, taxonomies and metadata
- Giig sync and application adapters
- Search/filter query logic
- Reviews, schema, sitemap and Indexing API logic
- Secure form handlers and application queue
- Candidate identity, saved jobs, alerts, profile/CV and privacy services
- Custom Elementor dynamic tags and widgets
- Admin health, logs and retention jobs

Elementor controls presentation. It must not contain API credentials, Giig
requests, application rules, schema rules or duplicated job-query logic.

## 3. Elementor 4 strategy

Use a hybrid build:

- Prefer Atomic elements for containers, headings, text, images and buttons
  where their controls are stable.
- Use v4 Variables, Global Classes and Components from
  `10-ELEMENTOR-DESIGN-SYSTEM.md`.
- Use global classes for repeated visual patterns.
- Use v3/classic Loop Grid, Loop Carousel and Loop Items for dynamic
  WordPress-query-backed collections.
- Use classic widgets elsewhere only where Atomic controls or integrations are
  not production-ready and record the exception.
- Use custom plugin query services/dynamic tags for public Loop Items and
  custom widgets for private or functional interfaces.
- Do not rebuild working classic widgets solely to claim an all-Atomic site.
- Never use Loop Grid for private candidate collections.

## 4. Initial setup

1. Create local and staging WordPress environments.
2. Install Hello Elementor, the child theme, Elementor Pro and the Poolhall
   plugin.
3. Activate the Elementor Pro licence on staging.
4. Connect Novamira to local/staging with a least-privilege administrator used
   only for development.
5. Set WordPress permalinks, timezone, media sizes and privacy defaults.
6. Configure Elementor Site Settings before building pages.
7. Create/sync v4 Variables and v3 Global Colors/Fonts.
8. Create the v4 Global Class registry and style-guide page.
9. Export the initial Site Settings and Theme Builder state to the repository's
   deployment artifacts.

## 5. Site Settings and global classes

Create tokens from `01-UX-UI-CONTENT-SPEC.md`. Required class intent includes:

- `ph-container`
- `ph-section`
- `ph-section--navy`
- `ph-section--paper`
- `ph-stack-sm`, `ph-stack-md`, `ph-stack-lg`
- `ph-grid-2`, `ph-grid-3`
- `ph-button`, `ph-button--primary`, `ph-button--secondary`
- `ph-card`, `ph-card--job`, `ph-card--review`
- `ph-eyebrow`
- `ph-form-field`
- `ph-visually-hidden`

The full required registry and exact values are in
`10-ELEMENTOR-DESIGN-SYSTEM.md`. Do not create an alternate class system.

Shared classes are an API. Before changing one, find every template and page
that uses it and verify all affected views.

## 6. Template build order

Build and approve in this order:

1. Global header and mobile navigation
2. Global footer
3. Default page shell
4. Home
5. Jobs archive shell
6. Single job
7. Expired job/410 content
8. Apply shell
9. Candidate auth shell
10. Candidate dashboard and account shells
11. Employers, Services, Sectors and Better Job Adverts
12. Team, Join Our Team and Contact
13. Blog archive and single post
14. Legal pages and 404

Before page assembly, create the v3 Loop Items defined in
`10-ELEMENTOR-DESIGN-SYSTEM.md` and record their template IDs and every consuming
Loop Grid/Carousel.

Record every Theme Builder template ID and display condition. Conditions must be
specific enough that job, post and page templates cannot overlap accidentally.

## 7. Required Novamira edit loop

For every Elementor data change:

1. Identify the target page/template ID and frontend URL.
2. Inspect the compact Elementor structure.
3. Inspect the exact target element and any shared class references.
4. Back up the current `_elementor_data`.
5. Make the smallest possible edit.
6. Regenerate Elementor CSS/data.
7. Clear Elementor element, CSS and page-asset caches.
8. Purge page/object/CDN cache where applicable.
9. Verify the frontend at desktop and mobile widths.
10. Record the changed IDs, backup location and verification result.

Do not perform broad search-and-replace operations on serialized Elementor data.

For a Loop Item edit, verify every page/template that references that Loop Item,
not only the template preview.

### Backup example

```php
$post_id = 123;
$data = get_post_meta($post_id, '_elementor_data', true);
$backup = WP_CONTENT_DIR . '/elementor-backups/' .
    $post_id . '-' . gmdate('Ymd-His') . '.json';
wp_mkdir_p(dirname($backup));
file_put_contents($backup, $data);
```

The backup directory must not be publicly browsable and must be excluded from
production deployment artifacts.

### Regeneration and cache clearing

Use Elementor's supported tools/APIs for the installed version. At minimum,
regenerate CSS/data and clear these post caches when present:

```php
delete_post_meta($post_id, '_elementor_element_cache');
delete_post_meta($post_id, '_elementor_css');
delete_post_meta($post_id, '_elementor_page_assets');
clean_post_cache($post_id);
```

Then run Elementor's CSS/data regeneration and purge host/CDN caches. The
database edit is not complete until the rendered frontend matches the editor.

## 8. Atomic data rules

- Atomic class references belong in `settings.classes.value`.
- Local Atomic styles belong under `styles.<style_id>.variants`.
- Preserve element IDs unless replacing an element intentionally.
- Preserve unknown settings added by the installed Elementor version.
- Do not mutate a shared class without auditing all references.
- Do not hand-edit generated CSS as a source of truth.
- Preserve v3 Loop Item/widget structure inside classic templates.
- Do not convert a working Loop Item to Atomic elements unless Elementor
  officially supports the complete dynamic loop workflow and the migration is
  separately approved/tested.

## 9. Custom Elementor widgets

The Poolhall plugin must provide controlled widgets/dynamic tags for:

- Job Search
- Job Results and Filters
- Job Card
- Job Details
- Apply CTA
- Application Form
- Featured Jobs
- Google Reviews
- Live Job Count
- Candidate Auth
- Candidate Account Navigation
- Candidate Dashboard
- Saved Jobs
- Application History
- Job Alerts
- Candidate Profile and CV
- Candidate Security
- Candidate Privacy

Each widget exposes presentation controls only. Queries, sanitization,
permissions, remote requests and submission behavior remain in plugin services.

## 10. Form rules

- Elementor Forms may render low-risk contact, employer and join-team forms only
  when submissions are delegated to the plugin's validated handlers.
- Do not place API keys or operational email logic in Elementor actions.
- The application form must be the custom plugin widget because it requires CV
  validation, idempotency, encryption, retry and Giig mode handling.
- Candidate auth and portal forms must be custom plugin widgets because they
  require ownership checks, private data, reauthentication and cache controls.

## 10A. Candidate portal template rules

- Elementor provides only the portal shell, safe copy and style controls.
- Do not place private candidate values directly into reusable Elementor
  document data.
- Render private values through plugin widgets after authorization.
- Candidate routes must bypass page/CDN cache and be `noindex,nofollow`.
- Header account state must vary safely without leaking one user's state into a
  cached public response.
- Verify signed-out, unverified and verified states separately after any shared
  header or portal-template edit.

## 11. Responsive verification

Check every shared template at:

- 360px
- 390px
- 768px
- 1024px
- 1280px
- 1440px

Verify no horizontal overflow, correct mobile navigation, filter drawer focus,
readable type, 44px touch targets and stable header/footer display conditions.

## 12. Deployment

- Deploy child-theme and plugin code through Git/host deployment.
- Move Elementor content with a tested database migration or Elementor export;
  do not rebuild production pages manually.
- Replace environment URLs using serialization-safe WordPress tooling.
- Regenerate Elementor CSS/data after import.
- Clear all caches and verify editor/frontend parity.
- Remove Novamira users, tokens, connectors and temporary backups before
  production launch.

## 13. Handoff record

Provide:

- Elementor Site Settings export
- Theme Builder templates export
- v4 Variables and Components export
- Template ID and display-condition inventory
- Global class inventory
- Style-guide page ID/URL
- v3 Loop Item ID and usage inventory
- Custom widget/dynamic-tag reference
- Novamira connection/removal record
- Last successful CSS regeneration date
- Desktop/mobile verification screenshots
- Restore test result for one representative template
