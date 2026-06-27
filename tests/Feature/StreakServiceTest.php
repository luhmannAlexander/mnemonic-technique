<?php

use App\Models\SessionLog;
use App\Models\User;
use App\Services\StreakService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->service = app(StreakService::class);
});

/** Create a finished session whose finished_at is N days ago. */
function finishedDaysAgo(User $user, int $daysAgo): void
{
    SessionLog::factory()->for($user)->create([
        'status' => 'finished',
        'finished_at' => now()->subDays($daysAgo)->setTime(12, 0),
    ]);
}

it('returns 0 for a user with no sessions', function () {
    expect($this->service->current($this->user->id))->toBe(0)
        ->and($this->service->longest($this->user->id))->toBe(0);
});

it('counts consecutive days ending today', function () {
    foreach ([0, 1, 2] as $d) {
        finishedDaysAgo($this->user, $d);
    }

    expect($this->service->current($this->user->id))->toBe(3);
});

it('keeps the streak alive when the latest session was yesterday', function () {
    // Practised yesterday and the two days before — but not yet today.
    foreach ([1, 2, 3] as $d) {
        finishedDaysAgo($this->user, $d);
    }

    expect($this->service->current($this->user->id))->toBe(3);
});

it('breaks the current streak when the latest session is older than yesterday', function () {
    foreach ([2, 3] as $d) {
        finishedDaysAgo($this->user, $d);
    }

    expect($this->service->current($this->user->id))->toBe(0);
});

it('breaks the streak on a gap', function () {
    finishedDaysAgo($this->user, 0);
    finishedDaysAgo($this->user, 2); // gap on day 1

    expect($this->service->current($this->user->id))->toBe(1);
});

it('collapses multiple sessions on the same day into one streak day', function () {
    finishedDaysAgo($this->user, 0);
    finishedDaysAgo($this->user, 0);
    finishedDaysAgo($this->user, 1);

    expect($this->service->current($this->user->id))->toBe(2);
});

it('reports the longest historical run independent of the current streak', function () {
    // A broken 3-day run in the past, nothing recent.
    foreach ([10, 11, 12] as $d) {
        finishedDaysAgo($this->user, $d);
    }

    expect($this->service->longest($this->user->id))->toBe(3)
        ->and($this->service->current($this->user->id))->toBe(0);
});

it('ignores unfinished sessions', function () {
    SessionLog::factory()->for($this->user)->create(['status' => 'interrupted', 'finished_at' => null]);
    SessionLog::factory()->for($this->user)->create(['status' => 'active', 'finished_at' => null]);

    expect($this->service->current($this->user->id))->toBe(0);
});

it('does not count another user\'s sessions', function () {
    finishedDaysAgo($this->user, 0);
    $stranger = User::factory()->create();

    expect($this->service->current($stranger->id))->toBe(0);
});

it('recomputes after invalidation', function () {
    finishedDaysAgo($this->user, 0);
    expect($this->service->current($this->user->id))->toBe(1); // caches 1

    finishedDaysAgo($this->user, 1);
    expect($this->service->current($this->user->id))->toBe(1); // still cached

    $this->service->invalidate($this->user->id);
    expect($this->service->current($this->user->id))->toBe(2); // recomputed
});
