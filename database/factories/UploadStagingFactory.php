<?php

namespace Database\Factories;

use App\Models\UploadStaging;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UploadStaging>
 */
class UploadStagingFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $markdown = '# '.rtrim(fake()->sentence(4), '.')."\n\n".fake()->paragraph();

        return [
            'user_id' => User::factory(),
            'filename' => fake()->slug(3).'.md',
            'raw_markdown' => $markdown,
            'file_size_bytes' => strlen($markdown),
            'classification_status' => 'pending',
            'classification_error' => null,
            'ai_suggestion_payload' => null,
            'assigned_project_id' => null,
            'assigned_project_name' => null,
            'confirmed_at' => null,
            'expires_at' => now()->addDays(7),
        ];
    }

    public function awaitingConfirmation(): static
    {
        return $this->state(fn (array $attributes) => [
            'classification_status' => 'awaiting_confirmation',
            'ai_suggestion_payload' => [
                'suggestions' => [
                    ['type' => 'new', 'name' => 'Business English', 'reason' => 'Testvorschlag'],
                ],
            ],
        ]);
    }
}
