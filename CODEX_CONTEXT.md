# Codex Context Handoff

Last updated: 2026-08-01

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
/Users/qiulei/Documents/Codex/2026-07-27/tjf/work/artdon_hongkong
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

- Cleaned public NO IMAGE, duplicate-product and empty-content issues without hard-deleting product records. The cleanup was applied directly to the Hong Kong production database after a full recoverable product/variant backup.
- Unpublished two empty duplicate LUMI series pages (`lumi-surface-mounted-light`, `lumi-surface-mounted-light-2`) because both were published with no variants and no text body. Unpublished the duplicate `LOW VOLTAGE TRACK (Recessed)` variant ending in `-2`. Also aligned variants under unpublished parent series by marking those variants unpublished.
- Filled two published MAGENTRA variants that had empty model images from their parent series cover image, and backfilled 152 published variant intro/short-description fields from their already generated SEO descriptions so published product-detail pages are not text-empty.
- Disabled the stale homepage product slot `id=57` that pointed to unpublished `profocus-outdoor-light-2` and caused `NO IMAGE` to remain in the homepage HTML.
- Created recoverable backups before changes: `/root/artdon_content_cleanup_backup_20260801_124828.json` for product/variant data and `/root/artdon_home_slots_cleanup_backup_20260801_125038.json` for homepage product slots. Cleared six old public HTML cache files into `storage/page_cache_backup_content_cleanup_20260801_125050`.
- Verified after cleanup: published series 45, published variants 153, 0 published series without image, 0 published variants without image, 0 empty published series bodies, 0 empty published variant bodies, 0 duplicate published series-name groups, 0 duplicate published variant model/name groups and 0 active homepage slots with missing/unpublished/no-image targets. Homepage and products page source both contain 0 `NO IMAGE` strings. Sitemap now has 378 URLs and does not contain the cleaned old slugs.
- Backfilled and de-duplicated SEO Title and Meta Description data across the public content tables that already support SEO fields. The main repair was the product center: 46 published series and 167 published variants now have non-empty SEO titles and descriptions.
- Preserved existing manually written SEO values and only filled blanks, except for generated duplicate product titles that were corrected to unique titles. The two old duplicate low-voltage recessed accessory records were given temporary distinct SEO titles; the duplicate/empty-content cleanup item should decide whether to merge, rewrite or remove them later.
- Verified public SEO data after the update: `web_products`, `web_product_variants`, `web_solution_pages`, `web_solution_retail_applications`, `web_resource_pages`, `web_resource_blog_articles`, `web_about_pages` and `web_resource_videos` all report 0 empty SEO titles, 0 empty SEO descriptions and 0 duplicate title groups for published/active records.
- Created a recoverable SEO metadata backup before updating database fields: `/root/artdon_seo_meta_backup_20260801_123856.json`. A cache sweep for this item found no existing HTML cache files to move.
- Live sample checks confirm the new meta output on the ARMI series page, ARMI 45 product page, products page and Hospitality Solutions page.
- Unified canonical URL handling for the public site. The formal public host is `https://artdonlighting.com`; `http://artdonlighting.com`, `http://www.artdonlighting.com` and `https://www.artdonlighting.com` now 301 to the HTTPS apex domain while preserving paths and query strings.
- Added `www.artdonlighting.com` to the live TLS certificate. The deployed Let's Encrypt certificate covers `artdonlighting.com` and `www.artdonlighting.com`, verifies successfully and is valid from 2026-08-01 to 2026-10-30.
- Converted legacy public duplicates to permanent redirects: `/index.php` 301s to `/`, `/Home/Products/` 301s to `/products.php`, `series.php?slug=...` 301s to the pretty series URL and `product.php?slug=...` 301s to the pretty product URL.
- Kept `products.php` as the canonical all-products page because existing navigation, filters and backend-generated links already use it. The products canonical tag and sitemap entry now point to `https://artdonlighting.com/products.php`; the sitemap contains 0 `/Home/Products` entries after the fix.
- Created recoverable production backups before URL/SSL changes: `/root/artdon_url_ssl_backup_20260801_113621`, source backups tagged `url_canonical_20260801_115128`, and `/root/artdon_url_products_backup_20260801_123151`. Production PHP lint passed for every changed PHP file.
- Verified the live redirect matrix: HTTP apex 301, HTTP www 301, HTTPS www 301, old `/index.php` 301, old `/Home/Products/` 301, legacy series query 301 and legacy product query 301. Followed final URLs return HTTP 200 with no redirect loop.
- Rebuilt the SEO sitemap structure. `https://artdonlighting.com/sitemap.xml` is now the stable public sitemap-index endpoint and points to the automatically generated `https://artdonlighting.com/sitemap.php` URL set.
- Corrected `robots.txt` to advertise the formal HTTPS domain sitemap rather than the obsolete server IP address.
- Expanded the dynamic sitemap from 8 static IP-based URLs to 380 current public URLs: core pages, current product categories, canonical pretty product/series URLs, published blog articles, active project details and active Solution Application detail pages.
- Excluded all admin/internal paths, duplicate entries, legacy `series.php?slug` / `product.php?slug` product detail URLs and two known 404 legacy 3-circuit variant routes. Static pages no longer publish a false “today” last-modified date on every sitemap request.
- Created recoverable production backups tagged `seo_sitemap_20260801_104500` before deployment. Production PHP lint passed; XML parsing verified 380 URLs, 0 old-IP URLs, 0 legacy product-detail URLs and 0 duplicate URLs. A complete public HTTP check returned 380/380 final 2xx responses.
- Backfilled existing public Solution Application cards into the new category-aware backend instead of leaving the five new categories empty. The verified record counts are Retail 6, Hospitality 6, Museum & Gallery 6, Office 5, Residential 6 and Outdoor & Landscape 6. Solution detail pages now render these managed cards, preserving their prior titles and images while routing clicks to their editable detail pages.
- Deployed `includes/retail_application_data.php` and `includes/solution_page_template.php` after recoverable production backups tagged `solution_application_backfill_20260730_140249` and moving old page-cache files to `storage/page_cache_backup_solution_application_backfill_20260730_140249`.
- Expanded the former Retail-only Applications backend into `Solution Applications`. It now provides six selectable parent categories: Retail, Hospitality, Museum & Gallery, Office, Residential and Outdoor & Landscape. Each parent category has its own create/edit/delete application list; the five new categories start empty and publish nothing until an application is deliberately created.
- Added category-aware application storage, save/delete routing and a generic public route `solutions-application.php?solution=...&slug=...` for all non-Retail application detail pages. Existing Retail application URLs and content remain unchanged.
- Deployed `admin/_layout.php`, `admin/retail_applications.php`, `admin/retail_applications_action.php`, `admin/save_retail_application.php`, `includes/retail_application_data.php`, `includes/retail_application_template.php` and `solutions-application.php` after recoverable production backups tagged `solution_application_categories_20260730_121334` and moving old page-cache files to `storage/page_cache_backup_solution_application_categories_20260730_121334`.
- Added create and delete controls to `admin/retail_applications.php`. The left application list now has a form for creating a new Retail Application (name plus optional slug) and every card has a confirmed delete action; one application must remain so the application set can never become empty.
- New applications receive a full editable default template, appear in the application list immediately, clear public cache on creation/deletion and open through the new dynamic public route `solutions-retail-application.php?slug=...`. The six original application URLs remain unchanged.
- Deployed `admin/retail_applications.php`, `admin/retail_applications_action.php`, `includes/retail_application_data.php` and `solutions-retail-application.php` after recoverable production backups tagged `retail_application_manage_20260730_115134` and moving old page-cache files to `storage/page_cache_backup_retail_application_manage_20260730_115134`.
- Made every editable element of the public Projects top banner available in the admin: image, image ALT, large title and small descriptive copy. The controls are in `Projects 详情页 → 项目页顶部横幅`; saving them clears the public cache and does not affect project-card images.
- Deployed `project.php`, `admin/project_action.php` and `admin/project_details.php` after recoverable production backups tagged `project_banner_copy_20260729_145645` and moving the prior page-cache files to `storage/page_cache_backup_project_banner_copy_20260729_145645`.
- Made the first large image on `project.php` independently editable in the admin: `Projects 详情页 → 项目页顶部横幅` now supports direct upload, media-library selection, image ALT text and automatic public-cache clearing.
- The public Projects page prioritizes this saved banner image while retaining the former `featured-retail.webp` image as a safe fallback; project-card images are unaffected.
- Deployed `project.php`, `admin/project_action.php` and `admin/project_details.php` after creating recoverable production backups tagged `project_banner_admin_20260729_144433` and moving older page-cache files to `storage/page_cache_backup_project_banner_admin_20260729_144433`.
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

