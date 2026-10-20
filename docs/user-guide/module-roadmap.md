# GWO SEVO ERP — Complete Module Catalogue and Roadmap

**Edition:** 20 October 2026. [Return to the user guide](README.md).

The feature tables below reproduce the supplied Module Feature Breakdown as a requirements reference. Modules 01–15 are installed within the boundaries explained in the user guide. Modules 16–22 are planned.

## Module 01 System Administration & Configuration

**Status:** Installed. See the main user guide for operating instructions and configuration/commissioning boundaries.

**Purpose:** Central management of ERP settings.

**Priority:** Foundation

| Specified feature | Required functionality / planned scope |
| --- | --- |
| General Settings | Company profile, logo, contact details, time zone, date formats |
| Branding | Login screen, templates, PDF branding |
| System Preferences | Currency, financial year, pagination and numbering |
| Module Management | Module configuration and controlled enablement |
| Custom Fields | Administrator-defined fields for supported records |
| Reference Data | Countries, regions, industries and customer categories |
| Number Sequences | Lead, customer, ticket, quotation and invoice references |
| Data Import/Export | CSV/Excel mapping, validation and controlled export |
| System Health | Queue, scheduler, database and storage checks |
| Maintenance | Maintenance mode and operational notices |

## Module 02 Identity, Users, Roles & Permissions

**Status:** Installed. See the main user guide for operating instructions and configuration/commissioning boundaries.

**Purpose:** Authentication, authorization and access governance.

**Priority:** Foundation

| Specified feature | Required functionality / planned scope |
| --- | --- |
| User Management | Invite, activate, suspend and deactivate users |
| Roles | Built-in and configurable business roles |
| Permissions | View, create, edit, archive, export, approve and assign |
| Data Scope | Own, team, department and organization access |
| Authentication | Login, reset, MFA and session management |
| SSO | Microsoft Entra ID / Microsoft 365 login |
| Delegation | Time-limited approval delegation |
| Access Reviews | Periodic role and privilege recertification |
| Audit | Login attempts, access and role changes |

## Module 03 Organization, Departments & Branches

**Status:** Installed. See the main user guide for operating instructions and configuration/commissioning boundaries.

**Purpose:** Organizational structure and reporting relationships.

**Priority:** Foundation

| Specified feature | Required functionality / planned scope |
| --- | --- |
| Company Structure | Legal entities and operating organizations |
| Departments | Functional units and management ownership |
| Branches | Office and operational locations |
| Teams | Team membership and supervisors |
| Positions | Titles, grades and job descriptions |
| Reporting Hierarchy | Managers and approval lines |
| Cost Centres | Revenue and cost allocations |
| Organization Chart | Reporting-line visualization |

## Module 04 CRM & Lead Management

**Status:** Installed. Manual, signed website and authenticated API capture, approved CSV import, profile/qualification/scoring, duplicate review, assignment rules and round-robin routing, activities/follow-ups, conversion, disqualification, archive/restore and scoped reports are available. Integration secrets, scheduler operation and role grants require operator configuration.

**Purpose:** Manage prospects from enquiry through qualification.

**Priority:** Phase 1

| Specified feature | Required functionality / planned scope |
| --- | --- |
| Lead Capture | Manual, website, API and approved imports |
| Lead Profile | Contact details, company, industry and interests |
| Lead Sources | Campaign, referral, inbound call, event and website |
| Lead Assignment | Manual, rules-based and round-robin routing |
| Qualification | Configurable budget, authority, need and timeline |
| Lead Scoring | Fit and engagement signals |
| Duplicate Detection | Email, phone and company matching |
| Activities | Calls, meetings, notes, tasks and reminders |
| Follow-ups | Due dates, overdue alerts and escalation |
| Lead Conversion | Account, contact and opportunity creation |
| Reporting | Source, conversion, ageing and owner productivity |

## Module 05 Sales Pipeline & Opportunities

