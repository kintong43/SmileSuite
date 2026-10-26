# Coursework Alignment Implementation Plan

> **For agentic workers:** Use superpowers:subagent-driven-development for bounded modules. Root owns Git branch changes, commits, merges, and test database scheduling.

**Goal:** Close the verified local software and documentation gaps in the two supplied coursework requirements, with genuine feature-branch history.

**Architecture:** Retain the Laravel/PostgreSQL clinic boundary. Add self-service account profiles, aggregate operational reporting, and a separate Directus CMS connected only to public/operational projections. Clinical records and authentication remain in Laravel.

**Spec:** `FINAL PROJECT REQUIREMENTS.docx` and `Git_Lab2_Finals.pdf`, read from the user's Downloads directory; requirement mapping in `docs/coursework/REQUIREMENTS.md`.

## Global constraints
- Existing tenant, patient ownership, MFA, encrypted clinical fields, scheduling constraints, and live-provider gates remain in force.
- Use three new feature branches with at least two real implementation commits each, merge all three into main, preserve published history.
- Do not invent student details, instructor identity, invitation status, remote repository naming compliance, or live-provider evidence.
- Documentation is explicitly requested by the user's acceptance of checking and implementing the attached requirements.
- Existing project directories remain the home of PHP code, views, routes, tests and documentation. CMS support configuration lives under `docker/directus` and scripts under `scripts`; no new root application framework.

## Tasks
- [x] `feature/profile-management`: account name/contact/password management, ownership-safe policies, session revocation, tests; then profile UI/navigation and user guide. Two implementation commits.
- [x] `feature/operational-reports`: date-scoped aggregate report service and owner/receptionist authorization tests; then report screen/CSV export and tests. Two implementation commits.
- [x] `feature/cms-integration`: pinned Directus sidecar configuration, collections/policies/bootstrap and tenant-safe Laravel API client; then public content/announcements/service descriptions, CMS status/sync projections and tests. Two implementation commits.
- [x] Business proposal, SRS, use case/ER/context/architecture diagrams, schema dictionary and grayscale wireframes based on actual implementation.
- [x] Full local verification, requirement evidence matrix, genuine branch/commit/merge evidence, and a precise remaining-submission checklist.

## Review focus
- Self-profile requests cannot mutate roles, tenant IDs, another user or clinical history.
- Reports contain aggregate operational values, not collected-revenue claims or clinical content; date filters honor clinic timezone.
- CMS permissions and Laravel filtering both isolate clinics; no patient records, secrets or authentication hashes leave Laravel.
- CMS outages do not break booking; failed CMS publishing cannot claim live content success.
- Student metadata, prescribed repo name, pushing all branches and instructor invitation need verified evidence rather than placeholder claims.

## Rulings / progress
- User authorized implementation of missing coursework requirements. Proceed with reversible local implementation; CMS preference and submission metadata were requested asynchronously.
- Directus is the recommended default because it preserves the existing PostgreSQL/Laravel architecture. No competing CMS preference was provided at task start.
- Bootstrap AGENTS.md explicitly required Laravel Boost installation. Installed the development package and generated/read the project-specific guidelines before application edits.
- No remote rename, upload, collaborator invitation, or external mail is performed merely because the attachments describe those submission steps.
- Completed local course alignment: 148 PHP tests / 773 assertions; six browser workflows including real Directus editor isolation/publishing; 20 rendered figures from 41 tables / 70 foreign keys. Exact evidence and remaining remote steps are in docs/coursework.
- Both students confirmed: Kenji B. Rique and Samuael D. Luz, BSIT III F1. Instructor username remains unavailable. All three genuine feature branches contain two commits and are merged locally; upload and prescribed remote name remain pending.
- Directus 12 license/API evaluation rejected the required custom policies. Pinned 11.17.4 and initialized a fresh separate CMS database; no license gate bypass. Windows CMS uses the verified official Node 22 runtime and compatible native module. Docker alternative remains unexecuted here.
