# Codex Context Handoff

Last updated: 2026-07-27

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

- `2809ff5` - Align footer navigation with public sections
- `4ffb825` - Unify quote modal and configure inquiry dispatch fields
- `89310a2` - Open series quote CTA in inquiry modal
- `aa79f0a` - Initial sync from Hong Kong website server
- `45f65a1` - Document local GitHub server workflow
- `8dbc2d9` - Add Codex context handoff rule

## Server Sync Status

- `includes/footer.php` and `admin/footer.php` are synced to the Hong Kong server and passed production PHP lint.
- `admin/inquiry_routing.php`, `admin/settings.php`, `includes/default_content.php`, `includes/floating_actions.php` and `includes/inquiry_routing.php` are synced to the Hong Kong server and passed production PHP lint.
- Initial code sync to GitHub completed.
- `WORKFLOW_RULES.md` synced to server.
- `CODEX_CONTEXT.md` synced to server.

## Current Session Closeout

- The requested four-column footer navigation completion and link correction are complete.
- No open code changes are pending.
- Next session should continue using the default workflow:

```text
Local edit -> Git commit -> GitHub push -> Server deploy
```

## Standing Rule

Before ending a work session or when the user asks to stop/exit, update this file with the current context, then commit, push to GitHub, and sync to the server.
