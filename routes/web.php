<?php

use App\Http\Controllers\Admin\ApplicationController as AdminApplicationController;
use App\Http\Controllers\Admin\ClientController as AdminClientController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DocumentController as AdminDocumentController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\ReviewerController as AdminReviewerController;
use App\Http\Controllers\Admin\TaskBuilderController as AdminTaskBuilderController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VisaTypeController as AdminVisaTypeController;
use App\Http\Controllers\Client\DashboardController as ClientDashboardController;
use App\Http\Controllers\Client\DocumentController as ClientDocumentController;
use App\Http\Controllers\Client\OnboardingController;
use App\Http\Controllers\Client\PaymentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Reviewer\ApplicationController as ReviewerApplicationController;
use App\Http\Controllers\Reviewer\DashboardController as ReviewerDashboardController;
use App\Http\Controllers\Reviewer\DocumentController as ReviewerDocumentController;
use Illuminate\Support\Facades\Route;

Route::post('/language/{locale}', [LanguageController::class, 'switch'])->name('language.switch');

Route::post('/payments/webhook', [PaymentWebhookController::class, 'handle'])->name('payments.webhook');

Route::middleware('guest')->group(function () {
    Route::get('/', fn () => redirect()->route('onboarding.show'))->name('home');
    Route::get('/apply', [OnboardingController::class, 'show'])->name('onboarding.show');
    Route::post('/apply', [OnboardingController::class, 'store'])->name('onboarding.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/client/dashboard/{tab?}', [ClientDashboardController::class, 'show'])->middleware('active')->name('client.dashboard');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::post('/client/documents', [ClientDocumentController::class, 'store'])->middleware('active')->name('client.documents.store');
    Route::delete('/client/documents/{document}', [ClientDocumentController::class, 'destroy'])->middleware('active')->name('client.documents.destroy');
    Route::get('/payments/success', [PaymentController::class, 'success'])->middleware('active')->name('client.payments.success');
    Route::get('/payments/cancel', [PaymentController::class, 'cancel'])->middleware('active')->name('client.payments.cancel');
    Route::get('/payments/{payment}/checkout', [PaymentController::class, 'checkout'])->middleware('active')->name('client.payments.checkout');
});

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->middleware('can:dashboard.admin')->name('dashboard');
    Route::get('/visa-types', [AdminVisaTypeController::class, 'index'])->middleware('can:dashboard.admin')->name('visa-types.index');
    Route::get('/clients', [AdminClientController::class, 'index'])->middleware('can:dashboard.admin')->name('clients.index');
    Route::get('/task-builder', [AdminTaskBuilderController::class, 'index'])->middleware('can:dashboard.admin')->name('task-builder.index');
    Route::get('/reviewers', [AdminReviewerController::class, 'index'])->middleware('can:dashboard.admin')->name('reviewers.index');
    Route::resource('users', UserController::class)->except(['show', 'destroy'])->middleware('can:dashboard.admin');
    Route::delete('users/{user}/deactivate', [UserController::class, 'deactivate'])->middleware('can:dashboard.admin')->name('users.deactivate');
    Route::patch('users/{user}/role', [UserController::class, 'assignRole'])->middleware('can:dashboard.admin')->name('users.assign-role');
    Route::get('/applications', [AdminApplicationController::class, 'index'])->middleware('can:dashboard.admin')->name('applications.index');
    Route::get('/applications/{application}/documents', [AdminDocumentController::class, 'index'])->middleware('can:dashboard.admin')->name('applications.documents.index');
    Route::post('/applications/{application}/documents', [AdminDocumentController::class, 'store'])->middleware('can:dashboard.admin')->name('applications.documents.store');
    Route::delete('/applications/{application}/documents/{document}', [AdminDocumentController::class, 'destroy'])->middleware('can:dashboard.admin')->name('applications.documents.destroy');
    Route::get('/applications/{application}/payments', [AdminPaymentController::class, 'index'])->middleware('can:dashboard.admin')->name('applications.payments.index');
    Route::patch('/applications/{application}/payments/{payment}/mark-due', [AdminPaymentController::class, 'markDue'])->middleware('can:dashboard.admin')->name('applications.payments.mark-due');
});

Route::middleware(['auth', 'verified'])->prefix('reviewer')->name('reviewer.')->group(function () {
    Route::get('/dashboard/{tab?}', [ReviewerDashboardController::class, 'show'])->middleware('can:tasks.view')->name('dashboard');
    Route::get('/applications/{application}', [ReviewerApplicationController::class, 'show'])->middleware('can:tasks.view')->name('applications.show');
    Route::post('/applications/{application}/tasks/{task}/advance', [ReviewerApplicationController::class, 'advance'])->name('applications.tasks.advance');
    Route::post('/applications/{application}/tasks/{task}/reject', [ReviewerApplicationController::class, 'reject'])->name('applications.tasks.reject');
    Route::post('/applications/{application}/tasks/{task}/reopen', [ReviewerApplicationController::class, 'reopen'])->middleware('can:tasks.advance')->name('applications.tasks.reopen');
    Route::post('/applications/{application}/documents', [ReviewerDocumentController::class, 'store'])->middleware('can:documents.reviewer-upload')->name('applications.documents.store');
});

require __DIR__.'/auth.php';
