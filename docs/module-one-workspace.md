# Module 01 — Workspace

Workspace is the signed-in user's personal starting point. Open its six destinations from the Workspace sidebar group. Personal records are scoped to the authenticated user; team tasks and approval records are additionally scoped to the active organization.

## My Dashboard

The dashboard shows personal open and overdue task totals, approval decisions waiting for the user, today's personal calendar events, and unread notifications. Cards open the matching Workspace page. Users can show or hide the personal task, approval, calendar and notification cards; that selection is saved to their account. Other organization summaries remain subject to each module's permissions.

## My Tasks

Create a task for yourself or an active user in the current organization. Tasks have a title, description, priority, due date, status, and encrypted discussion comments. Users can see tasks assigned to them or created by them, update task status/priority/due date, and comment. The assignee receives an in-app notification when another user assigns the task.

## My Approvals

The page lists pending requests at the signed-in user's current approval stage and recent approval history. Decisions use the existing approval workflow, which records the decision and optional note and advances the request according to its configured stages. Users only see requests submitted by or assigned to them.

## My Calendar

Create personal events with a title, description, start/end, all-day flag, and optional reminder. The monthly view also shows assigned task deadlines and approval deadlines with links to those pages. It supports previous/next month navigation and deleting one's own personal event. Due reminders create an in-app notification. The Laravel scheduler must run `schedule:run` every minute for reminders to be delivered on time.

## Notifications

The bell shows the unread count. The notification page lists personal notices with pagination and unread filtering, and lets the user mark notices read or archive them. New notices are checked in the browser every 30 seconds while the app is open and shown as an in-app toast. Preferences are stored per user and organization. Task assignment and calendar reminder notices support in-app delivery; approval workflow events can also use email and SMS, subject to configured providers, enabled modules, and consent requirements.

## My Profile

Users can update their own display name and phone number. Email and authentication credentials remain managed through the administrator and account security flows. The page links to password/MFA security and notification preferences.

## Install and operate

The additive migration adds the profile phone field, saved dashboard widget preferences, and Workspace task, comment, and calendar tables. Deploy the code and run `php artisan migrate --force`. Keep the existing `.env` and `APP_KEY`; no new environment variables are required. For calendar reminders, configure the standard Laravel scheduler cron entry (`* * * * * php /path/to/artisan schedule:run`).

The dashboard summary cards are user-configurable. Calendar events are personal rather than shared team calendars, and task reminders beyond assignment notices are not currently sent. These are deliberate current boundaries; approval history and notification delivery reuse the existing automation module.
