<?php

use App\Models\Project;
use App\Models\User;
use App\Policies\ProjectPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('grants the owner access to their project', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->create();

    $policy = new ProjectPolicy;

    expect($policy->view($owner, $project))->toBeTrue()
        ->and($policy->update($owner, $project))->toBeTrue()
        ->and($policy->delete($owner, $project))->toBeTrue();
});

it('denies access to another user\'s project', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->for($owner)->create();

    $policy = new ProjectPolicy;

    expect($policy->view($other, $project))->toBeFalse()
        ->and($policy->update($other, $project))->toBeFalse()
        ->and($policy->delete($other, $project))->toBeFalse();
});
