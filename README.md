# The Mnemonic Technique

> **A learning app built end-to-end by [Claude Code](https://claude.com/claude-code).**
>
> This repository exists first and foremost as a hands-on way for me to deepen my
> understanding of Claude Code — how it plans, writes, tests, and hardens a real
> application. Every line of application code, every test, and every deployment
> artifact in this repo was written by Claude Code, working milestone by milestone
> from the specifications in [`docs/`](docs/). I directed the work; Claude Code did
> the engineering. Treat this project as both a working app **and** a reference for
> what an agent-driven build looks like in practice.

---

## What the app does

**The Mnemonic Technique** is a German-language, AI-assisted spaced-repetition
learning center. You upload your own study material as Markdown; a local AI extracts
the knowledge, turns it into interactive cards, picks a fitting mnemonic technique per
topic, and lets you practice with it. Wrong or shaky answers are stored and fed back
into an AI-driven review queue so you revisit exactly what you haven't retained yet.

The core user journey:

1. **Register / sign in** (email + password).
2. **Upload Markdown** into a "learning project" — or use the global upload and let
   the AI suggest which project it belongs to.
3. **AI extraction** turns the text into knowledge units (facts, concepts, vocabulary).
4. **Review & edit** the extracted drafts before approving them.
5. **Practice** in focused sessions mixing multiple-choice and AI-graded free-text
   questions, with immediate feedback.
6. **Review queue** resurfaces wrong/uncertain answers on an AI-scheduled cadence.
7. **Track progress** — retention rate and streaks on the dashboard.

The UI and all AI output are **German only** by design. It is a single-user model:
every user sees only their own content and progress (ownership is enforced strictly —
accessing another user's data returns a 404, never a 403).

## Tech stack

| Layer        | Choice                                                        |
|--------------|---------------------------------------------------------------|
| Framework    | Laravel 13, PHP 8.5                                           |
| Frontend     | Livewire 4 (single-file components) + Flux 2 + Tailwind CSS 4 |
| Charts       | Chart.js 4 (bundled via Vite)                                |
| Database     | MariaDB 11.8                                                  |
| Cache / Queue| Redis 7.4, Laravel Horizon                                   |
| Local AI     | Ollama running `qwen3:14b` (GPU / ROCm)                      |
| Dev runtime  | Laravel Sail (Docker)                                        |
| Tests        | Pest 4                                                        |
| Quality      | Pint (formatting) + Larastan / PHPStan level 6              |

All AI is **local** — no external API calls. Knowledge extraction, question
generation, free-text grading, and review scheduling run against a local Ollama model.

## Repository layout

```
mnemonic-technique/
├── docs/        # The specification Claude Code built from (PRD, AppFlow,
│                #   BackendSchema, ContentGuidelines, TechStack, ImplementationPlan)
├── mnemonic/    # The Laravel application (its own git repository)
├── samples/     # Example Markdown study material for uploading
└── README.md    # You are here
```

The specs in `docs/` were written explicitly for an AI coding agent as the reader,
and the app was implemented against them milestone by milestone.

## Running it locally

The app lives in [`mnemonic/`](mnemonic/) and runs via Laravel Sail (Docker). You'll
also need a running [Ollama](https://ollama.com/) instance with the `qwen3:14b` model
pulled, plus a GPU for reasonable inference speed.

```bash
cd mnemonic
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install && ./vendor/bin/sail npm run build
```

Then open <http://localhost>. See `mnemonic/docker/DEPLOY.md` for the hardened
production configuration (TLS reverse proxy, locked-down services, dedicated DB user).

### Quality gate

Every change is validated with the same gate Claude Code used throughout the build:

```bash
cd mnemonic
./vendor/bin/sail composer test     # full Pest suite
./vendor/bin/sail composer format   # Pint
./vendor/bin/sail composer analyse  # PHPStan level 6
```

## Status

The app is feature-complete through the planned milestones (M1–M4) plus production
hardening, tagged `v1.0.0`. It covers accounts, learning projects, Markdown upload
with AI classification, extraction and review, spaced-repetition practice, the
AI-driven review queue, and a statistics dashboard (retention trends and streaks).

## Why this exists

I built this with Claude Code to learn Claude Code — to see how an agent handles a
non-trivial, spec-driven application from first commit to a tagged release: making
architectural calls, writing its own tests, debugging real runtime issues, and
producing deployable artifacts. The result is a genuinely usable learning app, and
just as importantly, a worked example of agent-driven development I can study and
build on.

---

*Built by Claude Code. Directed by [luhmann.alexander@gmail.com](mailto:luhmann.alexander@gmail.com).*
