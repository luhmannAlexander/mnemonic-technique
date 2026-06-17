<?php

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
});

require __DIR__.'/settings.php';
