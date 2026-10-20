<?php

return [
    'modules' => [
        'administration' => ['name' => '01 · System Administration & Configuration', 'available' => true, 'required' => true],
        'identity' => ['name' => '02 · Identity, Users, Roles & Permissions', 'available' => true, 'required' => true],
        'organization' => ['name' => '03 · Organization, Departments & Branches', 'available' => true, 'required' => true],
        'crm' => ['name' => '04 · CRM & Lead Management', 'available' => true, 'required' => false],
        'sales' => ['name' => '05 · Sales Pipeline & Opportunities', 'available' => true, 'required' => false],
        'contracts' => ['name' => '06 · Quotations, Contracts & Client Onboarding', 'available' => true, 'required' => false],
        'contact_centre' => ['name' => '07 · Contact Centre & VitalPBX', 'available' => true],
        'support' => ['name' => '08 · Customer Support & Ticketing', 'available' => true],
        'sms' => ['name' => '09 · SMS & Communication Management', 'available' => false],
        'marketing' => ['name' => '10 · Marketing, Campaigns & Lead Generation', 'available' => false],
        'hr' => ['name' => '11 · Human Resources & Employee Records', 'available' => false],
        'recruitment' => ['name' => '12 · Recruitment & Assessments', 'available' => false],
        'performance' => ['name' => '13 · Performance Management & Appraisals', 'available' => false],
        'attendance' => ['name' => '14 · Attendance, Leave & Employee Self-Service', 'available' => false],
        'projects' => ['name' => '15 · Projects, Tasks & Team Productivity', 'available' => false],
        'finance' => ['name' => '16 · Finance, Invoicing & Revenue Management', 'available' => false],
        'procurement' => ['name' => '17 · Procurement, Assets & Inventory', 'available' => false],
        'knowledge' => ['name' => '18 · Knowledge Bank & AI Search', 'available' => false],
        'reports' => ['name' => '19 · Reports, Dashboards & Business Intelligence', 'available' => false],
        'automation' => ['name' => '20 · Notifications, Approvals & Automation', 'available' => false],
        'integrations' => ['name' => '21 · Integration & API Management', 'available' => false],
        'security' => ['name' => '22 · Security, Audit, Backup & Monitoring', 'available' => false],
    ],
];