**Status:** Installed. Opportunity capture, stage pipeline, assignment, service mapping, activities/reminders, competitor tracking, commercial approvals, targets, weighted forecasts, win rate, sales velocity, forecast accuracy snapshots and scoped reports are available. Approved service catalog names, quota values, access grants and continuous scheduler operation require setup.

**Purpose:** Manage deals, forecasting and commercial performance.

**Priority:** Phase 1

| Specified feature | Required functionality / planned scope |
| --- | --- |
| Opportunities | Value, probability, owner and expected close |
| Pipeline | Kanban and list views |
| Stages | New through Won/Lost and On Hold |
| Service Mapping | Four Strategic Service Lines and seven ISPs |
| Forecasting | Weighted pipeline and closing forecasts |
| Competitors | Win/loss and competitor tracking |
| Approvals | Discounts and nonstandard terms |
| Activities | Meetings, calls and next actions |
| Targets | Sales quotas by person and team |
| Analytics | Win rate, velocity and forecast accuracy |

See [Module 05 setup and user guide](../module-five.md). The specifications require four strategic service lines and seven integrated service packages, but do not provide their names; the catalog is therefore empty until an administrator enters the approved company names.

**Before operational use:** configure approved service names and targets, review permissions and stage probabilities, and accept the discount/terms workflow with Sales and Finance.

## Module 06 Quotations, Contracts & Client Onboarding

**Status:** Installed. See [Module 06 setup and user guide](../module-six.md).

**Purpose:** Turn opportunities into contracted and onboarded clients.

**Priority:** Phase 1

| Specified feature | Required functionality / planned scope |
| --- | --- |
| Service Catalogue | Products, services and integrated packages |
| Pricing | Fixed, recurring, usage-based and negotiated pricing |
| Quotations | Draft, version, approve and print-to-PDF export |
| Discount Controls | Independent approval with justification; configure thresholds with business owners |
| Contracts | Templates, documents and renewal dates |
| Service Agreements | SLA terms and deliverables |
| Onboarding | Documents, checklists and approvals |
| Handover | Commercial to Technical/Operations transition |
| Renewals | Expiry alerts and renewal opportunities |
| Client Portal | Permitted contract and onboarding views |

**Before operational use:** configure approved templates, pricing and role permissions, run the scheduler, and accept the quote, agreement, onboarding, handover and client portal workflows with business owners.

## Module 07 Contact Centre & VitalPBX

**Status:** Installed with the boundaries in the [Module 07 operations guide](../module-seven.md). PBX provider activation requires approved API/interface details.

**Purpose:** Link telephony operations to customer records.

**Priority:** Phase 2

| Specified feature | Required functionality / planned scope |
| --- | --- |
| PBX Connections | Secure approved VitalPBX connection profiles |
| Agent Mapping | ERP users mapped to PBX extensions |
| Call Records | Inbound, outbound, missed and abandoned |
| Click-to-Call | Authorized PBX-initiated dialing |
| Screen Pop | Customer lookup by calling number |
| Disposition | Call outcome, notes and follow-up |
| Recordings | Permission-controlled references and retention |
| Agent Activity | Availability and operational status where supported |
| Queue Analytics | Service level, waiting and abandoned calls |
| Quality Assurance | Evaluation forms and scoring |
| Supervisor Dashboard | Agent and queue performance |

**Before operational use:** review HR permissions and retention rules, configure the organization structure, assign the Human Resources role only to authorized staff, and ensure production storage permissions and encrypted backups protect the Laravel `APP_KEY` and private document files. Disable application accounts separately during offboarding.

## Module 08 Customer Support & Ticketing

**Status:** Installed. See [Module 08 setup and operations](../module-eight.md).

**Purpose:** Track service issues against ownership and SLAs.

**Priority:** Phase 2