- `0414ef6` - Unify canonical URLs and www SSL
- `b816dd1` - Fix sitemap and robots SEO discovery
- `d942bfe` - Manage blog categories in admin
- `b4a4ec3` - Add editable browser tab icon
- `ec00724` - Backfill solution application management
- `0ce648a` - Document solution application category management
- `ca709ed` - Manage applications for all solution categories
- `10ae7ca` - Document retail application management controls
- `0c77153` - Add retail application create and delete controls
- `46bb946` - Document editable projects banner copy
- `7a294b5` - Make projects banner copy editable
- `c1b0cc3` - Document editable projects banner
- `bb5a939` - Make projects page banner editable
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

- Product/content cleanup was applied directly in the Hong Kong production database. No source-code deployment was required beyond syncing this context file. Public checks confirm 0 NO IMAGE strings on the homepage/products page and 0 published duplicate/empty/no-image product records.
- SEO title and description data has been backfilled directly in the Hong Kong production database. The affected public SEO-capable tables now verify with 0 empty titles, 0 empty descriptions and 0 duplicate title groups among published/active records. No source-code deployment was required for this item beyond syncing this context file.
- Nginx vhost and certificate are live with canonical apex-domain routing. `www.artdonlighting.com` no longer serves the default site; it redirects to `https://artdonlighting.com` and the certificate validates with SANs for both apex and www.
- `index.php`, `products.php`, `series.php`, `product.php`, `Home/Products/index.php` and `includes/artdon_pages_v710.php` are synced to the Hong Kong server with canonical URL redirects and the updated products sitemap/canonical entry. Production PHP lint passed, legacy URL checks return 301, followed final URLs return HTTP 200, and the dynamic sitemap has 0 `/Home/Products` entries.
- `robots.txt`, `sitemap.xml`, `sitemap.php` and `includes/artdon_pages_v710.php` are synced to the Hong Kong server. The public robots file and sitemap-index URL both return HTTP 200 and reference `https://artdonlighting.com/sitemap.xml` / `https://artdonlighting.com/sitemap.php` as intended. All 380 generated public URLs were checked after deployment and return final 2xx responses.
- `admin/resources_blog_categories.php`, `admin/resources_blog.php`, `admin/save_resources_blog.php`, `admin/resources_blog_template.php`, `admin/resources_pages.php`, `admin/_layout.php`, `includes/resources_blog_data.php` and `resources-blog.php` are synced to the Hong Kong server. Blog categories now live in `web_resource_blog_categories`; the original Lighting Knowledge, Industry News and Artdon News categories were backfilled unchanged. PHP lint passed for all updated files, checksums match local, and the live blog page returns HTTP 200 with all three original category anchors. The new admin route is `admin/resources_blog_categories.php` and returns its expected login redirect when unauthenticated.
- `admin/settings.php`, `includes/bootstrap.php`, `includes/content.php`, `includes/default_content.php` and `assets/img/favicon-artdon.png` are synced to the Hong Kong server. The browser-tab icon is injected once into every public page head, defaults to the supplied Artdon A mark, and can be replaced at `后台 → 网站设置 → 浏览器标签图标（favicon）`. Production PHP lint passed, the site setting was backfilled, and the live Hospitality page returns the expected icon tag and image HTTP 200.
- GitHub push for commits `b4a4ec3`, `d476de7` and `d942bfe` is temporarily pending: GitHub SSH connections from the local machine close before authentication and the production server has no GitHub deploy key. The working commits remain safely stored locally; production is deployed from the same committed files.
- `includes/retail_application_data.php` and `includes/solution_page_template.php` are synced to the Hong Kong server with public-to-admin Solution Application backfill. Both passed production PHP lint and checksums match local; the live Hospitality page returns HTTP 200 and database counts verify content in all six categories.
- `admin/_layout.php`, `admin/retail_applications.php`, `admin/retail_applications_action.php`, `admin/save_retail_application.php`, `includes/retail_application_data.php`, `includes/retail_application_template.php` and `solutions-application.php` are synced to the Hong Kong server with six parent Solution Application categories. All changed files passed production PHP lint and checksums match local; the original retail generic application route returns HTTP 200 and the empty new-category route returns its expected HTTP 404 until an application is created.
- `admin/retail_applications.php`, `admin/retail_applications_action.php`, `includes/retail_application_data.php` and `solutions-retail-application.php` are synced to the Hong Kong server with dynamic Retail Application create/delete support; all four passed production PHP lint and checksums match local. The generic public route was checked live with the existing Fashion Store application.
- `project.php`, `admin/project_action.php` and `admin/project_details.php` are synced to the Hong Kong server with editable public Projects banner image, ALT, large title and small descriptive copy; all three passed production PHP lint and their checksums match local.
- `series.php` is synced to the Hong Kong server with the shared 16:9 center-crop rule for all series Projects images; production PHP lint passed and checksum matches local.
- `series.php`, `product.php`, `includes/product_hierarchy.php`, `includes/product_accessories.php`, `admin/product_variant_edit.php`, `admin/products.php`, `admin/assets/admin_v7.css` and `assets/css/artdon_product_inline_v718.css` are synced to the Hong Kong server; all changed PHP files passed production lint and all eight deployed file checksums match local.
- `series.php`, `product.php`, `assets/js/artdon_product_inline_v718.js` and `assets/css/artdon_product_inline_v718.css` are synced to the Hong Kong server; PHP lint and the product JavaScript syntax check passed.
- `includes/footer.php` and `admin/footer.php` are synced to the Hong Kong server and passed production PHP lint.
- `admin/inquiry_routing.php`, `admin/settings.php`, `includes/default_content.php`, `includes/floating_actions.php` and `includes/inquiry_routing.php` are synced to the Hong Kong server and passed production PHP lint.
- Initial code sync to GitHub completed.
- `WORKFLOW_RULES.md` synced to server.
- `CODEX_CONTEXT.md` synced to server.

