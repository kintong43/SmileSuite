# Coursework requirement audit

Sources read in full: `FINAL PROJECT REQUIREMENTS.docx` (Web Systems and Technologies 2 / System Integration and Architecture 1) and `Git_Lab2_Finals.pdf` (two pages, Laboratory Activity No. 2 – Finals). Source files are retained unchanged in the user's Downloads directory.

## Final project

| Requirement | Baseline finding | Work in this alignment |
| --- | --- | --- |
| Business proposal: problem, users, value, revenue | System plan describes SaaS business; no dedicated course proposal | Prepare concise proposal grounded in SmileSuite; prices remain unapproved |
| Responsive public/user website with overview, products/services, features | Landing and authenticated services/store/portal exist | Add explicit public content pages via CMS and improve navigation |
| Registration, login, profile management | Authentication exists; self-profile management absent | Implement profile and credential management |
| At least three major modules | Scheduling, clinical intake/notes, commerce, billing, clinic configuration | Preserve and document existing modules |
| Actual CMS platform for content, announcements, products/services, user data, reports | Custom Laravel admin exists; it is not a CMS-platform integration | Add Directus with public content and safe operational projections |
| System integration | Stripe/Shopify clients with fixtures, queued email; live gates disabled | Add verifiable local Directus HTTP integration; do not claim live provider proof |
| Five relational tables and ERD | More than five tables, tenant composite constraints; no course ERD deliverable | Derive schema, dictionary and ERDs from real PostgreSQL schema |
| Authentication/authorization/password protection/validation/sessions | Implemented and tested | Regression-check new boundaries; passwords remain hashed |
| Administrator/customer roles, content/users/reports/profile | Multiple roles and staff management; reports/profile/content gaps | Add report/profile/CMS views and documented permission boundaries |
| SRS and design documents | System plan/evidence exist; course-specific set absent | Add SRS, use case, context, architecture, ERD, schema and wireframes |

## Git lab

| Part | Verified baseline / requirement |
| --- | --- |
| 1 | Project folder and initialized local Git exist |
| 2 | README and .gitignore exist; student name and course/section require confirmation |
| 3 | Published initial commit `26e3a86` exists |
| 4 | Missing three feature branches; implement three new actual features with two commits each |
| 5 | PDF says "both" branches after requiring at least three; merge all three to meet the stronger consistent interpretation |
| 6 | Current remote is `kintong43/SmileSuite`; prescribed name is `ITE311_FINAL-PROJECT_SECTION_LastName_FirstName_` and needs the student's details plus a rename/new-repo decision |
| 7 | Initial main was pushed; new feature branches and merged main require a later authorized push |
| 8 | Instructor GitHub username not provided; invitation not sent or claimed |

The student name cannot safely be inferred from Git's configured author. Course titles in the final requirements and the Git lab's ITE311 repository prefix are recorded as written rather than silently reconciled.
