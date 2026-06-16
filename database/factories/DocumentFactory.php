<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $markdown = '# '.rtrim(fake()->sentence(4), '.')."\n\n".fake()->paragraphs(3, true);

        return [
            'project_id' => Project::factory(),
            // Keep user_id consistent with the owning project (tenant integrity).
            'user_id' => fn (array $attributes) => Project::find($attributes['project_id'])?->user_id,
            'filename' => fake()->slug(3).'.md',
            'raw_markdown' => $markdown,
            'file_size_bytes' => strlen($markdown),
            'status' => 'pending',
            'error_detail' => null,
            'extraction_attempts' => 0,
            'extracted_unit_count' => null,
        ];
    }

    public function done(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'done',
            'extracted_unit_count' => fake()->numberBetween(3, 20),
        ]);
    }

    public function error(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'error',
            'error_detail' => 'Modell-Server nicht erreichbar (Timeout nach 120 s).',
            'extraction_attempts' => 2,
        ]);
    }
}