| Specified feature | Required functionality / planned scope |
| --- | --- |
| Ticket Intake | Staff portal, customer portal, authenticated API/email bridge and call-linked |
| Categories | Technical, billing, complaint and request |
| Priorities | Low, normal, high and critical |
| Assignment | Team, agent and escalation owner |
| Lifecycle | Open, assigned, in progress, waiting, resolved, closed |
| SLA | Configurable response and resolution targets by priority |
| Escalations | Scheduled breach detection, event history and owner routing |
| Conversations | Internal notes and customer messages |
| Attachments | Private file storage and authorized downloads |
| Satisfaction | One-time post-resolution customer feedback |
| Analytics | SLA breaches, reopen counts and average resolution time |

**Before operational use:** configure ticket categories and SLA targets, grant ticket permissions, start the Laravel scheduler, and connect any inbound support mailbox to the authenticated ticket intake API.

## Module 09 SMS & Communication Management

**Status:** Installed locally with TXTConnect sending, consent management, templates, scheduled campaigns, queue processing and message history. Provider delivery receipts and automated application-triggered notifications remain commissioning items.

**Purpose:** Manage authorized SMS through TXTConnect.

**Priority:** Phase 2

| Specified feature | Required functionality / planned scope |
| --- | --- |
| Provider Settings | Secure TXTConnect endpoint and sender configuration |
| Templates | Approved transactional and campaign templates |
| Transactional SMS | Reminders, alerts and confirmations |
| Bulk Messaging | Consent-based campaigns and recipient validation |
| Scheduling | Future message and batch execution |
| Queue Processing | Redis jobs and worker supervision |
| Status Tracking | Provider-supported delivery events |
| Retry & Idempotency | Bounded retries and duplicate prevention |
| Consent | Preferences, opt-outs and suppression |
| Usage Reports | Submission, failures and costs |
| History | Associate messages with ERP records |

**Before operational use:** verify TXTConnect credentials and sender ID, grant narrowly scoped SMS permissions, obtain and record consent evidence, configure the scheduler and queue worker, and confirm delivery-status API details with TXTConnect.

## Module 10 Marketing, Campaigns & Lead Generation

**Status:** Installed locally. Campaign planning, scoped CRM segments, campaign tasks and budgets, landing-page lead capture, affiliate attribution, and channel-specific consent records are available. Outbound campaign delivery and commission settlement are not included.

**Purpose:** Measure acquisition campaigns and lead attribution.

**Priority:** Phase 2

| Specified feature | Required functionality / planned scope |
| --- | --- |
| Campaigns | Objectives, owner, dates and budget |
| Channels | Email, SMS, digital, event and referral |
| Landing Pages | Campaign-specific lead forms |
| Audience Segments | Permission-controlled CRM filtering |
| Consent | Contact permissions and opt-outs |
| Attribution | Source and campaign association |
| Tasks | Campaign execution work items |
| Budget | Planned versus actual spend |
| Conversions | Leads, opportunities and won revenue |
| Affiliates | Referral attribution and approved commissions |

**Before operational use:** configure marketing segmentation and consent procedures, channel permissions, campaign budgets, and the related communication provider integrations.

## Module 11 Human Resources & Employee Records

**Status:** Installed locally with restricted employee, employment-contract, private document, assignment-history, onboarding/offboarding checklist, disciplinary-case, training and headcount/tenure/turnover workflows. Account deactivation remains an explicit operation in Users & Access.

**Purpose:** Maintain protected employee records and lifecycle.

**Priority:** Phase 3

| Specified feature | Required functionality / planned scope |
| --- | --- |
| Employee Directory | Staff profiles and status |
| Personal Information | Contact, emergency and identification details |
| Contracts | Employment terms and renewals |
| Assignment | Team, position, department and manager |
| Documents | Restricted contracts and certificates |
| Job History | Promotion, transfer and grade changes |
| Onboarding | New-hire tasks and approvals |
| Offboarding | Clearance, access revocation and asset return |
| Disciplinary Cases | Restricted HR case tracking |
| Training | Courses and certifications |
| Reports | Headcount, tenure and turnover |

