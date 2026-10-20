# Module 15: Projects, Tasks & Team Productivity

## Access and setup

Open **Operations & Projects → Projects & tasks**. All records are scoped to the selected active organization. The Administrator role receives all Module 15 permissions. The Operations role receives project viewing, task management/assignment, comments, attachments, time entry and reports; assign `projects.manage`, `projects.approve` and `projects.risks.manage` to the appropriate project leads and approvers in **Administration → Roles & Permissions**.

Confirm each employee has an active ERP user account and organization membership before assigning them. Keep approval permissions separate from task ownership where deliverable sign-off requires independence. Private project files are encrypted with `APP_KEY` on local storage. Back up the application key and private storage together, and use a production retention policy.

## Create and plan work

1. Create a project with its owner, optional client, budget, dates, status and description.
2. Add the project team. A project owner is included as a member automatically.
3. Add workstreams for delivery areas and departments.
4. Add tasks with priority, dates, estimate and owner. Select an existing task as the parent to create a subtask.
5. Use each task card's status control to move work across the Kanban stages. Add a dependency to show which task must be completed first. Cyclic dependencies are rejected.

## Collaboration, time and approval

Project members with `projects.comments` can comment and mention other members. Users with `projects.attachments` can upload PDF, text, CSV, image and Office files up to 10 MB; the app encrypts file contents before storing them. Downloads are authorized and audited.

Task owners may record their own time; `projects.time.manage` grants organization-wide entry. Entries include work date, hours and optional note. The board shows actual hours against estimate, and the project page lists recent entries.

Mark a task as a deliverable when it needs sign-off. Deliverables cannot be set to **Done** until a user with `projects.approve` approves them. Milestones can be created with approval required and signed off by an authorized approver.

## Risks and reports

Users with `projects.risks.manage` can record risks and issues with likelihood, impact, owner, due date and mitigation. Users with `projects.reports.view` can see active project, overdue task and logged-hour summaries. Project completion is shown per project.

The module does not calculate invoices from budgets or timesheets, send mention/reminder notifications, automatically reschedule dependent tasks, scan uploads for malware, or connect to external project services.
