<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ResourceController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Api\FormController;
use App\Http\Controllers\Api\PublicController;
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

Route::middleware('throttle:10,1')->group(function () {
    Route::post('inquiries', [FormController::class, 'inquiry']);
    Route::post('careers/{slug}/apply', [FormController::class, 'apply']);
});

/*
|--------------------------------------------------------------------------
| Admin API
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('profile', [AuthController::class, 'profile']);

        Route::get('dashboard', [DashboardController::class, 'index']);
        Route::get('job_applications/{id}/cv', [DashboardController::class, 'cv'])->whereNumber('id');

        Route::get('settings', [SettingController::class, 'index']);
        Route::post('settings', [SettingController::class, 'update']);

        // Generic CRUD for every registered resource
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