## Current Session Closeout

- SEO work follows the user-approved sequence: complete one item, validate front and back end, report, then wait for confirmation before starting the next. Sitemap/robots, URL/301/www-SSL, SEO Title/Description and NO IMAGE / duplicate product / empty-content cleanup are complete; do not begin unified contact information until the user confirms.
- Sitemap/robots, URL/301/www-SSL, SEO Title/Description and content-cleanup GitHub pushes remain pending together with the earlier commits because GitHub SSH is still unavailable. Production is deployed from local files and production database updates.
- Blog categories are now managed separately from blog articles at `后台 → 资源中心 → Blog & Insights → 管理博客分类`. Categories can be added, renamed, ordered, hidden and deleted. Category deletion requires choosing a destination category and moves its articles there, so no blog posts are lost.
- The supplied red-and-black A mark is now the default browser tab icon. Future changes are self-service through the Website Settings page; upload a square PNG (recommended 512 × 512) and save.
- GitHub is the only outstanding sync step for the favicon and blog-category changes; retry `git push origin main` when the SSH service is available, then deploy the context-file commit and update this note.
- Existing public application cards are now backfilled under their corresponding Solution Applications category, so the backend is populated rather than blank and remains the source of the front-end application-card list.
- The five non-Retail solution categories now have their own populated application-management lists inside `Solution Applications`; new child pages can be added in the selected category whenever needed.
- Retail Applications can now be increased or decreased from the left-side list. Creating an item opens its fully editable template; deleting an item removes it from the public application navigation while retaining uploaded media in the media library.
- The public Projects page top banner can now be changed independently in the admin without affecting the project-list images. Its image, ALT, large title and small descriptive copy are all editable in one form.
- No open code changes are pending.
- Next session should continue using the default workflow:

```text
Local edit -> Git commit -> GitHub push -> Server deploy
```

## Standing Rule

Before ending a work session or when the user asks to stop/exit, update this file with the current context, then commit, push to GitHub, and sync to the server.
