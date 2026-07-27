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

- `89310a2` - Open series quote CTA in inquiry modal
- `aa79f0a` - Initial sync from Hong Kong website server
- `45f65a1` - Document local GitHub server workflow
- `8dbc2d9` - Add Codex context handoff rule

## Server Sync Status

- `includes/floating_actions.php` synced to the Hong Kong server and verified on the live ARMI page.
- Initial code sync to GitHub completed.
- `WORKFLOW_RULES.md` synced to server.
- `CODEX_CONTEXT.md` synced to server.

## Current Session Closeout

- The requested ARMI `Get a Quote` popup behavior is complete.
- No open code changes are pending.
- Next session should continue using the default workflow:

```text
Local edit -> Git commit -> GitHub push -> Server deploy
```

## Standing Rule

Before ending a work session or when the user asks to stop/exit, update this file with the current context, then commit, push to GitHub, and sync to the server.