**Before operational use:** approve the provider interface, event mapping, lawful recording policy and role assignments; configure the encrypted connection profile and signing secret; then verify events against the provider.

## Module 12 Recruitment & Assessments

**Status:** Installed locally. Requisitions, approval decisions, published vacancies and public applications, candidate screening, interview scheduling/scoring, timed assessments, practical and speech submissions, offer approval/response, employee conversion, and scoped funnel/time-to-hire reporting are available.

**Purpose:** Manage vacancies, candidates and hiring decisions.

**Priority:** Phase 3

| Specified feature | Required functionality / planned scope |
| --- | --- |
| Requisitions | Hiring requests and approval |
| Vacancies | Job requirements and application windows |
| Applicant Tracking | Applications and candidate stages |
| Screening | Eligibility and qualification review |
| Interviews | Scheduling, panel notes and scoring |
| Online Tests | Timed assessments and question banks |
| Practical Tests | Role-specific evaluations |
| Speech Assessment | Audio submission and assessor grading |
| Integrity Controls | Consent-based, legally reviewed monitoring |
| Offers | Approval and issuance |
| Hiring Conversion | Successful applicant to employee |
| Reports | Time-to-hire and funnel performance |

**Before operational use:** run the Module 12 migration and the additive `IdentitySeeder`; authorize the Human Resources and independent approver roles; set the candidate privacy, consent, retention and data-access rules; and agree the assessment questions, interview scoring rubric, offer approval authority, and candidate communication process.

## Module 13 Performance Management & Appraisals

**Status:** Installed locally with the boundaries in the [Module 13 operations guide](../module-thirteen.md).

**Purpose:** Set goals, evaluate performance and track improvement.

**Priority:** Phase 3

| Specified feature | Required functionality / planned scope |
| --- | --- |
| Review Cycles | Monthly, quarterly, biannual and annual |
| KPI Library | Role and department measures |
| Goals | SMART goals, weights and deadlines |
| Self-Appraisal | Employee submissions |
| Manager Review | Supervisor evaluation |
| 360 Feedback | Configurable peer/stakeholder input |
| Competencies | Communication, teamwork, leadership and skills |
| Weighted Scores | Configurable scoring models |
| Evidence | Supporting records and attachments |
| Calibration | HR/management moderation |
| Approval | Review sign-off workflow |
| Improvement Plans | PIP milestones and follow-up |
| Development Plans | Training and growth actions |
| History & Analytics | Trends, completion and attainment |

**Before operational use:** approve review-cycle timing and scoring weights, assign HR cycle permissions and independent calibration/approval roles, verify employee-manager links and linked ERP accounts, and set evidence, privacy and retention rules.

## Module 14 Attendance, Leave & Employee Self-Service

**Status:** Installed locally with the boundaries in the [Module 14 operations guide](../module-fourteen.md).

**Purpose:** Enable time, leave and employee requests.

**Priority:** Phase 3

| Specified feature | Required functionality / planned scope |
| --- | --- |
| Attendance | Clock-in/out and exceptions |
| Shifts | Schedules and rotations |
| Leave Types | Annual, sick and configurable categories |
| Leave Requests | Submit, approve, reject and cancel |
| Balances | Accrual, entitlement and carryover |
| Holiday Calendar | Ghana and company holidays |
| Timesheets | Time by project and activity |
| Employee Portal | Personal records, leave and appraisal |
| Manager Portal | Team attendance and approvals |
| Reports | Absence, lateness and leave utilization |

**Before operational use:** set annual entitlements and carryover rules, assign self-service and independent approval permissions, verify employee-to-user and manager links, configure shifts and the organization holiday calendar, and establish attendance correction and retention practices.

## Module 15 Projects, Tasks & Team Productivity

**Status:** Installed locally. See the [Module 15 operations guide](../module-fifteen.md).

**Purpose:** Coordinate work and track delivery.

