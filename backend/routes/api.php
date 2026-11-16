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
});