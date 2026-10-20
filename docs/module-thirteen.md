# Module 13 — Performance Management & Appraisals

**Status:** Installed locally · **Navigation:** Human Resources → Performance & appraisals · **Route:** `/performance`

## Permissions

The `Human Resources` role receives `performance.view`, `performance.reviews.self`, `performance.reviews.manager`, `performance.kpis.manage`, `performance.feedback.manage`, `performance.feedback.respond`, `performance.plans.manage`, and `performance.reports.view` when `IdentitySeeder` runs. `performance.manage` is granted separately for cycle administration. Assign `performance.calibrate` and `performance.reviews.approve` to authorized, independent reviewers. The superadmin bypass grants all application permissions.

Employees need `performance.view` and `performance.reviews.self`, plus an HR employee record linked to their ERP account. Managers need `performance.view` and `performance.reviews.manager`; their employee record must be selected as the report's manager and linked to their ERP account. Cycle owners need `performance.manage`.

## Create and run a cycle

1. Open **Performance & appraisals** and create a cycle with a unique name, period type, dates, and goal/competency weights. The weights must total 100 percent.
2. Open the draft cycle. The system creates one review for each active employee in that organization and snapshots the employee's current manager link.
3. Employees open their review, add a self-appraisal, self score, competency scores and up to three SMART goals, then submit it.
4. The assigned manager adds an assessment and rates each goal. The system combines the weighted average of goal attainment and competency scores using the cycle weights.
5. A user with `performance.calibrate` may adjust the score and record the reason. A user with `performance.reviews.approve` approves the calibrated score. The assigned manager cannot approve that same review.

Approved reviews stay available in the organization's review history. Active employees without a linked ERP user receive a review record but cannot submit a self-appraisal until their HR profile is linked.

## KPI library, 360 feedback and plans

Users with `performance.kpis.manage` can maintain role or department measures. KPI definitions are a reference library; the current goal form does not automatically copy KPI targets into goals.

Users with `performance.feedback.manage` can create an internal feedback request using the reviewer's ERP user ID and a relationship type. The reviewer must be an active member of the same organization. They submit their response and score from **My 360 feedback requests**. The response is encrypted and not shown in the review screen.

Users with `performance.plans.manage` may add an improvement or development plan and optionally reference an existing HR training course. Plan actions are encrypted. The current version stores plan dates and follow-up notes but does not provide milestone completion or reminder automation.

## Evidence and privacy

Authorized employees, assigned managers and HR users can attach a PDF, Word/Excel file, image or text file up to 10 MB to the review. Evidence is encrypted using `APP_KEY` on Laravel's private local disk. Downloads are permission checked and audit logged. Keep the production application key and encrypted private storage backups together; losing the key makes the evidence unreadable.

Before production use, establish the cycle calendar, approval separation, retention period, employee notice and evidence-handling process with HR. The installed starter version has fixed competency categories and basic completion counts. It does not send employee notifications, schedule calibration meetings, scan files for malware, support external 360 respondents, or provide longitudinal trend charts.
