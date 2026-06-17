<?php

namespace App\Providers;

use App\Contracts\LLMServiceInterface;
use App\Models\Document;
use App\Models\KnowledgeUnit;
use App\Models\Project;
use App\Models\SessionLog;
use App\Models\UploadStaging;
use App\Models\User;
use App\Observers\DocumentObserver;
use App\Observers\KnowledgeUnitObserver;
use App\Observers\ProjectObserver;
use App\Observers\UserObserver;
use App\Policies\DocumentPolicy;
use App\Policies\KnowledgeUnitPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\SessionPolicy;
use App\Policies\UploadStagingPolicy;
use App\Services\OllamaLLMService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LLMServiceInterface::class, fn (): OllamaLLMService => new OllamaLLMService(
            (string) config('services.ollama.url'),
            (string) config('services.ollama.model'),
            (int) config('services.ollama.timeout_extract'),
            (int) config('services.ollama.timeout_grade'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerObservers();
        $this->registerPolicies();
    }

    /**
     * Map models to policies. Registered explicitly because SessionLog → SessionPolicy
     * does not follow Laravel's auto-discovery naming convention.
     */
    protected function registerPolicies(): void
    {
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(KnowledgeUnit::class, KnowledgeUnitPolicy::class);
        Gate::policy(SessionLog::class, SessionPolicy::class);
        Gate::policy(UploadStaging::class, UploadStagingPolicy::class);
    }

    /**
     * Register model observers (cascade soft-delete + user settings bootstrap).
     */
    protected function registerObservers(): void
    {
        Project::observe(ProjectObserver::class);
        Document::observe(DocumentObserver::class);
        KnowledgeUnit::observe(KnowledgeUnitObserver::class);
        User::observe(UserObserver::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
