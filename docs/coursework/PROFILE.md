# Profile management

Authenticated clinic members can open **My profile** from the sidebar. Patients may edit their own contact details; staff may edit their own account name and email. Clinical records and roles use their existing authorized workflows.

Email changes require the current password, clear verification, rotate the session revocation token, and send a signed verification link through Laravel mail. Password changes require the current password and a confirmed password of at least 12 characters including letters and numbers, then revoke sessions and require a new login. Audit events contain action metadata without passwords or contact values.

The tenant is resolved from the host, and the account comes from the authenticated session. Request-supplied account IDs, tenant IDs, status, and roles are rejected. Email uniqueness applies within each clinic.

Verification: `php artisan test --compact tests/Feature/ProfileTest.php` covers contact updates, the profile page, unauthorized fields, email uniqueness and verification, password checks and rotation, guest access, and cross-clinic rejection.
