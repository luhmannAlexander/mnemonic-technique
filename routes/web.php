<?php

use App\Exceptions\NoCardsAvailableException;
use App\Models\Project;
use App\Services\SessionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'dashboard')->name('dashboard');

    // Lernprojekte (AppFlow §1.3)
    Route::livewire('projects', 'projects.project-list')->name('projects.index');
    Route::livewire('projects/{project}', 'projects.project-overview')->name('projects.show');

    // Dokumente (AppFlow §2.7, §2.8) — scoped bindings enforce parent-child link
    Route::livewire('projects/{project}/documents', 'documents.document-list')->name('documents.index');
    Route::livewire('projects/{project}/documents/{document}', 'documents.document-detail')
        ->scopeBindings()
        ->name('documents.show');

    // Karten – interaktive Kartenansicht (AppFlow §2.10)
    Route::livewire('projects/{project}/cards', 'cards.card-list')->name('cards.index');

    // Review der Draft-Einheiten (AppFlow §2.9)
    Route::livewire('projects/{project}/review', 'review.review-list')->name('review.index');

    // Globaler Upload (AppFlow §2.5)
    Route::livewire('upload', 'upload.global-upload')->name('upload.index');

    // Papierkorb (AppFlow §2.13)
    Route::livewire('trash', 'trash.trash-list')->name('trash.index');

    // Übungssession – Fokusmodus (AppFlow §2.11).
    // Entry points create/resume a session, then redirect into the focus screen.
    Route::get('practice/today', function () {
        $type = request('type') === 'voluntary' ? 'voluntary' : 'due';

        try {
            $session = app(SessionService::class)->start(Auth::id(), null, $type);
        } catch (NoCardsAvailableException) {
            return redirect()->route('dashboard')->with('status', __('Aktuell ist keine Karte fällig.'));
        }

        return redirect()->route('practice.session', $session);
    })->name('practice.today');

    Route::get('projects/{project}/practice', function (Project $project) {
        abort_unless($project->user_id === Auth::id(), 404);

        $type = request('type') === 'voluntary' ? 'voluntary' : 'due';

        try {
            $session = app(SessionService::class)->start(Auth::id(), $project->id, $type);
        } catch (NoCardsAvailableException) {
            return redirect()->route('projects.show', $project)->with('status', __('Aktuell ist keine Karte fällig.'));
        }

        return redirect()->route('practice.session', $session);
    })->name('practice.project');

    Route::livewire('practice/{session}', 'practice.practice-session')->name('practice.session');
    Route::livewire('practice/{session}/summary', 'practice.session-summary')->name('practice.summary');

    // Statistiken (AppFlow §2.12)
    Route::livewire('stats', 'stats.global-stats')->name('stats.index');
    Route::livewire('projects/{project}/stats', 'stats.project-stats')->name('stats.project');
});

require __DIR__.'/settings.php';
