<?php

namespace App\Observers;

use App\Models\User;
use App\Models\UserSetting;

class UserObserver
{
    /**
     * Create the default settings row on registration (ImplementationPlan 1.7,
     * BackendSchema §2.2). firstOrCreate keeps this idempotent if a settings row
     * already exists (e.g. created explicitly in a test).
     */
    public function created(User $user): void
    {
        UserSetting::firstOrCreate(
            ['user_id' => $user->id],
            ['session_length' => 10],
        );
    }
}
