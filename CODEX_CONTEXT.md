# Codex Context Handoff

Last updated: 2026-07-24

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

- Initialized local Git repository.
- Connected GitHub with SSH.
- Synced server code to local.
- Excluded uploads, storage, runtime config and backup files from GitHub.
- Pushed initial server code to GitHub.
- Added workflow rules.

## Latest Known Commits

- `aa79f0a` - Initial sync from Hong Kong website server
- `45f65a1` - Document local GitHub server workflow

## Server Sync Status

- Initial code sync to GitHub completed.
- `WORKFLOW_RULES.md` synced to server.
- This context file should also be synced to server after commit.

## Standing Rule

Before ending a work session or when the user asks to stop/exit, update this file with the current context, then commit, push to GitHub, and sync to the server.
