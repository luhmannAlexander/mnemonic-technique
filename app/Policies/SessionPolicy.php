<?php

namespace App\Policies;

use App\Models\SessionLog;
use App\Models\User;

class SessionPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return null;
    }

    public function view(User $user, SessionLog $session): bool
    {
        return $user->id === $session->user_id;
    }

    public function update(User $user, SessionLog $session): bool
    {
        return $user->id === $session->user_id;
    }

    public function delete(User $user, SessionLog $session): bool
    {
        return $user->id === $session->user_id;
    }
}
