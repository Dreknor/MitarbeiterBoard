<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Model' => 'App\Policies\ModelPolicy',
        \App\Models\DiagnosticSession::class => \App\Policies\DiagnosticPolicy::class,
        \App\Models\DiagnosticArea::class => \App\Policies\DiagnosticAreaPolicy::class,
        \App\Models\GradingDocumentationSession::class => \App\Policies\GradingDocumentationSessionPolicy::class,

        // Personal-Modul (Phase 1)
        \App\Models\personal\Employment::class => \App\Policies\Personal\EmploymentPolicy::class,
        // Personal-Modul (Phase 2)
        \App\Models\personal\PersonalDocument::class => \App\Policies\Personal\PersonalDocumentPolicy::class,
        \App\Models\personal\Training::class         => \App\Policies\Personal\TrainingPolicy::class,
        // Policies für zukünftige Phase-3+ Modelle
        // \App\Models\personal\EmployeeReview::class   => \App\Policies\Personal\EmployeeReviewPolicy::class,
        // \App\Models\personal\BemCase::class          => \App\Policies\Personal\BemCasePolicy::class,
        // \App\Models\personal\ChangeRequest::class    => \App\Policies\Personal\ChangeRequestPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // Caching user
        Auth::provider('cache-user', function () {
            return resolve(CacheUserProvider::class);
        });
    }
}
