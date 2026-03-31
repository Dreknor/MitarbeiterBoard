<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// Services
use App\Services\Personal\PersonalScopeService;
use App\Services\Personal\PersonalDocumentService;
use App\Services\Personal\NextcloudFileService;
use App\Services\Personal\QualificationService;
use App\Services\Personal\PersonalReminderService;
use App\Services\Personal\PersonalReportService;
use App\Services\Personal\BemService;
use App\Services\Personal\PersonalRetentionService;
use App\Services\Personal\PersonalAuditService;

// Interfaces
use App\Services\Personal\Contracts\NextcloudFileServiceInterface;

// Models
use App\Models\personal\Employment;

// Observers
use App\Observers\Personal\EmploymentObserver;

class PersonalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Interface-Binding für Nextcloud-Service (erlaubt Fake in Tests)
        $this->app->bind(
            NextcloudFileServiceInterface::class,
            NextcloudFileService::class
        );

        // Services als Singletons registrieren
        $this->app->singleton(PersonalScopeService::class);
        $this->app->singleton(PersonalDocumentService::class);
        $this->app->singleton(QualificationService::class);
        $this->app->singleton(PersonalReminderService::class);
        $this->app->singleton(PersonalReportService::class);
        $this->app->singleton(BemService::class);
        $this->app->singleton(PersonalRetentionService::class);
        $this->app->singleton(PersonalAuditService::class);
    }

    public function boot(): void
    {
        // Observer registrieren
        Employment::observe(EmploymentObserver::class);
    }
}

