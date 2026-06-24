<?php

use App\Jobs\GradeFreetextJob;
use App\Jobs\PrioritiseReviewJob;
use App\Models\Attempt;
use App\Models\SessionLog;
use App\Models\SessionQuestion;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.focus')] #[Title('Übung')] class extends Component
{
    public SessionLog $session;

    public ?SessionQuestion $currentQuestion = null;

    public bool $answered = false;

    /** Result of the just-answered MC question (null for free-text — graded async). */
    public ?string $feedbackResult = null;

    /** Index of the MC option the user picked, so the view can highlight it. */
    public ?int $selectedOption = null;

    public string $freeAnswer = '';

    /** Set once if the KI is unreachable; surfaces a non-blocking notice (AppFlow §2.11). */
    public bool $aiUnavailable = false;

    public function mount(SessionLog $session): void
    {
        // Ownership is a 404, never a 403 (AppFlow §2.15).
        abort_unless($session->user_id === Auth::id(), 404);

        // A finished/abandoned session is not replayable — send to its result screen.
        if (in_array($session->status, ['finished', 'abandoned'], true)) {
            $this->redirectRoute('practice.summary', $session, navigate: true);

            return;
        }

        // Resuming reactivates an interrupted session (AppFlow §2.11).
        if ($session->status === 'interrupted') {
            $session->update(['status' => 'active']);
        }

        $this->session = $session;
        $this->loadCurrentQuestion();
    }

    /** Load the question at the cursor (`current_question_index` is 0-based, positions are 1-based). */
    public function loadCurrentQuestion(): void
    {
        $this->currentQuestion = SessionQuestion::with(['question', 'knowledgeUnit'])
            ->where('session_id', $this->session->id)
            ->where('position', $this->session->current_question_index + 1)
            ->first();

        if ($this->currentQuestion === null) {
            $this->finish();

            return;
        }

        if ($this->currentQuestion->presented_at === null) {
            $this->currentQuestion->update(['presented_at' => now()]);
        }
    }

    public function answerMC(int $optionIndex): void
    {
        if ($this->answered || $this->currentQuestion === null) {
            return;
        }

        $question = $this->currentQuestion->question;
        $options = $question->options_json ?? [];
        $chosen = $options[$optionIndex] ?? null;

        if ($chosen === null) {
            return;
        }

        $result = ($chosen['correct'] ?? false) ? 'correct' : 'wrong';

        $this->recordAttempt('mc', $chosen['text'] ?? '', $result);
        $this->tally($result, pending: false);

        if ($this->session->session_type === 'due') {
            PrioritiseReviewJob::dispatch(
                $this->currentQuestion->knowledge_unit_id,
                $this->session->user_id,
                $result,
            );
        }

        $this->selectedOption = $optionIndex;
        $this->feedbackResult = $result;
        $this->answered = true;
    }

    public function answerFree(): void
    {
        if ($this->answered || $this->currentQuestion === null || trim($this->freeAnswer) === '') {
            return;
        }

        $attempt = $this->recordAttempt('free', $this->freeAnswer, 'pending');
        $this->tally('pending', pending: true);

        // Grading is asynchronous — the user advances immediately, no blocking spinner.
        GradeFreetextJob::dispatch($attempt->id);

        $this->feedbackResult = null;
        $this->answered = true;
    }

    public function next(): void
    {
        $this->session->increment('current_question_index');

        $this->answered = false;
        $this->feedbackResult = null;
        $this->selectedOption = null;
        $this->freeAnswer = '';

        $this->loadCurrentQuestion();
    }

    public function finish(): void
    {
        $this->session->update(['status' => 'finished', 'finished_at' => now()]);

        $this->redirectRoute('practice.summary', $this->session, navigate: true);
    }

    public function interrupt(): void
    {
        $this->session->update(['status' => 'interrupted']);

        // Project sessions return to the project, global sessions to the dashboard.
        $this->session->project_id === null
            ? $this->redirectRoute('dashboard', navigate: true)
            : $this->redirectRoute('projects.show', $this->session->project_id, navigate: true);
    }

    /** Persist an attempt for the current question, denormalising stats fields (BackendSchema §5). */
    private function recordAttempt(string $kind, string $answer, string $result): Attempt
    {
        $unit = $this->currentQuestion->knowledgeUnit;

        return Attempt::create([
            'session_id' => $this->session->id,
            'session_question_id' => $this->currentQuestion->id,
            'question_id' => $this->currentQuestion->question_id,
            'knowledge_unit_id' => $this->currentQuestion->knowledge_unit_id,
            'user_id' => $this->session->user_id,
            'project_id' => $unit->project_id,
            'topic_tag' => $unit->topic_tag,
            'kind' => $kind,
            'given_answer' => $answer,
            'result' => $result,
            'attempted_at' => now(),
        ]);
    }

    /**
     * Update the live session counters. MC results land in their bucket
     * immediately; free-text answers only bump `questions_pending` here and the
     * grade bucket later (GradeFreetextJob), so totals never double-count.
     */
    private function tally(string $result, bool $pending): void
    {
        $this->session->increment('questions_answered');

        if ($pending) {
            $this->session->increment('questions_pending');

            return;
        }

        $column = match ($result) {
            'correct' => 'questions_correct',
            'partial' => 'questions_partial',
            default => 'questions_wrong',
        };

        $this->session->increment($column);
    }
}; ?>

