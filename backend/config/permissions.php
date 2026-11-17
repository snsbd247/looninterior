<?php

/*
| Permission catalog for the admin panel. Keys are "module.action".
| Roles store a list of these keys (the "admin" role stores ["*"] = everything).
| The UI groups checkboxes by module, using these labels.
|
| When a new module is built, add its permissions here, add them to the default
| roles below if needed, and gate every route with the `perm:` middleware.
*/
return [
    'modules' => [
        'website' => ['label' => 'Website Content', 'actions' => [
            'manage' => 'Manage website content, quote requests, job applications and site settings',
        ]],
        'crm' => ['label' => 'CRM (Leads & Customers)', 'actions' => [
            'view' => 'View leads and customers',
            'manage' => 'Create / edit leads, follow-ups and customers',
        ]],
        'quotations' => ['label' => 'Quotations', 'actions' => [
            'view' => 'View quotations',
            'manage' => 'Create / edit / send quotations',
        ]],
        'projects' => ['label' => 'Projects', 'actions' => [
            'view' => 'View projects',
            'manage' => 'Create / edit projects, milestones and tasks',
        ]],
        'billing' => ['label' => 'Invoices & Payments', 'actions' => [
            'view' => 'View invoices and payments',
            'manage' => 'Create invoices, receive payments, cancel',
        ]],
        'accounts' => ['label' => 'Accounts', 'actions' => [
            'view' => 'View accounts, income/expense and reports',
            'manage' => 'Record income, expenses and transfers',
        ]],
        'inventory' => ['label' => 'Inventory & Purchase', 'actions' => [
            'view' => 'View stock, items and purchases',
            'manage' => 'Purchase orders, receive goods, issue / transfer stock',
        ]],
        'suppliers' => ['label' => 'Supplier Payments', 'actions' => [
            'pay' => 'Pay supplier dues',
        ]],
        'tickets' => ['label' => 'Ticketing', 'actions' => [
            'view' => 'View service tickets',
            'manage' => 'Create / assign / resolve tickets',
        ]],
        'users' => ['label' => 'Users', 'actions' => [
            'manage' => 'Add / edit staff users',
        ]],
        'roles' => ['label' => 'Roles & Permissions', 'actions' => [
            'manage' => 'Edit roles and their permissions',
        ]],
        'branches' => ['label' => 'Branches', 'actions' => [
            'manage' => 'Add / edit branches',
            'all' => 'See all branches and central (all-branch) reports',
        ]],
        'settings' => ['label' => 'Business Settings', 'actions' => [
            'business' => 'Company info, tax rates, document numbering, PDF',
        ]],
        'activity' => ['label' => 'Activity Log', 'actions' => [
            'view' => 'View the activity log',
        ]],
        // Phase F
        'hr' => ['label' => 'HR & Payroll', 'actions' => [
            'view' => 'View employees, attendance and salary sheets',
            'manage' => 'Add employees, mark attendance, approve leave, prepare salary',
            'pay' => 'Pay salaries from an account',
        ]],
        'labour' => ['label' => 'Labour & Contractors', 'actions' => [
            'view' => 'View labour entries and contractor dues',
            'manage' => 'Record labour work on projects',
            'pay' => 'Pay contractors from an account',
        ]],
        'portal' => ['label' => 'Client Portal', 'actions' => [
            'manage' => 'Give customers portal access (login) and reset their password',
        ]],
        'marketing' => ['label' => 'Marketing (SMS & E-mail campaigns)', 'actions' => [
            'view' => 'See campaigns and their delivery reports',
            'send' => 'Create and send bulk SMS / e-mail campaigns (SMS costs money)',
        ]],
        'webmail' => ['label' => 'Webmail', 'actions' => [
            'use' => 'Open their own company mailbox (Webmail) from the admin panel',
        ]],
    ],

    /* Default roles (created once; afterwards the Admin can edit them in the panel). */
    'default_roles' => [
        'admin' => ['name' => 'Admin', 'system' => true, 'permissions' => ['*']],
        'accounts' => ['name' => 'Accounts', 'permissions' => [
            'crm.view', 'quotations.view', 'projects.view', 'billing.view', 'billing.manage',
            'accounts.view', 'accounts.manage', 'inventory.view', 'suppliers.pay',
            'hr.view', 'hr.pay', 'labour.view', 'labour.pay', 'webmail.use',
        ]],
        'store' => ['name' => 'Store', 'permissions' => [
            'projects.view', 'inventory.view', 'inventory.manage', 'webmail.use',
        ]],
        'sales' => ['name' => 'Sales & Marketing', 'permissions' => [
            'website.manage', 'crm.view', 'crm.manage', 'quotations.view', 'quotations.manage',
            'projects.view', 'projects.manage', 'billing.view', 'tickets.view',
            'labour.view', 'labour.manage', 'portal.manage', 'marketing.view', 'marketing.send', 'webmail.use',
        ]],
        'support' => ['name' => 'Support', 'permissions' => [
            'crm.view', 'projects.view', 'tickets.view', 'tickets.manage', 'webmail.use',
        ]],
    ],
];
