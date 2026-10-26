# Git lab evidence and upload checklist

**Students:** Kenji B. Rique and Samuael D. Luz — BSIT III F1  
**Repository:** [kintong43/SmileSuite](https://github.com/kintong43/SmileSuite)  
**Checked:** 27 October 2026

The original published history is preserved. These are real new implementations and local merge commits, not fabricated historical work. The README names both students, section, project, description and technologies. `.gitignore` excludes local credentials, runtimes, installed dependencies, databases, compiled assets, logs and test output.

| Lab item | Evidence / status |
| --- | --- |
| Initialize project/repository | Existing working repository and `git status` |
| README and .gitignore | Committed project metadata and exclusion rules |
| Initial commit | `26e3a86` — Initial SmileSuite application; already published by the student |
| Three feature branches, two commits each | Exact implementation commits below; verified count is **2 / 2 / 2** |
| Merge feature branches | All three are merged into local `main` with preserved merge commits |
| Prescribed GitHub name | **Pending** — current name is `SmileSuite`; lab specifies `ITE311_FINAL-PROJECT_SECTION_LastName_FirstName_` |
| Push main and all feature branches | **Pending for this new work** — remote main still points to `26e3a86`; no remote feature branches were listed by `git ls-remote` |
| Invite instructor | **Pending** — instructor GitHub username has not been supplied; no invitation sent |

## Actual implementation history

| Branch | Feature commits | Local main merge |
| --- | --- | --- |
| `feature/profile-management` | `36381d4` tenant-safe profile/password updates; `c32caf2` self-service profile/password screens | `8cbec80` |
| `feature/operational-reports` | `d66aeab` tenant-scoped aggregates/secure CSV; `50c2b45` report filters/dashboard/currency summaries | `c1f52a2` |
| `feature/cms-integration` | `f758d66` scoped Directus integration/provisioning; `15b0ecf` CMS pages/editor workflows/account hardening | `dbcec99` |

The final course documentation is committed separately on main. The PDF asks for at least three branches, then uses the word “both” for merging; all three were merged to satisfy the stronger consistent interpretation.

Review the preserved graph and feature counts:

```powershell
git log --graph --oneline --all
git branch --merged main
git rev-list --count 26e3a86..feature/profile-management
git rev-list --count 8cbec80..feature/operational-reports
git rev-list --count c1f52a2..feature/cms-integration
```

## Upload the prepared work

From `C:\Projects\SmileSuite`, inspect the status, fetch current remote references and upload all four branches:

```powershell
git status
git fetch origin
git push origin main feature/profile-management feature/operational-reports feature/cms-integration
```

If GitHub reports that the remote advanced, inspect and reconcile those commits before pushing. Do not force-push over somebody else's history. Authenticate privately with your GitHub credential manager when necessary. Verify the branches, latest main commit, and Actions result on GitHub after uploading; local tests do not establish remote CI status.

## Repository name and instructor access

The course requires `ITE311_FINAL-PROJECT_SECTION_LastName_FirstName_`. The section is III F1, and both students are recorded above. Confirm how the instructor wants a group repository named before choosing which name/section spelling to use. The established `SmileSuite` remote was not silently renamed. If renamed through GitHub, update `origin` to the resulting URL and recheck it with `git remote -v`.

When the instructor provides a GitHub username, open repository **Settings → Collaborators → Add people**, select the exact account and send the invitation. Record whether the invitation is pending or accepted; neither status can be inferred before it happens. This local preparation did not contact or invite anyone.
