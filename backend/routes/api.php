<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ResourceController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Api\FormController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Business\ActivityLogController;
use App\Http\Controllers\Business\BusinessSettingController;
use App\Http\Controllers\Business\CustomerController;
use App\Http\Controllers\Business\LeadController;
use App\Http\Controllers\Business\LookupController;
use App\Http\Controllers\Business\QuotationController;
use App\Http\Controllers\Business\AccountController;
use App\Http\Controllers\Business\InvoiceController;
use App\Http\Controllers\Business\PaymentController;
use App\Http\Controllers\Business\ReportController;
use App\Http\Controllers\Business\TransactionController;
use App\Http\Controllers\Business\WorkProjectController;
use App\Http\Controllers\Business\InventoryController;
use App\Http\Controllers\Business\PurchaseController;
use App\Http\Controllers\Business\SupplierController;
use App\Http\Controllers\Business\TicketController;
use App\Http\Controllers\Business\RoleController;
use App\Http\Controllers\Business\UserController;
use App\Support\ResourceRegistry;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API (website)
|--------------------------------------------------------------------------
*/
Route::controller(PublicController::class)->group(function () {
    Route::get('site', 'site');
    Route::get('home', 'home');
    Route::get('about', 'about');
    Route::get('services', 'services');
    Route::get('services/{slug}', 'service');
    Route::get('projects', 'projects');
    Route::get('projects/{slug}', 'project');
    Route::get('testimonials', 'testimonials');
    Route::get('faqs', 'faqs');
    Route::get('posts', 'posts');
    Route::get('posts/{slug}', 'post');
    Route::get('careers', 'careers');
    Route::get('careers/{slug}', 'career');
    Route::get('pages/{slug}', 'page');
});

Route::middleware('throttle:forms')->group(function () {
    Route::post('inquiries', [FormController::class, 'inquiry']);
    Route::post('careers/{slug}/apply', [FormController::class, 'apply']);
    Route::post('service-requests', [FormController::class, 'serviceRequest']);
});

