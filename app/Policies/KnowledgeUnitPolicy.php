<?php

namespace App\Policies;

use App\Models\KnowledgeUnit;
use App\Models\User;

class KnowledgeUnitPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, KnowledgeUnit $unit): bool
    {
        return $user->id === $unit->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, KnowledgeUnit $unit): bool
    {
        return $user->id === $unit->user_id;
    }

    public function delete(User $user, KnowledgeUnit $unit): bool
    {
        return $user->id === $unit->user_id;
    }

    public function restore(User $user, KnowledgeUnit $unit): bool
    {
        return $user->id === $unit->user_id;
    }

    public function forceDelete(User $user, KnowledgeUnit $unit): bool
    {
        return $user->id === $unit->user_id;
    }
}
