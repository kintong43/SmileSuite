# Operational reports

Owners and receptionists can open **Reports** in their clinic workspace. Practitioners and patients cannot view or export the report. Clinic selection comes from the validated clinic hostname and signed-in account; a query-string tenant ID cannot change the scope.

Select inclusive **From date** and **To date**, then select **Update report**. The default is the first day of the current month through today in the clinic timezone. The range must contain at most 366 calendar days. Invalid dates, reversed dates, and larger ranges are rejected.

The report includes appointment counts, completed-visit counts, and scheduled value grouped by status, service, practitioner, and local appointment date. The appointment start determines the reporting day, using the clinic's current IANA timezone. UTC filtering includes the start of the first local day and excludes the start of the following local day, including daylight-saving changes.

**Scheduled value is not collected revenue.** It sums the price snapshots on appointments whose current status is not cancelled, including no-shows. Completed counts use the current completed status. Cancellation counts remain visible. Reports are a current operational view rather than an immutable historical accounting ledger.

The summary scheduled value uses the clinic's current currency. The currency table and all breakdowns keep historical appointment currencies separate; unlike currencies are never added together. Amounts on screen use two decimal places, following the application's existing minor-unit convention.

**Export CSV** downloads the selected date range. Each row contains one aggregate group, label, currency, appointment count, completed count, scheduled value in integer minor units, date range, and timezone. The four breakdown sections repeat the same appointments; do not total the entire CSV across groups. Cells beginning with spreadsheet formula characters or control prefixes receive an apostrophe. All query results are materialized before the streamed response runs, preserving the tenant boundary after request cleanup.

Patient names, patient IDs, patient contact details, intake, consent, clinical notes, and appointment-level rows are excluded. Service and practitioner names are operational labels and are escaped when rendered in HTML. These reports do not synchronize clinical records into the CMS.

Verification lives in `tests/Feature/ReportTest.php`, covering synthetic counts, local-midnight and DST boundaries, changing/empty date ranges, validation, currencies, authorization, clinic isolation, HTML escaping, and CSV formula safety. Run with `.runtime/php/php.exe artisan test --compact tests/Feature/ReportTest.php` against the isolated PostgreSQL test database.