/*
|--------------------------------------------------------------------------
| Admin API
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:password-reset');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:password-reset-submit');

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('profile', [AuthController::class, 'profile']);

        // Website content (role permission: website.manage)
        Route::middleware('perm:website.manage')->group(function () {
            Route::get('dashboard', [DashboardController::class, 'index']);
            Route::get('job_applications/{id}/cv', [DashboardController::class, 'cv'])->whereNumber('id');

            Route::get('settings', [SettingController::class, 'index']);
            Route::post('settings', [SettingController::class, 'update']);
            Route::post('settings/test-mail', [SettingController::class, 'testMail'])->middleware('throttle:test-mail');
        });

        // Generic CRUD for every registered resource.
        // Permission is checked per resource in ResourceController (registry key "permission").
        $resources = implode('|', ResourceRegistry::keys());
        Route::controller(ResourceController::class)->group(function () use ($resources) {
            Route::get('{resource}', 'index')->where('resource', $resources);
            Route::post('{resource}', 'store')->where('resource', $resources);
            Route::get('{resource}/{id}', 'show')->where('resource', $resources)->whereNumber('id');
            Route::post('{resource}/{id}', 'update')->where('resource', $resources)->whereNumber('id');
            Route::delete('{resource}/{id}', 'destroy')->where('resource', $resources)->whereNumber('id');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Business API (staff panel): auth + active user + branch context + permission
|--------------------------------------------------------------------------
*/
Route::prefix('business')->middleware(['auth:sanctum', 'active', 'branch'])->group(function () {
    Route::get('permissions', [RoleController::class, 'catalog'])->middleware('perm:roles.manage|users.manage');

    Route::middleware('perm:users.manage')->controller(UserController::class)->group(function () {
        Route::get('users', 'index');
        Route::post('users', 'store');
        Route::get('users/{user}', 'show')->whereNumber('user');
        Route::post('users/{user}', 'update')->whereNumber('user');
    });
    Route::get('roles', [RoleController::class, 'index'])->middleware('perm:roles.manage|users.manage');
    Route::middleware('perm:roles.manage')->controller(RoleController::class)->group(function () {
        Route::post('roles', 'store');
        Route::get('roles/{role}', 'show')->whereNumber('role');
        Route::post('roles/{role}', 'update')->whereNumber('role');
        Route::delete('roles/{role}', 'destroy')->whereNumber('role');
    });

    Route::middleware('perm:settings.business')->controller(BusinessSettingController::class)->group(function () {
        Route::get('settings', 'index');
        Route::post('settings', 'update');
        Route::get('pdf/sample', 'samplePdf');
    });

    Route::get('activity-logs', [ActivityLogController::class, 'index'])->middleware('perm:activity.view');

    // Lookups for business forms (any staff)
    Route::get('lookup/staff', [LookupController::class, 'staff']);
    Route::get('lookup/tax-rates', [LookupController::class, 'taxRates']);
    Route::get('lookup/branches', [LookupController::class, 'branches']);

    // Step 2: CRM
    Route::middleware('perm:crm.view')->group(function () {
        Route::get('leads', [LeadController::class, 'index']);
        Route::get('leads/{lead}', [LeadController::class, 'show'])->whereNumber('lead');
        Route::get('follow-ups', [LeadController::class, 'followUps']);
        Route::get('customers', [CustomerController::class, 'index']);
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->whereNumber('customer');
    });
    Route::middleware('perm:crm.manage')->group(function () {
        Route::post('leads', [LeadController::class, 'store']);
        Route::post('leads/{lead}', [LeadController::class, 'update'])->whereNumber('lead');
        Route::post('leads/{lead}/stage', [LeadController::class, 'stage'])->whereNumber('lead');
        Route::post('leads/{lead}/activities', [LeadController::class, 'addActivity'])->whereNumber('lead');
        Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])->whereNumber('lead');
        Route::post('lead-activities/{activity}/toggle', [LeadController::class, 'completeActivity'])->whereNumber('activity');
        Route::post('customers', [CustomerController::class, 'store']);
        Route::post('customers/{customer}', [CustomerController::class, 'update'])->whereNumber('customer');
    });

    // Step 3: Quotations
    Route::middleware('perm:quotations.view')->controller(QuotationController::class)->group(function () {
        Route::get('quotations', 'index');
        Route::get('quotations/{quotation}', 'show')->whereNumber('quotation');
        Route::get('quotations/{quotation}/pdf', 'pdf')->whereNumber('quotation');
    });
    Route::middleware('perm:quotations.manage')->controller(QuotationController::class)->group(function () {
        Route::post('quotations', 'store');
        Route::post('quotations/{quotation}', 'update')->whereNumber('quotation');
        Route::post('quotations/{quotation}/status', 'status')->whereNumber('quotation');
        Route::post('quotations/{quotation}/revise', 'revise')->whereNumber('quotation');
        Route::post('quotations/{quotation}/email', 'email')->whereNumber('quotation')->middleware('throttle:test-mail');
    });

    // Step 4: Projects
    Route::middleware('perm:projects.view')->controller(WorkProjectController::class)->group(function () {
        Route::get('projects', 'index');
        Route::get('projects/{project}', 'show')->whereNumber('project');
        Route::get('projects/{project}/files/{file}', 'downloadFile')->whereNumber(['project', 'file']);
    });
    Route::middleware('perm:projects.manage')->controller(WorkProjectController::class)->group(function () {
        Route::post('projects', 'store');
        Route::post('projects/{project}', 'update')->whereNumber('project');
        Route::post('projects/{project}/milestones', 'saveMilestones')->whereNumber('project');
        Route::post('projects/{project}/tasks', 'saveTask')->whereNumber('project');
        Route::post('projects/{project}/tasks/{task}', 'saveTask')->whereNumber(['project', 'task']);
        Route::delete('projects/{project}/tasks/{task}', 'deleteTask')->whereNumber(['project', 'task']);
        Route::post('projects/{project}/files', 'uploadFiles')->whereNumber('project');
        Route::delete('projects/{project}/files/{file}', 'deleteFile')->whereNumber(['project', 'file']);
        Route::post('projects/{project}/schedule', 'saveSchedule')->whereNumber('project');
        Route::post('projects/{project}/publish', 'publish')->whereNumber('project')->middleware('perm:website.manage');
    });

    // Step 4: Billing (invoices, payments, credit notes)
    Route::middleware('perm:billing.view')->group(function () {
        Route::get('invoices', [InvoiceController::class, 'index']);
        Route::get('invoices/prefill', [InvoiceController::class, 'prefill']);
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->whereNumber('invoice');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->whereNumber('invoice');
        Route::get('receivables', [InvoiceController::class, 'receivables']);
        Route::get('payments', [PaymentController::class, 'index']);
        Route::get('payments/{payment}', [PaymentController::class, 'show'])->whereNumber('payment');
        Route::get('payments/{payment}/pdf', [PaymentController::class, 'pdf'])->whereNumber('payment');
    });
    Route::middleware('perm:billing.manage')->group(function () {
        Route::post('invoices', [InvoiceController::class, 'store']);
        Route::post('invoices/{invoice}', [InvoiceController::class, 'update'])->whereNumber('invoice');
        Route::post('invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->whereNumber('invoice');
        Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->whereNumber('invoice');
        Route::post('invoices/{invoice}/receive', [InvoiceController::class, 'receive'])->whereNumber('invoice');
        Route::post('invoices/{invoice}/credit-note', [InvoiceController::class, 'creditNote'])->whereNumber('invoice');
        Route::post('invoices/{invoice}/email', [InvoiceController::class, 'email'])->whereNumber('invoice')->middleware('throttle:test-mail');
        Route::post('payments/{payment}/cancel', [PaymentController::class, 'cancel'])->whereNumber('payment');
        Route::post('payments/{payment}/email', [PaymentController::class, 'email'])->whereNumber('payment')->middleware('throttle:test-mail');
    });

    // Step 5: Accounts
    Route::get('lookup/accounts', [AccountController::class, 'index'])->middleware('perm:accounts.view|billing.manage|suppliers.pay');
    Route::get('lookup/categories', [TransactionController::class, 'categories'])->middleware('perm:accounts.view|projects.view');
    Route::middleware('perm:accounts.view')->group(function () {
        Route::get('accounts', [AccountController::class, 'index']);
        Route::get('accounts/{account}', [AccountController::class, 'show'])->whereNumber('account');
        Route::get('transactions', [TransactionController::class, 'index']);
        Route::get('transactions/{transaction}/attachment', [TransactionController::class, 'attachment'])->whereNumber('transaction');
        Route::get('reports/cashbook', [ReportController::class, 'cashbook']);
        Route::get('reports/summary', [ReportController::class, 'summary']);
        Route::get('reports/profit-loss', [ReportController::class, 'profitLoss']);
        Route::get('reports/projects', [ReportController::class, 'projects']);
        Route::get('reports/branches', [ReportController::class, 'branches']);
    });
    // Step 6: Inventory & purchase
    Route::get('lookup/stores', [InventoryController::class, 'stores'])->middleware('perm:inventory.view|projects.view');
    Route::get('lookup/items', [InventoryController::class, 'items'])->middleware('perm:inventory.view');
    Route::middleware('perm:inventory.view')->group(function () {
        Route::get('stock', [InventoryController::class, 'stock']);
        Route::get('stock/items/{item}', [InventoryController::class, 'itemLedger'])->whereNumber('item');
        Route::get('stock-entries', [InventoryController::class, 'entries']);
        Route::get('stock-entries/{entry}', [InventoryController::class, 'showEntry'])->whereNumber('entry');
        Route::get('purchase-orders', [PurchaseController::class, 'orders']);
        Route::get('purchase-orders/{order}', [PurchaseController::class, 'showOrder'])->whereNumber('order');
        Route::get('purchase-orders/{order}/pdf', [PurchaseController::class, 'orderPdf'])->whereNumber('order');
        Route::get('purchases', [PurchaseController::class, 'index']);
        Route::get('purchases/{purchase}', [PurchaseController::class, 'show'])->whereNumber('purchase');
    });
    Route::middleware('perm:inventory.view|suppliers.pay')->group(function () {
        Route::get('suppliers', [SupplierController::class, 'index']);
        Route::get('suppliers/{supplier}', [SupplierController::class, 'show'])->whereNumber('supplier');
    });
    Route::middleware('perm:inventory.manage')->group(function () {
        Route::post('stores', [InventoryController::class, 'saveStore']);
        Route::post('stores/{store}', [InventoryController::class, 'saveStore'])->whereNumber('store');
        Route::post('stock-entries', [InventoryController::class, 'storeEntry']);
        Route::post('stock-entries/{entry}/cancel', [InventoryController::class, 'cancelEntry'])->whereNumber('entry');
        Route::post('purchase-orders', [PurchaseController::class, 'storeOrder']);
        Route::post('purchase-orders/{order}', [PurchaseController::class, 'storeOrder'])->whereNumber('order');
        Route::post('purchase-orders/{order}/status', [PurchaseController::class, 'orderStatus'])->whereNumber('order');
        Route::post('purchase-orders/{order}/email', [PurchaseController::class, 'emailOrder'])->whereNumber('order')->middleware('throttle:test-mail');
        Route::post('purchases', [PurchaseController::class, 'store']);
        Route::post('purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])->whereNumber('purchase');
    });
    Route::middleware('perm:suppliers.pay')->group(function () {
        Route::post('suppliers/{supplier}/pay', [SupplierController::class, 'pay'])->whereNumber('supplier');
        Route::post('supplier-payments/{payment}/cancel', [SupplierController::class, 'cancelPayment'])->whereNumber('payment');
    });

    // Step 7: Ticketing
    Route::middleware('perm:tickets.view')->group(function () {
        Route::get('tickets', [TicketController::class, 'index']);
        Route::get('tickets/report', [TicketController::class, 'report']);
        Route::get('tickets/{ticket}', [TicketController::class, 'show'])->whereNumber('ticket');
    });
    Route::middleware('perm:tickets.manage')->group(function () {
        Route::post('tickets', [TicketController::class, 'store']);
        Route::post('tickets/{ticket}', [TicketController::class, 'update'])->whereNumber('ticket');
        Route::post('tickets/{ticket}/comments', [TicketController::class, 'comment'])->whereNumber('ticket');
    });

    Route::middleware('perm:accounts.manage')->group(function () {
        Route::post('accounts', [AccountController::class, 'store']);
        Route::post('accounts/{account}', [AccountController::class, 'update'])->whereNumber('account');
        Route::post('transactions', [TransactionController::class, 'store']);
        Route::post('transactions/transfer', [TransactionController::class, 'transfer']);
        Route::post('transactions/{transaction}/cancel', [TransactionController::class, 'cancel'])->whereNumber('transaction');
    });
});

/*
| Public signed document links (shared on WhatsApp). Valid only with an unexpired signature.
*/
Route::get('public/quotations/{quotation}/pdf', [QuotationController::class, 'publicPdf'])
    ->whereNumber('quotation')->middleware(['signed', 'throttle:30,1'])->name('public.quotation.pdf');