# Codex Context Handoff

Last updated: 2026-07-29

## Workflow Rule

Default workflow:

```text
Local edit -> Git commit -> GitHub push -> Server deploy
```

Do not edit production server files directly unless it is urgent or explicitly requested.

Excluded from GitHub:

- `uploads/`
- `storage/`
- `website_config.php`
- `.user.ini`
- SQL dumps
- ZIP / archive files
- backups
- temporary repair scripts
- local Codex helper files

## Current Repository

Local project:

```text
/Users/qiulei/Library/Mobile Documents/com~apple~CloudDocs/artdon/artdon_hongkong
```

GitHub remote:

```text
git@github.com:qiulei6386-stack/artdon_hongkong.git
```

Server:

```text
artdon-hongkong:/www/wwwroot/43.132.210.162/
```

## Latest Completed Work

- Updated the shared series-page Projects image treatment from 4:3 to 16:9, reducing image-box height by 25% through center cropping (`object-fit: cover`) without distortion.
- Verified the live EMMA series page: all three project images changed from 597×448 to 597×336, with no horizontal overflow or browser errors.
- Deployed `series.php` after creating the recoverable backup `series.php.bak_project_crop_20260729_110519` and moving previous page-cache files to `storage/page_cache_backup_project_crop_20260729_110519`.
- Expanded product-variant accessory capacity from 8 to 12 across variant editing, normalization and shared accessory push logic.
- Kept series and sibling product cards compact by showing the first four accessories plus a `+N more accessories` indicator when more exist.
- Updated product-detail compatible-accessory layouts for up to 12 entries: four columns on desktop, two on tablet and one on mobile, with incomplete final rows centered.
- Verified the live `1 CIRCUIT TRACK AND ACCESSORIES` series: both products contain eight accessories, each card shows four plus `+4 more accessories`, and each product detail shows all eight in two desktop rows without overflow or console errors.
- Deployed eight accessory-related files after creating recoverable production backups tagged `accessory12_20260728_164912`; moved the old page cache into `storage/page_cache_backup_accessory12_20260728_164912`.
- Simplified the shared series-page Project Support CTA across all product families: removed `Request Sample` and `Download Datasheet`, retained one `Get a quote` button, and right-aligned it at desktop and responsive widths.
- Corrected both product-detail layouts so `Technical files` points to the current product pretty URL plus `#technical-files`, rather than resolving through `<base href="/">` to the homepage.
- Added deterministic same-page scrolling to the `Downloads / Planning files` section with a 96px top offset and cache-busted the product CSS/JS references.
- Verified the live ARMI series page renders exactly one Project Support action with no legacy sample/datasheet buttons; verified the ARMI 73 product page exposes the correct same-product anchor, target heading and updated scroll handler.
- Deployed `series.php`, `product.php`, `assets/js/artdon_product_inline_v718.js` and `assets/css/artdon_product_inline_v718.css`; production PHP lint and JS syntax checks passed.
- Created recoverable production backups with tags `20260727_191801`, `20260727_192150` and `20260727_192547`, and moved regenerated public-page caches into matching backup directories.
- Corrected the footer contact CTA from `index.php#about` to `/about.php`, cleared the remaining public-page caches, and verified both `/products.php` and the homepage render the same 9/7/7/5 footer columns.
- Click-tested the footer `About Us` link from `/products.php`; it opens `/about.php` successfully.
- Backed up `includes/footer.php` and footer content with tag `20260727_153408`, then moved five remaining page-cache files to `storage/page_cache_backup_footer_about_20260727_153419`.
- Rebuilt the public footer navigation as exactly four groups: Products, Solutions, Projects and Resources.
- Added all current product categories, corrected Solutions links to their dedicated pages, completed Projects categories, and replaced legacy Resources destinations with the current Resources pages.
- Persisted footer navigation version `718173`; the live column link counts are Products 9, Solutions 7, Projects 7 and Resources 5.
- Verified all 28 footer navigation links return HTTP 200, the live footer has no horizontal overflow, and the browser console has no errors.
- Backed up production footer code with tag `20260727_152439`, saved the previous footer content as `storage/footer_before_nav_20260727_152439.json`, and moved 16 old page-cache files to `storage/page_cache_backup_footer_nav_20260727_152524`.
- Unified every public `GET A QUOTE` entry through the shared `Project inquiry` modal, including header, homepage hero, series, product-detail and content-page links.
- Added public modal-copy controls under `admin/settings.php` in the `全站询价弹窗` section.
- Added separate task-title, project and dispatch-content templates under `admin/inquiry_routing.php`.
- Changed shared quote submissions to use `source=global_quote` and `support_type=quotation`.
- Verified live behavior on the homepage, ARMI series page, ARMI 45 model page and About page: current URL is retained, the shared modal opens, the legacy homepage modal stays closed, product context is prefilled, and the browser console has no errors.
- Deployed five inquiry-related files after creating production backups with tag `20260727_151137`.
- Moved 31 pre-deployment page-cache files to the recoverable directory `storage/page_cache_backup_quote_unify_20260727_151210`.
- Updated the series-page hero `Get a Quote` action to open the shared CRM inquiry modal used by the floating inquiry button.
- Verified the ARMI series page keeps the current URL, opens `Project inquiry`, shows `Inquiry about: ARMI`, and pre-fills `I am interested in ARMI`.
- Deployed only `includes/floating_actions.php` after creating a production backup.
- Cleared and regenerated the ARMI series-page micro-cache after deployment.
- Initialized local Git repository.
- Connected GitHub with SSH.
- Synced server code to local.
- Excluded uploads, storage, runtime config and backup files from GitHub.
- Pushed initial server code to GitHub.
- Added workflow rules.
- Added context handoff rule and this context file.
- Confirmed standing rule: before exit, update context, commit, push GitHub, and sync server.

