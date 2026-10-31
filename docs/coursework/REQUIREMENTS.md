# Coursework requirement audit

Sources read in full: `FINAL PROJECT REQUIREMENTS.docx` (Web Systems and Technologies 2 / System Integration and Architecture 1) and `Git_Lab2_Finals.pdf` (two pages, Laboratory Activity No. 2 – Finals). Source files are retained unchanged in the user's Downloads directory.

## Final project

| Requirement | Baseline finding | Implemented evidence |
| --- | --- | --- |
| Business proposal: problem, users, value, revenue | No dedicated course proposal | [Business proposal](BUSINESS_PROPOSAL.md); commercial prices remain unapproved |
| Responsive public/user website with overview, products/services, features | Landing and authenticated services/store/portal existed | CMS-backed About, Features, Announcements & services; public navigation and browser checks |
| Registration, login, profile management | Self-profile management absent | [Profile and credential management](PROFILE.md), with password checks, verification and session/reset-link revocation |
| At least three major modules | Scheduling, clinical intake/notes, commerce, billing, clinic configuration | Existing modules retained; reports and CMS added; [SRS](SRS.md) maps requirements |
| Actual CMS platform for content, announcements, products/services, user data, reports | Custom Laravel admin alone did not satisfy platform integration | [Directus 11.17.4](CMS.md), five collections, separate editor identities; user data represented by safe staff display-name/role directory; patient and credential data remain in Laravel |
| System integration | Existing Stripe/Shopify fixtures and queued email | Executed real local Directus REST and browser publishing/isolation checks; no live payment/store claim |
| Five relational tables and ERD | No course ERD deliverable | [Schema/dictionary](DATABASE_SCHEMA.md), 41 actual tables, 70 physical foreign keys, eight domain ERDs |
| Authentication/authorization/password protection/validation/sessions | Existing framework/application controls | Full regression plus new scope, role, profile, reset-token and password-attempt tests; [verification](VERIFICATION.md) |
| Administrator/customer roles, content/users/reports/profile | Reports/profile/content gaps | Owner/staff/patient roles, separate CMS editors and platform operators; aggregate reports and profile screens |
| SRS and design documents | Course-specific set absent | [SRS](SRS.md), [system design](SYSTEM_DESIGN.md), proposal, schema, 20 figures, [demo guide](DEMO_GUIDE.md) |

## Git lab

| Part | Verified baseline / requirement |
| --- | --- |
| 1 | Project folder and initialized local Git exist |
| 2 | README includes Kenji B. Rique, Samuael D. Luz, BSIT III F1, description and technology stack; .gitignore excludes credentials and local/generated files |
| 3 | Published initial commit `26e3a86` exists |
| 4 | Three genuine feature branches, at least two implementation commits each; exact commits in [Git evidence](GIT_LAB_EVIDENCE.md) |
| 5 | All three feature branches merged locally into main; PDF's inconsistent "both" wording is resolved by merging all three |
| 6 | Renamed on 31 October 2026 to the student-selected group name `SmileSuite_ITE311_FINAL-PROJECT_IIIF1_Rique_Luz_`; lab's individual template remains `ITE311_FINAL-PROJECT_SECTION_LastName_FirstName_` |
| 7 | Main and all three feature branches verified on GitHub on 31 October 2026 |
| 8 | Instructor GitHub username not provided; invitation not sent or claimed |

Both student names, section and the exact group repository name were supplied by the user. The instructor has not supplied a GitHub username, so the invitation remains pending. Course titles and the Git lab's individual naming template are recorded as written; instructor acceptance of the selected group variation has not been independently established. Repository and branch upload status was checked directly on GitHub, separately from local application tests.
