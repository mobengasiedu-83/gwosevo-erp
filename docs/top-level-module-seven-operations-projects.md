# Top-level Module 7 — Operations & Projects

**Status:** Local implementation consolidated from the existing project delivery module. No production deployment is implied.

## Menu

The **Operations & Projects** menu follows the approved structure: **Projects, Tasks, Milestones, Timesheets, Risks, Deliverables, Operations Reports**. The group Overview remains available and summarizes active projects and open tasks when the corresponding modules and permissions are available.

Menu entries are shown according to module availability and the current user's project permissions. Project lists and detail links are limited to projects the user can access in their active organization. Organization-wide reports require `projects.reports.view`.

## Workflows

- **Projects:** create and manage organization projects, owners, members, workstreams, client, budget, dates and status.
- **Tasks:** create tasks and subtasks, assign owners, set due dates and estimates, record priority and status, and track dependencies. Circular dependency chains are rejected.
- **Milestones:** record project checkpoints and dates, track status, and require sign-off where configured.
- **Timesheets:** record dated hours against an assigned task and view entries in the authorized project scope. Project owners can record their own time; `projects.time.manage` allows broader entry.
- **Risks:** record risks and issues with probability, impact, owner, due date and mitigation.
- **Deliverables:** identify deliverable tasks that need approval. They cannot be marked complete before approval.
- **Operations Reports:** view project/task counts, overdue tasks, milestone and risk totals, hours logged, task status and open task workload.

Project details provide the corresponding write and review actions, including member assignment, task collaboration, encrypted attachments, risk entry, time logging, milestone approval and deliverable approval. Organization and project authorization checks apply on detail and action routes.

## Existing boundaries

Reports show recorded operational data; they do not implement budget variance, forecasts or a financial ledger. Time entries do not currently have an approval or period-close workflow. Project approvals cover configured deliverables and milestones. Automatic dependency scheduling, mention notifications, due-date email reminders, drag-and-drop boards, malware scanning and external project-tool integrations are not part of this implementation. Configure the required policies and operating procedures before production use.

## Local verification

After pulling the local changes, open **Operations & Projects → Overview** and visit each submenu using a project manager, project member, task owner and report viewer account. Existing project tables and permissions are used; this consolidation adds no database migration.