## Latest Known Commits

- `7fd6edb` - Crop series project images to widescreen
- `6ab03a0` - Expand product accessory display capacity
- `8c5c960` - Make files anchor scrolling deterministic
- `d3bd0e8` - Ensure product files link scrolls to downloads
- `2023166` - Simplify support CTA and fix files anchor
- `00e23e7` - Point footer About Us to about page
- `2809ff5` - Align footer navigation with public sections
- `4ffb825` - Unify quote modal and configure inquiry dispatch fields
- `89310a2` - Open series quote CTA in inquiry modal
- `aa79f0a` - Initial sync from Hong Kong website server
- `45f65a1` - Document local GitHub server workflow
- `8dbc2d9` - Add Codex context handoff rule

## Server Sync Status

- `series.php` is synced to the Hong Kong server with the shared 16:9 center-crop rule for all series Projects images; production PHP lint passed and checksum matches local.
- `series.php`, `product.php`, `includes/product_hierarchy.php`, `includes/product_accessories.php`, `admin/product_variant_edit.php`, `admin/products.php`, `admin/assets/admin_v7.css` and `assets/css/artdon_product_inline_v718.css` are synced to the Hong Kong server; all changed PHP files passed production lint and all eight deployed file checksums match local.
- `series.php`, `product.php`, `assets/js/artdon_product_inline_v718.js` and `assets/css/artdon_product_inline_v718.css` are synced to the Hong Kong server; PHP lint and the product JavaScript syntax check passed.
- `includes/footer.php` and `admin/footer.php` are synced to the Hong Kong server and passed production PHP lint.
- `admin/inquiry_routing.php`, `admin/settings.php`, `includes/default_content.php`, `includes/floating_actions.php` and `includes/inquiry_routing.php` are synced to the Hong Kong server and passed production PHP lint.
- Initial code sync to GitHub completed.
- `WORKFLOW_RULES.md` synced to server.
- `CODEX_CONTEXT.md` synced to server.

## Current Session Closeout

- Series-page Projects images now use a 16:9 center crop across every product family, reducing display height by 25% without stretching source images.
- No open code changes are pending.
- Next session should continue using the default workflow:

```text
Local edit -> Git commit -> GitHub push -> Server deploy
```

## Standing Rule

Before ending a work session or when the user asks to stop/exit, update this file with the current context, then commit, push to GitHub, and sync to the server.
