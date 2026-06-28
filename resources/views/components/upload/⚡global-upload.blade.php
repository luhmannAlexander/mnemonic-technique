<?php

use App\Jobs\ClassifyUploadJob;
use App\Jobs\PromoteUploadStagingJob;
use App\Models\Project;
use App\Models\UploadStaging;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Upload')] class extends Component
{
    use WithFileUploads;

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $files = [];

    /** Per-staging project selection: staging id => project id or "__new__". */
    public array $assign = [];

    /** Per-staging new-project name when "__new__" is selected. */
    public array $newProjectName = [];

    /**
     * Unconfirmed, non-expired stagings for this user — re-shown on every visit (AppFlow §2.5).
     *
     * @return \Illuminate\Support\Collection<int, UploadStaging>
     */
    #[Computed]
    public function stagings()
    {
        return UploadStaging::query()
            ->where('user_id', Auth::id())
            ->whereNull('confirmed_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Project>
     */
    #[Computed]
    public function projects()
    {
        return Project::query()->where('user_id', Auth::id())->orderBy('name')->get();
    }

    /** True while any staging is still being classified — drives wire:poll. */
    #[Computed]
    public function hasPendingClassification(): bool
    {
        return $this->stagings->contains(
            fn (UploadStaging $s): bool => in_array($s->classification_status, ['pending', 'classifying'], true),
        );
    }

    // NB: must NOT be named upload()/uploadMultiple()/removeUpload() — those are
    // reserved Livewire $wire JS magics; wire:submit would hit the JS file
    // uploader instead of this server action and crash on an undefined file.
    public function save(): void
    {
        $this->validate([
            'files' => ['required', 'array'],
            'files.*' => ['file', 'extensions:md,txt', 'max:2048'],
        ], [
            'files.*.extensions' => __('Nur Markdown-Dateien (.md) sind erlaubt. Wandle die Datei um oder wähle eine andere.'),
            'files.*.max' => __('Die Datei ist größer als 2 MB. Verkleinere sie oder teile den Inhalt auf.'),
        ]);

        foreach ($this->files as $file) {
            $staging = UploadStaging::create([
                'user_id' => Auth::id(),
                'filename' => $file->getClientOriginalName(),
                'raw_markdown' => $file->get(),
                'file_size_bytes' => $file->getSize(),
                'classification_status' => 'pending',
                'expires_at' => now()->addDays(7),
            ]);

            ClassifyUploadJob::dispatch($staging->id);
        }

        $this->reset('files');
        unset($this->stagings);
    }

    public function assignStaging(int $id): void
    {
        $staging = $this->ownedStagingOrFail($id);
        $choice = $this->assign[$id] ?? null;

        if ($choice === '__new__') {
            $name = trim((string) ($this->newProjectName[$id] ?? ''));
            $this->validate(
                ["newProjectName.{$id}" => ['required', 'string', 'max:120']],
                [], ["newProjectName.{$id}" => __('Projektname')]
            );

            $staging->update([
                'assigned_project_id' => null,
                'assigned_project_name' => $name,
                'classification_status' => 'awaiting_confirmation',
            ]);
        } else {
            $project = Project::where('user_id', Auth::id())->find((int) $choice);
            abort_unless($project !== null, 404);

            $staging->update([
                'assigned_project_id' => $project->id,
                'assigned_project_name' => $project->name,
                'classification_status' => 'awaiting_confirmation',
            ]);
        }

        unset($this->stagings);
    }

    /** Apply one of the KI suggestions (ImplementationPlan §2.4) for the user to confirm. */
    public function acceptSuggestion(int $id, int $index): void
    {
        $staging = $this->ownedStagingOrFail($id);

        $suggestion = data_get($staging->ai_suggestion_payload, "suggestions.{$index}");
        abort_if(! is_array($suggestion), 404);

        if (($suggestion['type'] ?? null) === 'existing') {
            // Never trust an LLM-provided id — it must be one of the user's own projects.
            $project = Project::where('user_id', Auth::id())->find((int) ($suggestion['project_id'] ?? 0));
            abort_unless($project !== null, 404);

            $staging->update([
                'assigned_project_id' => $project->id,
                'assigned_project_name' => $project->name,
                'classification_status' => 'awaiting_confirmation',
            ]);
        } else {
            $name = trim((string) ($suggestion['name'] ?? ''));
            abort_if($name === '', 404);

            $staging->update([
                'assigned_project_id' => null,
                'assigned_project_name' => $name,
                'classification_status' => 'awaiting_confirmation',
            ]);
        }

        unset($this->stagings);
    }

    public function confirmAssignment(int $id): void
    {
        $staging = $this->ownedStagingOrFail($id);

        abort_unless(
            $staging->assigned_project_id !== null || filled($staging->assigned_project_name),
            422,
        );

        PromoteUploadStagingJob::dispatchSync($staging->id);

        unset($this->stagings);
        Flux::toast(text: __('Zuordnung bestätigt – die Karten werden erstellt.'), variant: 'success');
    }

    public function confirmAll(): void
    {
        $ready = UploadStaging::query()
            ->where('user_id', Auth::id())
            ->where('classification_status', 'awaiting_confirmation')
            ->whereNull('confirmed_at')
            ->get();

        foreach ($ready as $staging) {
            if ($staging->assigned_project_id !== null || filled($staging->assigned_project_name)) {
                PromoteUploadStagingJob::dispatchSync($staging->id);
            }
        }

        unset($this->stagings);
        Flux::toast(text: __('Alle Zuordnungen bestätigt.'), variant: 'success');
    }

    public function discard(int $id): void
    {
        $this->ownedStagingOrFail($id)->delete();

        unset($this->stagings);
    }

    private function ownedStagingOrFail(int $id): UploadStaging
    {
        $staging = UploadStaging::where('user_id', Auth::id())->find($id);

        abort_unless($staging !== null, 404);

        return $staging;
    }
}; ?>

<div class="mx-auto w-full max-w-[1200px] px-4 py-6 md:px-8">
    <flux:heading size="xl" level="1" class="mb-6">{{ __('Upload') }}</flux:heading>

    {{-- Upload area --}}
    <form wire:submit="save" class="mb-8 flex flex-col gap-3 rounded-2xl bg-surface p-4 shadow-md shadow-black/40">
        <flux:input
            type="file"
            wire:model="files"
            multiple
            accept=".md,.txt"
            :label="__('Markdown-Dateien hochladen')"
            :description="__('Die KI ordnet sie einem Lernprojekt zu. Nur .md, max. 2 MB pro Datei.')"
        />
        <flux:error name="files.*" />
        <div class="flex items-center justify-end">
            <flux:button type="submit" variant="primary" icon="arrow-up-tray">{{ __('Hochladen') }}</flux:button>
        </div>
    </form>

    @if ($this->projects->isEmpty() && $this->stagings->isEmpty())
        <flux:callout icon="information-circle" class="mb-6">
            {{ __('Du hast noch keine Lernprojekte – beim Hochladen kannst du direkt ein neues anlegen.') }}
        </flux:callout>
    @endif

    {{-- Staging rows (AppFlow §2.5) --}}
    @if ($this->stagings->isNotEmpty())
        <div class="mb-4 flex items-center justify-between">
            <flux:heading size="lg">{{ __('Unzugeordnete Uploads') }}</flux:heading>
            <flux:button size="sm" variant="primary" wire:click="confirmAll">{{ __('Alle bestätigen') }}</flux:button>
        </div>

        {{-- Auto-refresh while the KI is still classifying any upload (no #[Poll] attr in this build). --}}
        <div class="flex flex-col gap-3" @if ($this->hasPendingClassification) wire:poll.3s @endif>
            @foreach ($this->stagings as $staging)
                @php
                    $assigned = $staging->assigned_project_id !== null || filled($staging->assigned_project_name);
                    $suggestions = data_get($staging->ai_suggestion_payload, 'suggestions', []);
                @endphp
                <div class="flex flex-col gap-3 rounded-2xl bg-surface p-4 shadow-md shadow-black/40" wire:key="staging-{{ $staging->id }}">
                    <div class="flex items-center gap-2">
                        <flux:icon.document-text class="size-4 shrink-0 text-text-secondary" />
                        <span class="min-w-0 flex-1 truncate text-text">{{ $staging->filename }}</span>
                        <flux:button size="sm" variant="ghost" icon="trash" :aria-label="__('Upload verwerfen')" wire:click="discard({{ $staging->id }})" />
                    </div>

                    @if (in_array($staging->classification_status, ['pending', 'classifying'], true))
                        {{-- KI is still working — the list auto-refreshes via wire:poll above. --}}
                        <div class="flex items-center gap-2 text-sm text-text-secondary">
                            <flux:icon.arrow-path class="size-4 animate-spin" />
                            {{ __('Wird automatisch zugeordnet …') }}
                        </div>
                    @elseif ($assigned)
                        {{-- A project is chosen (KI-Vorschlag übernommen oder manuell) → bestätigen. --}}
                        <div class="flex flex-wrap items-center gap-3">
                            <flux:badge size="sm" color="violet" icon="folder">{{ $staging->assigned_project_name }}</flux:badge>
                            <flux:spacer />
                            <flux:button size="sm" variant="ghost" wire:click="assignStaging({{ $staging->id }})">{{ __('Ändern') }}</flux:button>
                            <flux:button size="sm" variant="primary" icon="check" wire:click="confirmAssignment({{ $staging->id }})">{{ __('Zuordnung bestätigen') }}</flux:button>
                        </div>
                    @else
                        @if (! empty($suggestions))
                            {{-- KI-Vorschläge (AppFlow §2.5, ImplementationPlan §2.4) --}}
                            <flux:text class="text-sm text-text-secondary">{{ __('Vorschläge der KI:') }}</flux:text>
                            <div class="flex flex-col gap-2">
                                @foreach ($suggestions as $i => $suggestion)
                                    <div class="flex flex-wrap items-center gap-2 rounded-xl bg-surface-raised p-3" wire:key="sugg-{{ $staging->id }}-{{ $i }}">
                                        <flux:icon.sparkles class="size-4 shrink-0 text-primary" />
                                        <span class="font-medium text-text">{{ $suggestion['name'] ?? __('Vorhandenes Projekt') }}</span>
                                        @if (($suggestion['type'] ?? null) === 'new')
                                            <flux:badge size="sm" color="zinc">{{ __('neu') }}</flux:badge>
                                        @endif
                                        @if (filled($suggestion['reason'] ?? null))
                                            <span class="w-full text-sm text-text-secondary md:flex-1">{{ $suggestion['reason'] }}</span>
                                        @endif
                                        <flux:button size="sm" variant="primary" icon="check" wire:click="acceptSuggestion({{ $staging->id }}, {{ $i }})">{{ __('Übernehmen') }}</flux:button>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            {{-- Keine KI-Vorschläge (Klassifizierung fehlgeschlagen) → manueller Fallback. --}}
                            <flux:text class="text-sm text-text-secondary">
                                {{ __('Automatische Zuordnung gerade nicht verfügbar – wähle ein Lernprojekt.') }}
                            </flux:text>
                        @endif

                        <div class="flex flex-wrap items-end gap-3">
                            <flux:select wire:model.live="assign.{{ $staging->id }}" :label="empty($suggestions) ? __('Lernprojekt') : __('Oder anderes Projekt wählen')" class="min-w-56">
                                <flux:select.option value="">{{ __('– auswählen –') }}</flux:select.option>
                                @foreach ($this->projects as $project)
                                    <flux:select.option value="{{ $project->id }}">{{ $project->name }}</flux:select.option>
                                @endforeach
                                <flux:select.option value="__new__">{{ __('+ Neues Lernprojekt') }}</flux:select.option>
                            </flux:select>

                            @if (($assign[$staging->id] ?? null) === '__new__')
                                <flux:input wire:model="newProjectName.{{ $staging->id }}" :label="__('Name des Projekts')" class="min-w-56" />
                            @endif

                            <flux:button size="sm" variant="primary" wire:click="assignStaging({{ $staging->id }})">{{ __('Zuordnen') }}</flux:button>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