**Priority:** Phase 4

| Specified feature | Implemented functionality |
| --- | --- |
| Projects | Owner, client, budget and timeline |
| Workstreams | Departmental delivery areas |
| Tasks | Owner, priority, due date and status |
| Subtasks | Hierarchical work breakdown |
| Milestones | Deliverables and deadlines |
| Dependencies | Blocked and blocking tasks |
| Kanban | Board workflow |
| Time Tracking | Estimate and actual hours |
| Collaboration | Comments, mentions and attachments |
| Approvals | Deliverable sign-off |
| Risks | Risk and issue registers |
| Reports | Overdue, workload and completion |

**Before operational use:** configure project permissions, organization memberships, independent approval responsibilities, attachment retention and backup practices.

## Module 16 Finance, Invoicing & Revenue Management

**Status:** Planned — not installed. No operational screens or end-to-end user procedures are available yet.

**Purpose:** Operational billing, revenue and budget controls.

**Priority:** Phase 4

| Specified feature | Required functionality / planned scope |
| --- | --- |
| Invoices | Draft, approve, issue, pay and overdue |
| Recurring Billing | Schedules and service subscriptions |
| Credit Notes | Controlled reversals |
| Payments | Allocation and reconciliation |
| Expenses | Claims and approval |
| Revenue | Client, service and department analysis |
| Budgets | Allocation and variance |
| Approval Limits | Financial authorization |
| Taxes | Configurable codes and rules |
| Receivables | Outstanding balances and ageing |
| Exports | Accounting and spreadsheet output |
| Dashboard | Collections, receivables and budget use |

**Before operational use:** approve the provider interface, event mapping, lawful recording policy and role assignments; configure the encrypted connection profile and signing secret; then verify events against the provider.

## Module 17 Procurement, Assets & Inventory

**Status:** Planned — not installed. No operational screens or end-to-end user procedures are available yet.

**Purpose:** Track purchasing and company assets.

**Priority:** Phase 4

| Specified feature | Required functionality / planned scope |
| --- | --- |
| Suppliers | Vendor profiles and due diligence |
| Purchase Requests | Department requests and approval |
| Purchase Orders | Approved ordering |
| Goods Receipt | Receipt and discrepancies |
| Asset Register | Computers, phones, network equipment and furniture |
| Assignments | Employee and location custody |
| Maintenance | Service and repairs |
| Inventory | Stock balances and locations |
| Movements | Transfers and adjustments |
| Disposal | Authorization and retirement |
| Reports | Spend, assets and stock |

**Before operational use:** approve the provider interface, event mapping, lawful recording policy and role assignments; configure the encrypted connection profile and signing secret; then verify events against the provider.

## Module 18 Knowledge Bank & AI Search

**Status:** Planned — not installed. No operational screens or end-to-end user procedures are available yet.

**Purpose:** Controlled organizational knowledge and retrieval.

**Priority:** Phase 5

| Specified feature | Required functionality / planned scope |
| --- | --- |
| Knowledge Spaces | Team, department, project and shared |
| Documents | PDF, Word, text and supported uploads |
| Articles | Draft, review, publish and archive |
| Taxonomy | Categories and tags |
| Versions | Revision and publication history |
| Permissions | Document and space-level access |
| Keyword Search | Full-text and metadata search |
| Semantic Search | Qdrant-backed retrieval |
| AI Assistant | Answers grounded in permitted documents |
| Citations | Traceable source links |
| Microsoft 365 Sync | Approved SharePoint/OneDrive/Teams content |
| Sync Governance | Change/deletion propagation and logs |
| Feedback | Relevance and quality ratings |
| Analytics | Search usage and unanswered queries |

**Before operational use:** approve the provider interface, event mapping, lawful recording policy and role assignments; configure the encrypted connection profile and signing secret; then verify events against the provider.

## Module 19 Reports, Dashboards & Business Intelligence

