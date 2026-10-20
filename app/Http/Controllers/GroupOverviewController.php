<?php

namespace App\Http\Controllers;

use App\Domain\Administration\Modules;
use App\Domain\Identity\LeadAccess;
use App\Domain\Sales\SalesAccess;
use App\Support\WorkspaceNavigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GroupOverviewController extends Controller
{
    private const GROUPS = [
        'commercial' => ['title' => 'Commercial & CRM', 'description' => 'Customer relationships, sales pipeline and commercial activity.'],
        'customer-experience' => ['title' => 'Customer Experience', 'description' => 'Customer calls, support requests and communications.'],
        'human-resources' => ['title' => 'Human Resources', 'description' => 'People, hiring, attendance and employee development.'],
        'operations-projects' => ['title' => 'Operations & Projects', 'description' => 'Projects, tasks, procurement and operational delivery.'],
    ];

    public function __invoke(Request $request, string $group)
    {
        abort_unless(isset(self::GROUPS[$group]), 404);
        $user = $request->user();
        $organizationId = $user->active_organization_id;
        $links = app(WorkspaceNavigation::class)->groups($user);
        $groupTitle = self::GROUPS[$group]['title'];
        $availableLinks = collect($links[$groupTitle] ?? [])->reject(fn ($link) => ($link['overview'] ?? false))->values();
        abort_if($availableLinks->isEmpty(), 403);

        $modules = app(Modules::class);
        $cards = [];
        $count = static function (string $table, int|string|null $organizationId, ?string $where = null, array $bindings = []): int {
            if (! $organizationId || ! Schema::hasTable($table)) return 0;
            $query = DB::table($table)->where('organization_id', $organizationId);
            return $where ? $query->whereRaw($where, $bindings)->count() : $query->count();
        };
        $add = static function (string $label, int $value, string $description, string $route) use (&$cards): void {
            $cards[] = compact('label', 'value', 'description', 'route');
        };

        if ($group === 'commercial') {
            if ($modules->enabled('crm') && $user->can('leads.view')) {
                $leads = app(LeadAccess::class)->query($user)->whereNull('archived_at');
                $add('Active leads', (clone $leads)->whereNotIn('status', ['converted', 'disqualified'])->count(), 'Leads in your permitted data scope', 'leads.index');
            }
            if ($modules->enabled('sales') && $user->can('opportunities.view')) {
                $opportunities = app(SalesAccess::class)->query($user);
                $add('Open opportunities', (clone $opportunities)->whereNotIn('stage', ['won', 'lost'])->count(), 'Opportunities in your permitted pipeline', 'opportunities.index');
            }
            if ($modules->enabled('contracts') && $user->can('quotes.view')) $add('Draft quotations', $count('commercial_quotes', $organizationId, "status = ?", ['draft']), 'Quotations awaiting completion', 'commercial.quotes.index');
            if ($modules->enabled('marketing') && $user->can('marketing.view')) $add('Active campaigns', $count('marketing_campaigns', $organizationId, "status = ?", ['active']), 'Campaigns currently in progress', 'marketing.index');
        }

        if ($group === 'customer-experience') {
            if ($modules->enabled('support') && $user->can('tickets.view')) $add('Open tickets', $count('tickets', $organizationId, "status NOT IN (?, ?)", ['resolved', 'closed']), 'Customer requests still in progress', 'tickets.index');
            if ($modules->enabled('contact_centre') && $user->can('calls.view')) $add('Calls today', $count('call_records', $organizationId, 'started_at >= ?', [now()->startOfDay()]), 'Inbound and outbound calls recorded today', 'contact-centre.index');
            if ($modules->enabled('sms') && $user->can('sms.view')) $add('SMS sent this month', $count('sms_messages', $organizationId, 'created_at >= ?', [now()->startOfMonth()]), 'Messages submitted or queued this month', 'sms.index');
        }

        if ($group === 'human-resources') {
            if ($modules->enabled('hr') && $user->can('hr.view')) $add('Active employees', $count('hr_employees', $organizationId, "status = ?", ['active']), 'Employees currently active', 'hr.index');
            if ($modules->enabled('recruitment') && $user->can('recruitment.view')) {
                $add('Open vacancies', $count('hr_vacancies', $organizationId, "status = ?", ['open']), 'Vacancies accepting applications', 'recruitment.index');
                $add('Applications to review', $count('hr_applications', $organizationId, "stage NOT IN (?, ?, ?, ?)", ['hired', 'rejected', 'withdrawn', 'closed']), 'Candidates still in the hiring process', 'recruitment.index');
            }
            if ($modules->enabled('attendance') && $user->can('attendance.view')) $add('Leave awaiting approval', $count('hr_leave_requests', $organizationId, "status = ?", ['pending']), 'Requests awaiting a decision', 'attendance.index');
            if ($modules->enabled('performance') && $user->can('performance.view')) $add('Open performance reviews', $count('hr_performance_reviews', $organizationId, "status != ?", ['approved']), 'Reviews still in progress', 'performance.index');
        }

        if ($group === 'operations-projects') {
            if ($modules->enabled('projects') && ($user->can('projects.view') || $user->can('projects.manage') || $user->can('projects.tasks.manage'))) {
                $add('Active projects', $count('projects', $organizationId, "status = ?", ['active']), 'Projects currently underway', 'projects.index');
                $add('Open tasks', $count('project_tasks', $organizationId, "status NOT IN (?, ?)", ['done', 'cancelled']), 'Tasks not yet completed', 'projects.index');
            }
            if ($modules->enabled('procurement') && ($user->isSuperAdmin() || $user->can('procurement.view') || $user->can('procurement.requests.create') || $user->can('procurement.inventory.manage') || $user->can('procurement.assets.manage'))) {
                $add('Pending purchase requests', $count('procurement_requests', $organizationId, "status = ?", ['pending_approval']), 'Requests waiting for review', 'procurement.index');
                $add('Purchase orders', $count('procurement_purchase_orders', $organizationId), 'Orders recorded for this organization', 'procurement.index');
            }
        }

        return view('overview.group', [
            'title' => $groupTitle,
            'description' => self::GROUPS[$group]['description'],
            'cards' => $cards,
            'links' => $availableLinks,
        ]);
    }
}
