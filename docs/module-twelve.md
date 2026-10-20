# Module 12 Recruitment and Assessments

This guide describes the locally installed recruitment workflow. Open **Human Resources → Recruitment & assessments** or go to `/recruitment` after signing in. Records are scoped to the active organization.

## Install and access

Apply the migration and additive role grants in each environment:

```sh
php artisan migrate --force
php artisan db:seed --class=IdentitySeeder --force
php artisan optimize:clear
```

The Human Resources role receives candidate management, interviews, assessments, offer preparation and hiring permissions. Requisition approvals and offer approvals are separate permissions so an organization can assign independent approvers. Approvers also need `recruitment.view` and `recruitment.applications.view` to review the request or offer in the interface. The Administrator/Super Admin can access every recruitment permission. Assign other roles only the capabilities they need:

- `recruitment.view` opens the recruitment workspace.
- `recruitment.manage` submits requisitions and creates or closes vacancies.
- `recruitment.requisitions.approve` approves or rejects hiring requests.
- `recruitment.applications.view` views candidate data and resumes.
- `recruitment.applications.manage` records screening stages and notes.
- `recruitment.interviews.manage` schedules and scores interviews.
- `recruitment.assessments.manage` creates templates, issues candidate links and grades submissions.
- `recruitment.offers.manage` prepares offers.
- `recruitment.offers.approve` approves and issues offers.
- `recruitment.hire` converts an accepted offer into an HR employee record.
- `recruitment.reports.view` views the applicant funnel and average time to hire.

## Hiring workflow

1. Submit a requisition with the role, organization department or position, headcount and justification. An authorized approver records the decision.
2. Create a vacancy from an approved requisition. Set its opening and closing dates and publish it by choosing **Open**.
3. Share the public career link. Applicants submit their name, contact details, optional cover letter and optional resume, and must agree to the recruitment use notice.
4. Review applications, download resumes, record screening notes, and move applicants through screening, interview, assessment, offer, rejection or withdrawal.
5. Schedule interviews and record panel scores and notes. Notes are encrypted in the database.
6. Create an assessment template. Put one question or practical/speech scoring criterion on each line, set the time limit and optional pass score, and assign it to an applicant. Share the generated one-time link with the candidate. The candidate’s time limit starts on first access.
7. Practical and speech assessments accept an encrypted submission attachment. Assessors download it through the protected application screen, then record a score and notes.
8. Prepare offer terms and submit the offer for approval. Once approved, share the generated candidate response link. Candidates can accept or decline through that link.
9. Convert an accepted offer to an employee record by entering an employee number, hire date and employment type. The new HR employee starts in **preboarding**. Link an ERP account later from the HR employee record if needed.

The recruitment dashboard shows open vacancies, application stages, active offers and average time to hire where enough completed data exists. Stage changes and key decisions are recorded in the audit log.

## Data protection and boundaries

Candidate profile details, cover letters, screening and interview notes, assessment questions and answers, offer terms, resumes and practical/speech attachments are encrypted with Laravel `APP_KEY`. Files remain on the private local disk and are served only through authorized routes or candidate-specific, high-entropy links. Keep `APP_KEY` with protected backups; losing it prevents decryption. Do not place uploads on a public web root.

The applicant must provide consent before submitting. The system records the consent time but does not determine legal basis, retention duration or automatic deletion. Configure and follow the organization's candidate privacy, retention and deletion policy. Candidate email, interview invitations and offer links are not sent automatically; HR shares generated links through its approved communication process.

Assessment grading is manual. The system does not monitor candidate behavior, record webcam/video surveillance, run speech transcription, or score tests automatically. Agree the questions, assessment accessibility, scoring rubric and decision policy with the relevant business owners before use. An accepted offer creates an HR record but does not create or grant an ERP login automatically.
