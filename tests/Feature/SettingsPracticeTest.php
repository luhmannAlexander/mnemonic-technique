<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('shows the practice settings page', function () {
    actingAs(User::factory()->create())
        ->get(route('settings.practice'))
        ->assertOk();
});

it('loads the current session length', function () {
    $user = User::factory()->create();
    $user->settings()->update(['session_length' => 15]);

    Livewire::actingAs($user)
        ->test('pages::settings.practice')
        ->assertSet('session_length', 15);
});

it('saves a new session length', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.practice')
        ->set('session_length', 20)
        ->call('save')
        ->assertHasNoErrors();

    expect($user->settings->fresh()->session_length)->toBe(20);
});

it('rejects a session length outside 5–25', function (int $value) {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.practice')
        ->set('session_length', $value)
        ->call('save')
        ->assertHasErrors(['session_length']);
})->with([4, 26, 0]);
