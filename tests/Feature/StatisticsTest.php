<?php

use App\Models\Attempt;
use App\Models\Project;
use App\Models\User;
use App\Services\StatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake(); // the attempt factory chain creates units; keep observer jobs out
    $this->user = User::factory()->create();
    $this->service = app(StatsService::class);
});

it('calculates the current retention rate', function () {
    Attempt::factory(8)->create(['user_id' => $this->user->id, 'result' => 'correct']);
    Attempt::factory(2)->create(['user_id' => $this->user->id, 'result' => 'wrong']);

    expect($this->service->currentRetention($this->user->id))->toBe(80.0);
});

it('returns zero retention for a user with no attempts', function () {
    expect($this->service->currentRetention($this->user->id))->toBe(0.0);
});

it('excludes pending attempts from retention', function () {
    Attempt::factory(2)->create(['user_id' => $this->user->id, 'result' => 'correct']);
    Attempt::factory(8)->create(['user_id' => $this->user->id, 'result' => 'pending']);

    // Only the 2 graded attempts count → 100%.
    expect($this->service->currentRetention($this->user->id))->toBe(100.0);
});

it('scopes retention to a project', function () {
    $project = Project::factory()->for($this->user)->create();
    Attempt::factory(3)->create(['user_id' => $this->user->id, 'project_id' => $project->id, 'result' => 'correct']);
    Attempt::factory(1)->create(['user_id' => $this->user->id, 'project_id' => $project->id, 'result' => 'wrong']);
    Attempt::factory(5)->create(['user_id' => $this->user->id, 'result' => 'wrong']); // other project

    expect($this->service->currentRetention($this->user->id, $project->id))->toBe(75.0);
});

it('builds a daily retention trend', function () {
    Attempt::factory(2)->create(['user_id' => $this->user->id, 'result' => 'correct', 'attempted_at' => now()->subDays(1)]);
    Attempt::factory(1)->create(['user_id' => $this->user->id, 'result' => 'wrong', 'attempted_at' => now()->subDays(1)]);

    $trend = $this->service->retentionTrend($this->user->id);

    expect($trend)->toHaveCount(1)
        ->and($trend[0]['total'])->toBe(3)
        ->and($trend[0]['correct'])->toBe(2)
        ->and($trend[0]['rate'])->toBe(66.7);
});

it('breaks retention down by topic', function () {
    $project = Project::factory()->for($this->user)->create();
    Attempt::factory(2)->create(['user_id' => $this->user->id, 'project_id' => $project->id, 'topic_tag' => 'Biologie', 'result' => 'correct']);
    Attempt::factory(2)->create(['user_id' => $this->user->id, 'project_id' => $project->id, 'topic_tag' => 'Chemie', 'result' => 'wrong']);

    $topics = collect($this->service->retentionByTopic($this->user->id, $project->id))->keyBy('topic_tag');

    expect($topics['Biologie']['rate'])->toBe(100.0)
        ->and($topics['Chemie']['rate'])->toBe(0.0);
});

it('renders the global stats page with the empty state when there is no data', function () {
    Livewire::actingAs($this->user)
        ->test('stats.global-stats')
        ->assertSee('Noch keine Daten');
});

it('renders the global stats page with data', function () {
    Attempt::factory(4)->create(['user_id' => $this->user->id, 'result' => 'correct']);
    Attempt::factory(1)->create(['user_id' => $this->user->id, 'result' => 'wrong']);

    Livewire::actingAs($this->user)
        ->test('stats.global-stats')
        ->assertSee('Behaltensquote')
        ->assertSee('80 %');
});

it('returns 404 on another user\'s project stats', function () {
    $stranger = User::factory()->create();
    $project = Project::factory()->for($stranger)->create();

    $this->actingAs($this->user)
        ->get(route('stats.project', $project))
        ->assertNotFound();
});

it('renders project stats scoped to the project', function () {
    $project = Project::factory()->for($this->user)->create();
    Attempt::factory(2)->create(['user_id' => $this->user->id, 'project_id' => $project->id, 'topic_tag' => 'Biologie', 'result' => 'correct']);

    Livewire::actingAs($this->user)
        ->test('stats.project-stats', ['project' => $project])
        ->assertSee('Biologie')
        ->assertSee('Nach Thema');
});
