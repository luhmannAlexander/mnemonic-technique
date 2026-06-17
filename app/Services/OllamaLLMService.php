<?php

namespace App\Services;

use App\Contracts\LLMServiceInterface;
use App\Exceptions\LLMException;
use Illuminate\Support\Facades\Http;

/**
 * Talks to a local Ollama server over /api/chat with `format: json`.
 *
 * Every prompt is German (du-Form output) and pins an exact JSON schema; the
 * raw response is decoded and shape-checked by {@see parseAndValidate()} so a
 * malformed reply becomes an {@see LLMException} rather than a silent bad row.
 *
 * `think` is disabled because qwen3 is a reasoning model: without it the chat
 * response is prefixed with a <think> trace that breaks JSON parsing.
 */
class OllamaLLMService implements LLMServiceInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly int $timeoutExtract,
        private readonly int $timeoutGrade,
    ) {}

    public function extract(string $markdown): array
    {
        $system = <<<'PROMPT'
        Du bist ein präziser Wissensextraktions-Assistent für eine Lern-App. Analysiere den Markdown-Text und zerlege ihn in atomare, eigenständig lernbare Wissenseinheiten.

        Regeln:
        - Antworte ausschließlich auf Deutsch und in der Du-Form.
        - Jede Einheit behandelt genau einen Gedanken (atomar, nicht mehrere Fakten bündeln).
        - Erfinde nichts dazu; bleibe inhaltlich am Quelltext.
        - Wähle den Typ exakt aus: "fact" (einzelne Tatsache/Datum/Wert), "concept" (Begriff/Definition/Prinzip), "relation" (Zusammenhang/Ursache-Wirkung/Vergleich), "vocab" (Vokabel/Fachbegriff-Übersetzung).
        - "title" ist eine kurze Frage oder ein Stichwort (max. 12 Wörter). "content" ist die vollständige, eigenständige Antwort.
        - "source_ref" verweist auf die Stelle im Text (z. B. Überschrift oder Abschnitt), sonst null.
        - "topic_tag" ist ein knappes Themenschlagwort (1–3 Wörter).
        - Wähle eine passende Merktechnik: "spaced" (Standard), "acronym", "story", "loci", "major". "technique_material" enthält eine konkrete, auf den Inhalt zugeschnittene Merkhilfe (z. B. die Eselsbrücke selbst) oder null.

        Antworte NUR mit validem JSON ohne Markdown-Codeblöcke, exakt nach diesem Schema:
        {"units":[{"type":"fact|concept|relation|vocab","title":"...","content":"...","source_ref":"... oder null","topic_tag":"...","technique":"spaced|acronym|story|loci|major","technique_material":"... oder null"}]}

        Beispiel:
        {"units":[{"type":"fact","title":"Wann fiel die Berliner Mauer?","content":"Die Berliner Mauer fiel am 9. November 1989.","source_ref":"Abschnitt: Wende","topic_tag":"Deutsche Geschichte","technique":"spaced","technique_material":null}]}
        PROMPT;

        $raw = $this->chat($system, $markdown, $this->timeoutExtract);

        return $this->parseAndValidate($raw, ['units']);
    }

    public function classifyUpload(string $markdown, array $existingProjects): array
    {
        $projectList = json_encode(
            array_map(fn (array $p): array => ['id' => $p['id'], 'name' => $p['name']], $existingProjects),
            JSON_UNESCAPED_UNICODE,
        );

        $system = <<<PROMPT
        Du ordnest hochgeladene Lernnotizen einem Lernprojekt zu. Hier sind die vorhandenen Projekte des Nutzers als JSON:
        {$projectList}

        Entscheide, ob der Text inhaltlich zu einem vorhandenen Projekt passt ("existing") oder ein neues Projekt braucht ("new").
        Regeln:
        - Antworte auf Deutsch in der Du-Form.
        - Bei "existing" gib die exakte "project_id" aus der Liste an.
        - Bei "new" schlage einen prägnanten "name" (2–4 Wörter) vor.
        - "reason" begründet die Zuordnung in einem kurzen Satz.
        - Gib bis zu drei Vorschläge zurück, den besten zuerst.

        Antworte NUR mit validem JSON ohne Codeblöcke nach diesem Schema:
        {"suggestions":[{"type":"existing|new","project_id":<zahl oder null>,"name":"<bei new, sonst null>","reason":"..."}]}
        PROMPT;

        $raw = $this->chat($system, $markdown, $this->timeoutGrade);

        return $this->parseAndValidate($raw, ['suggestions']);
    }

    public function generateQuestions(array $unit): array
    {
        $payload = json_encode([
            'type' => $unit['type'] ?? null,
            'title' => $unit['title'] ?? null,
            'content' => $unit['content'] ?? null,
            'topic_tag' => $unit['topic_tag'] ?? null,
        ], JSON_UNESCAPED_UNICODE);

        $system = <<<'PROMPT'
        Du erstellst Prüfungsfragen zu einer Wissenseinheit für eine Lern-App. Erzeuge genau eine Multiple-Choice-Frage und eine Freitext-Frage.

        Regeln:
        - Antworte auf Deutsch in der Du-Form.
        - Die MC-Frage hat genau vier Optionen, davon genau eine korrekte.
        - "correct_answer" wiederholt bei MC den Text der richtigen Option; bei der Freitext-Frage die ideale Musterantwort.
        - Die Fragen müssen sich allein aus dem Inhalt der Einheit beantworten lassen.

        Antworte NUR mit validem JSON ohne Codeblöcke nach diesem Schema:
        {"mc":{"prompt":"...","options":[{"text":"...","correct":true},{"text":"...","correct":false},{"text":"...","correct":false},{"text":"...","correct":false}],"correct_answer":"..."},"free":{"prompt":"...","correct_answer":"..."}}
        PROMPT;

        $raw = $this->chat($system, $payload, $this->timeoutGrade);

        return $this->parseAndValidate($raw, ['mc', 'free']);
    }

    public function gradeFreetextAnswer(array $unit, string $question, string $answer): array
    {
        $payload = json_encode([
            'einheit' => ['title' => $unit['title'] ?? null, 'content' => $unit['content'] ?? null],
            'frage' => $question,
            'antwort_des_nutzers' => $answer,
        ], JSON_UNESCAPED_UNICODE);

        $system = <<<'PROMPT'
        Du bewertest die Freitext-Antwort eines Lernenden gegen den Inhalt der Wissenseinheit.

        Regeln:
        - Antworte auf Deutsch in der Du-Form.
        - "result" ist "correct" (inhaltlich richtig), "partial" (teilweise richtig oder unvollständig) oder "wrong" (falsch).
        - "feedback" ist eine kurze, ermutigende Rückmeldung (1–2 Sätze), die bei Fehlern den korrekten Kern nennt.
        - Bewerte den Inhalt, nicht Rechtschreibung oder Formulierung.

        Antworte NUR mit validem JSON ohne Codeblöcke nach diesem Schema:
        {"result":"correct|partial|wrong","feedback":"..."}
        PROMPT;

        $raw = $this->chat($system, $payload, $this->timeoutGrade);

        return $this->parseAndValidate($raw, ['result', 'feedback']);
    }

    public function prioritiseReview(array $unitHistory): array
    {
        $payload = json_encode(['einheiten' => $unitHistory], JSON_UNESCAPED_UNICODE);

        $system = <<<'PROMPT'
        Du planst die nächste Wiederholungsrunde einer Lern-App nach Prinzipien des verteilten Lernens (spaced repetition).
        Du erhältst je Wissenseinheit ihre bisherige Lernhistorie (Versuche, Erfolge, letztes Ergebnis).

        Regeln:
        - Vergib je Einheit eine "priority" von 0 bis 100 (höher = dringender wiederholen). Häufige Fehler und lange Pausen erhöhen die Priorität.
        - Setze "due_at" als ISO-8601-Zeitstempel für die nächste Fälligkeit.

        Antworte NUR mit validem JSON ohne Codeblöcke nach diesem Schema:
        {"priorities":[{"knowledge_unit_id":<zahl>,"priority":<0-100>,"due_at":"<ISO 8601>"}]}
        PROMPT;

        $raw = $this->chat($system, $payload, $this->timeoutGrade);

        return $this->parseAndValidate($raw, ['priorities'])['priorities'];
    }

    /** Single round-trip to Ollama's chat endpoint, returning the message content. */
    private function chat(string $systemPrompt, string $userMessage, int $timeout): string
    {
        $response = Http::timeout($timeout)
            ->post("{$this->baseUrl}/api/chat", [
                'model' => $this->model,
                'format' => 'json',
                'stream' => false,
                'think' => false,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userMessage],
                ],
            ]);

        if ($response->failed()) {
            throw new LLMException("Ollama HTTP {$response->status()}: {$response->body()}");
        }

        $content = $response->json('message.content');

        if (! is_string($content) || $content === '') {
            throw new LLMException('Leere Antwort vom Modell.');
        }

        return $content;
    }

    /**
     * Decode the model's JSON and assert every required top-level key is present.
     *
     * @param  array<int, string>  $requiredKeys
     * @return array<string, mixed>
     */
    private function parseAndValidate(string $raw, array $requiredKeys): array
    {
        $clean = trim((string) preg_replace('/^```(?:json)?|```$/m', '', trim($raw)));
        $data = json_decode($clean, true);

        if (! is_array($data)) {
            throw new LLMException("Modell lieferte kein JSON: {$raw}");
        }

        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $data)) {
                throw new LLMException("Schlüssel '{$key}' fehlt in der Modellantwort.");
            }
        }

        return $data;
    }
}
