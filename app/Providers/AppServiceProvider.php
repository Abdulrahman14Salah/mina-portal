<?php

namespace App\Providers;

use App\Models\ApplicationTask;
use App\Models\Document;
use App\Models\Payment;
use App\Models\User;
use App\Models\VisaApplication;
use App\Policies\ApplicationTaskPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\UserPolicy;
use App\Policies\VisaApplicationPolicy;
use App\View\Composers\AdminBreadcrumbComposer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(ApplicationTask::class, ApplicationTaskPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(VisaApplication::class, VisaApplicationPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);

        View::composer('admin.*', AdminBreadcrumbComposer::class);
    }
}
