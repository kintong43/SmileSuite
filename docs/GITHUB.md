# GitHub handoff

Target: `https://github.com/kintong43/SmileSuite_ITE311_FINAL-PROJECT_IIIF1_Rique_Luz_.git`

The prepared local repository uses branch `main` and remote `origin`. The source, dependency lockfiles, PostgreSQL tests, browser tests, documentation, and GitHub Actions workflow are included. Local runtimes, installed dependencies, compiled assets, test reports, logs, databases, backups, and `.env` files are excluded. `.env.example` contains development placeholders only.

After reviewing the local commit, upload it with:

```sh
git push origin main feature/profile-management feature/operational-reports feature/cms-integration
```

Authenticate using your own GitHub credentials or credential manager when prompted. No credentials belong in the remote URL or in committed files. If somebody adds commits to the remote before a push, fetch and reconcile that history; do not force-push over it.

GitHub Actions runs PHP 8.4 integration checks against disposable PostgreSQL 16 and Redis services on pushes and pull requests. The workflow's static test key and passwords are synthetic CI fixtures, not deployment credentials. Hosting and deployment are separate from uploading this repository.

For a new clone, follow the main README's clone-and-run instructions. `.runtime/`, `vendor/`, `node_modules/`, `.env`, and database contents are deliberately absent from source control.

The application and course alignment have been uploaded, including all three two-commit feature branches and their merges into main. On 31 October 2026, the repository was renamed to the exact group name selected by the student, and the local origin URL was updated. See [Git lab evidence and remaining steps](coursework/GIT_LAB_EVIDENCE.md) for actual commits, the selected repository name and the pending instructor username.