<div class="mx-auto flex min-h-screen w-full max-w-[720px] flex-col px-4 py-4 md:py-6">
    {{-- Fortschrittsleiste: Balken + „Frage x von y" links, Schließen-X rechts (ContentGuidelines §8). --}}
    <header class="flex items-center gap-4">
        <div class="flex-1">
            <div class="mb-1.5 flex items-center justify-between text-sm text-text-secondary">
                <span>{{ __('Frage :current von :total', [
                    'current' => min($this->session->current_question_index + 1, $this->session->questions_total),
                    'total' => $this->session->questions_total,
                ]) }}</span>
                @if ($this->session->questions_pending > 0)
                    <span class="text-text-muted">
                        {{ trans_choice('{1}1 Bewertung ausstehend|[2,*]:count Bewertungen ausstehend', $this->session->questions_pending) }}
                    </span>
                @endif
            </div>
            <div class="h-1 w-full overflow-hidden rounded-full bg-surface-raised">
                <div
                    class="h-full rounded-full bg-success transition-all duration-300 ease-out"
                    style="width: {{ $this->session->questions_total > 0
                        ? round($this->session->current_question_index / $this->session->questions_total * 100)
                        : 0 }}%"
                ></div>
            </div>
        </div>

        <flux:modal.trigger name="interrupt-session">
            <flux:button variant="ghost" icon="x-mark" size="sm" inset aria-label="{{ __('Session unterbrechen') }}" />
        </flux:modal.trigger>
    </header>

    @if ($aiUnavailable)
        <div class="mt-4 flex items-start gap-2 rounded-lg border-l-[3px] border-warning bg-surface px-4 py-3 text-sm text-text-secondary">
            <flux:icon.exclamation-triangle class="mt-0.5 size-4 shrink-0 text-warning" />
            <span>{{ __('Die KI-Bewertung ist gerade nicht verfügbar – offene Freitext-Bewertungen werden nachgereicht.') }}</span>
        </div>
    @endif

    @if ($currentQuestion)
        @php($question = $currentQuestion->question)
        @php($unit = $currentQuestion->knowledgeUnit)

        <main class="flex flex-1 flex-col justify-center gap-6 py-8">
            <h1 class="text-3xl font-bold leading-snug">{{ $question->prompt }}</h1>

            @if ($question->kind === 'mc')
                <div class="flex flex-col gap-3" role="group">
                    @foreach ($question->options_json as $index => $option)
                        @php($isChosen = $selectedOption === $index)
                        @php($isCorrectOption = (bool) ($option['correct'] ?? false))
                        <button
                            type="button"
                            wire:click="answerMC({{ $index }})"
                            @disabled($answered)
                            @class([
                                'flex min-h-[56px] items-center rounded-xl border px-5 text-left text-base transition',
                                // Before answering: neutral, hoverable.
                                'border-border bg-surface hover:border-primary hover:bg-surface-raised' => ! $answered,
                                // After answering: green for the correct option, red for a wrong pick, dimmed otherwise.
                                'border-success bg-success/10 text-text' => $answered && $isCorrectOption,
                                'border-danger bg-danger/10 text-text' => $answered && $isChosen && ! $isCorrectOption,
                                'border-border bg-surface opacity-40' => $answered && ! $isCorrectOption && ! $isChosen,
                            ])
                        >
                            <span class="flex-1">{{ $option['text'] }}</span>
                            @if ($answered && $isCorrectOption)
                                <flux:icon.check class="size-5 text-success" />
                            @elseif ($answered && $isChosen)
                                <flux:icon.x-mark class="size-5 text-danger" />
                            @endif
                        </button>
                    @endforeach
                </div>

                @if ($answered)
                    {{-- Auflösung: semantischer Farbstreifen + Erklärung + aufklappbare Merkhilfe (AppFlow §2.11). --}}
                    <div @class([
                        'rounded-lg border-l-[3px] bg-surface px-4 py-3',
                        'border-success' => $feedbackResult === 'correct',
                        'border-danger' => $feedbackResult !== 'correct',
                    ])>
                        <p class="text-sm font-medium {{ $feedbackResult === 'correct' ? 'text-success' : 'text-danger' }}">
                            {{ $feedbackResult === 'correct' ? __('Richtig!') : __('Leider falsch.') }}
                        </p>
                        <p class="mt-1 text-sm text-text-secondary">
                            {{ __('Richtige Antwort:') }} {{ $question->correct_answer }}
                        </p>
                        @if (filled($unit->technique_material))
                            <details class="mt-2 text-sm">
                                <summary class="cursor-pointer text-text-secondary hover:text-text">{{ __('Merkhilfe anzeigen') }}</summary>
                                <p class="mt-2 whitespace-pre-line text-text-secondary">{{ $unit->technique_material }}</p>
                            </details>
                        @endif
                    </div>
                @endif
            @else
                {{-- Freitext: Textfeld + „Antwort absenden"; Bewertung läuft asynchron. --}}
                @if (! $answered)
                    <flux:textarea
                        wire:model="freeAnswer"
                        rows="4"
                        :placeholder="__('Deine Antwort …')"
                        autofocus
                    />
                @else
                    <div class="rounded-lg border-l-[3px] border-info bg-surface px-4 py-3 text-sm text-text-secondary">
                        {{ __('Antwort eingereicht – die Bewertung läuft im Hintergrund und erscheint im Ergebnis.') }}
                    </div>
                @endif
            @endif
        </main>

        {{-- Aktionsleiste unten (Daumenzone auf Mobile, ContentGuidelines §8). --}}
        <footer class="sticky bottom-0 flex flex-col gap-3 bg-bg-focus py-4">
            @if (! $answered && $question->kind === 'free')
                <flux:button variant="primary" wire:click="answerFree" class="w-full">
                    {{ __('Antwort absenden') }}
                </flux:button>
            @elseif ($answered)
                <flux:button variant="primary" wire:click="next" class="w-full">
                    {{ $this->session->current_question_index + 1 >= $this->session->questions_total ? __('Session abschließen') : __('Weiter') }}
                </flux:button>
            @endif
        </footer>
    @endif

    {{-- Abbruch-Dialog (ContentGuidelines §5.4). --}}
    <flux:modal name="interrupt-session" class="max-w-sm">
        <div class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Session unterbrechen?') }}</flux:heading>
            <flux:text class="text-text-secondary">
                {{ __('Dein Fortschritt bleibt gespeichert, du kannst später fortsetzen.') }}
            </flux:text>
            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Weiter üben') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="interrupt">{{ __('Unterbrechen') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
