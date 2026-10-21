# Top-level Module 5: Customer Experience

The confirmed menu sequence from the consolidated navigation specification is:

1. Contact Centre
2. Live Monitoring
3. Call Records
4. Recordings
5. Quality Assurance
6. Tickets
7. SLA
8. SMS
9. Feedback
10. CX Reports

## Implemented workflows

- **Contact Centre:** call logging, click-to-dial for authorized users, PBX extension mapping and sync, customer matching, call dispositions, follow-up ownership, QA form creation and call evaluation.
- **Live Monitoring:** supervisor view of mapped agent extension availability and recent calls. The page refreshes periodically and shows when PBX activity was last received.
- **Call Records:** organization-scoped, searchable-by-filter call register with direction, queue, status, customer, agent, duration, disposition and follow-up details.
- **Recordings:** access-controlled register of recording references and expiry dates. Audio is not copied or played by the ERP.
- **Quality Assurance:** configured scorecards and completed evaluation history; authorized reviewers evaluate calls in Contact Centre.
- **Tickets:** staff and customer portal intake, assignment, conversations, internal notes, attachments, lifecycle management and customer portal updates.
- **SLA:** policy setup for response and resolution targets, escalation-owner assignment, open breach queue and breach summaries. Current targets use elapsed time and do not pause for business hours or customer waiting.
- **SMS:** consent-gated and queued SMS through the configured TXTConnect gateway, with status, templates and delivery diagnostics logged to `storage/logs/sms.log`.
- **Feedback:** customer satisfaction scores and comments submitted from a private ticket portal after resolution, with an aggregate rating and low-score follow-up count.
- **CX Reports:** active-organization call, ticket, SLA, QA, satisfaction and SMS totals, limited to the viewer's module permissions.

## Access and integrations

The submenu uses existing permissions: `calls.view`, `calls.supervise`, `calls.recordings.view`, `calls.qa.manage`, `tickets.view`, `tickets.manage`, `tickets.reports`, `sms.view` and `sms.send`. Data queries are scoped to the user's active organization; pages requiring sensitive operations have separate permission checks.

PBX agent activity depends on signed activity events being sent to the ERP. The monitoring page reports the last event received and does not directly poll the PBX. Recording values remain references for use in the approved PBX interface. Ticket SLA checks require the Laravel scheduler. SMS delivery requires TXTConnect credentials and a worker listening on the `sms` queue.

The existing support, contact-centre and SMS migrations and role grants provide the required tables and permissions. This top-level consolidation adds routes and screens but does not reset or reseed application data. See [Module 07](module-seven.md), [Module 08](module-eight.md), and [Module 09](module-nine.md) for detailed provider and operational setup.
