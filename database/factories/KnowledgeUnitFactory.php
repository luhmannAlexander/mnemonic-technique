<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\KnowledgeUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeUnit>
 */
class KnowledgeUnitFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            // Derive project_id and user_id from the owning document for consistency.
            'project_id' => fn (array $attributes) => Document::find($attributes['document_id'])?->project_id,
            'user_id' => fn (array $attributes) => Document::find($attributes['document_id'])?->user_id,
            'type' => fake()->randomElement(['fact', 'concept', 'relation', 'vocab']),
            'title' => rtrim(fake()->sentence(6), '.'),
            'content' => fake()->paragraph(),
            'source_ref' => 'kapitel-1.md, Abschnitt 1',
            'topic_tag' => ucfirst(fake()->word()),
            'unit_status' => 'draft',
            'technique' => 'spaced',
            'technique_material' => null,
            'manually_edited' => false,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'unit_status' => 'approved',
        ]);
    }
}
