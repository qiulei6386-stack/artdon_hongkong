# Artdon Hong Kong Workflow Rules

## Default Change Flow

All website code changes should follow this order:

1. Modify files locally.
2. Check locally where possible.
3. Commit changes locally with Git.
4. Push the commit to GitHub.
5. Sync the changed files to the Hong Kong server.

Default flow:

```text
Local edit -> Git commit -> GitHub push -> Server deploy
```

## Server Rule

Do not edit production server files directly unless:

- It is an urgent production fix, or
- The user explicitly asks to edit the server directly.

If server-side verification is needed, run checks on the server after local changes are synced.

## Files Excluded From GitHub

The following should not be committed to GitHub:

- `uploads/`
- `storage/`
- `website_config.php`
- `.user.ini`
- SQL dumps
- ZIP / archive files
- backups
- temporary repair scripts
- local Codex helper files

## Completion Report

After each change, report:

- Files changed
- GitHub commit hash
- Whether the change was pushed to GitHub
- Whether files were synced to the server
- Verification result

## Context Handoff Rule

Before ending a work session or when the user asks to stop/exit, update the project context handoff file:

- `CODEX_CONTEXT.md`

The context handoff should record:

- Current workflow rule status
- Latest completed task
- Latest GitHub commit hash
- Whether the server has been synced
- Open issues / pending next steps
- Important operational notes

This file should be committed to GitHub and synced to the server as part of the normal workflow.