**Status:** Planned — not installed. No operational screens or end-to-end user procedures are available yet.

**Purpose:** Provide permission-aware operational and executive reporting.

**Priority:** Cross-cutting

| Specified feature | Required functionality / planned scope |
| --- | --- |
| Executive | Revenue, pipeline, workforce and delivery |
| Commercial | Leads, conversion, forecasts and targets |
| Contact Centre | Calls, queues and agent activity |
| HR | Headcount, leave, recruitment and reviews |
| Finance | Receivables, collections and budgets |
| Projects | Milestones, workload and overdue tasks |
| Custom Reports | Filters, columns and grouping |
| Scheduling | Authorized delivery and subscriptions |
| Exports | CSV, Excel and PDF |
| KPI Definitions | Shared formulas and data ownership |

**Before operational use:** approve the provider interface, event mapping, lawful recording policy and role assignments; configure the encrypted connection profile and signing secret; then verify events against the provider.

## Module 20 Notifications, Approvals & Automation

**Status:** Planned — not installed. No operational screens or end-to-end user procedures are available yet.

**Purpose:** Standardize events, routing and business decisions.

**Priority:** Cross-cutting

| Specified feature | Required functionality / planned scope |
| --- | --- |
| Notification Centre | In-app alerts and unread counts |
| Email | SMTP or approved Microsoft 365 delivery |
| SMS | TXTConnect notifications |
| Templates | Variables and approved content |
| Preferences | Per-event channel choices |
| Approvals | One- and multistage workflows |
| Delegation | Time-bound substitute approvers |
| Escalation | Overdue requests and SLA events |
| Rules | Event-condition-action automation |
| Scheduled Actions | Reminders and renewal notices |
| Delivery Logs | Attempts, failure and retries |
| Audit | Decision history and timestamps |

**Before operational use:** approve the provider interface, event mapping, lawful recording policy and role assignments; configure the encrypted connection profile and signing secret; then verify events against the provider.

## Module 21 Integration & API Management

**Status:** Planned — not installed. No operational screens or end-to-end user procedures are available yet.

**Purpose:** Govern secure connections with external systems.

**Priority:** Cross-cutting

| Specified feature | Required functionality / planned scope |
| --- | --- |
| Registry | Integration owners and configuration |
| Credentials | Secrets storage and rotation |
| VitalPBX | Call and extension integration |
| TXTConnect | SMS requests and provider events |
| Microsoft 365 | SSO, Graph and approved content access |
| Qdrant | Semantic index operations |
| USSD | Secure API to separate USSD Manager |
| Webhooks | Signed inbound/outbound events |
| API Access | Scopes, tokens and rate limiting |
| Logs | Latency, errors and correlation IDs |
| Recovery | Backoff, idempotency and dead letters |
| Health | Connectivity and service status |

**Before operational use:** approve the provider interface, event mapping, lawful recording policy and role assignments; configure the encrypted connection profile and signing secret; then verify events against the provider.

## Module 22 Security, Audit, Backup & Monitoring

**Status:** Planned — not installed. No operational screens or end-to-end user procedures are available yet.

**Purpose:** Protect ERP data and assure service continuity.

**Priority:** Cross-cutting

| Specified feature | Required functionality / planned scope |
| --- | --- |
| Audit | Critical data and permission changes |
| Security Events | Login failures and suspicious activity |
| Classification | Public, internal, confidential and restricted |
| Retention | Retention/deletion by category |
| Backups | Database, files and configuration |
| Restore Tests | Recovery verification |
| Queues | Failed jobs, Horizon and backlog |
| Monitoring | Errors, uptime and performance |
| Secrets | Rotation and protected configuration |
| Incidents | Response and escalation |
| Disaster Recovery | RPO/RTO and restoration |
| Compliance | Privacy and access reviews |

**Before operational use:** approve the provider interface, event mapping, lawful recording policy and role assignments; configure the encrypted connection profile and signing secret; then verify events against the provider.
