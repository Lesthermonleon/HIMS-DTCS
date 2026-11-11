<?php

namespace App\Providers;

use App\Models\DietPlan;
use App\Models\DietRequest;
use App\Models\Patient;
use App\Models\SurgeryRequest;
use App\Models\SurgerySchedule;
use App\Policies\DietPlanPolicy;
use App\Policies\DietRequestPolicy;
use App\Policies\PatientPolicy;
use App\Policies\SurgeryRequestPolicy;
use App\Policies\SurgerySchedulePolicy;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            \App\Services\Backup\BackupRunnerInterface::class,
            \App\Services\Backup\MysqldumpBackupRunner::class
        );

        $this->app->singleton(\App\Services\Settings\SettingsService::class);

        $this->app->bind(
            \App\Services\Pharmacy\Contracts\MedicationStockProviderInterface::class,
            function ($app) {
                $provider = config('pharmacy.stock_provider', 'mock');
                return match ($provider) {
                    'mock' => $app->make(\App\Services\Pharmacy\MockMedicationStockProvider::class),
                    default => $app->make(\App\Services\Pharmacy\MockMedicationStockProvider::class),
                };
            }
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Patient::class, PatientPolicy::class);
        Gate::policy(SurgeryRequest::class, SurgeryRequestPolicy::class);
        Gate::policy(SurgerySchedule::class, SurgerySchedulePolicy::class);
        Gate::policy(DietRequest::class, DietRequestPolicy::class);
        Gate::policy(DietPlan::class, DietPlanPolicy::class);
        Paginator::useBootstrapFive();

        // Enforce password policy dynamically from hospital settings
        \Illuminate\Validation\Rules\Password::defaults(function () {
            /** @var \App\Services\Settings\SettingsService $settings */
            $settings = app(\App\Services\Settings\SettingsService::class);
            $minLength = (int) $settings->get('security.password_min_length', 8);
            $rule = \Illuminate\Validation\Rules\Password::min(max(8, $minLength));

            if ($settings->get('security.password_require_mixed_case', false)) {
                $rule->mixedCase();
            }
            if ($settings->get('security.password_require_numbers', false)) {
                $rule->numbers();
            }
            if ($settings->get('security.password_require_symbols', false)) {
                $rule->symbols();
            }

            return $rule;
        });
    }
}

