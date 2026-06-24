<?php

use App\Contracts\LLMServiceInterface;
use App\Exceptions\LLMException;
use App\Jobs\PrioritiseReviewJob;
use App\Models\ReviewState;
use App\Services\FakeLLMService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('applies the LLM ranking on success', function () {
    $state = ReviewState::factory()->create(['attempt_count' => 0, 'correct_count' => 0]);

    PrioritiseReviewJob::dispatchSync($state->knowledge_unit_id, $state->user_id, 'correct');

    $state->refresh();

    expect($state->priority)->toBe(50) // FakeLLMService default
        ->and($state->attempt_count)->toBe(1)
        ->and($state->correct_count)->toBe(1)
        ->and($state->last_result)->toBe('correct')
        ->and($state->due_at->isFuture())->toBeTrue();
});

it('does not bump correct_count on a wrong answer', function () {
    $state = ReviewState::factory()->create(['attempt_count' => 2, 'correct_count' => 2]);

    PrioritiseReviewJob::dispatchSync($state->knowledge_unit_id, $state->user_id, 'wrong');

    $state->refresh();

    expect($state->attempt_count)->toBe(3)
        ->and($state->correct_count)->toBe(2)
        ->and($state->last_result)->toBe('wrong');
});

it('uses the interval fallback when the LLM is unavailable', function () {
    app()->bind(LLMServiceInterface::class, fn () => new class extends FakeLLMService
    {
        public function prioritiseReview(array $unitHistory): array
        {
            throw new LLMException('down');
        }
    });

    $state = ReviewState::factory()->create(['interval_days' => 3, 'last_result' => 'correct']);

    PrioritiseReviewJob::dispatchSync($state->knowledge_unit_id, $state->user_id, 'correct');

    $state->refresh();

    expect($state->interval_days)->toBe(6) // 3 * 2
        ->and($state->due_at->greaterThan(now()->addDays(5)))->toBeTrue()
        ->and($state->attempt_count)->toBe(1);
});

it('resets the interval to one day after a wrong answer in fallback', function () {
    app()->bind(LLMServiceInterface::class, fn () => new class extends FakeLLMService
    {
        public function prioritiseReview(array $unitHistory): array
        {
            throw new LLMException('down');
        }
    });

    $state = ReviewState::factory()->create(['interval_days' => 8]);

    PrioritiseReviewJob::dispatchSync($state->knowledge_unit_id, $state->user_id, 'wrong');

    expect($state->fresh()->interval_days)->toBe(1);
});

it('does nothing when the review state is missing', function () {
    PrioritiseReviewJob::dispatchSync(999, 999, 'correct');
})->throwsNoExceptions();
