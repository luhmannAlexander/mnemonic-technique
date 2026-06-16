<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserSetting>
 *
 * Note: in normal flow a settings row is created automatically by UserObserver on
 * registration. This factory exists for completeness / explicit setups; prefer
 * updating the auto-created row (e.g. $user->settings()->update([...])) in tests.
 */
class UserSettingFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'session_length' => 10,
            'sidebar_collapsed' => false,
        ];
    }
}
