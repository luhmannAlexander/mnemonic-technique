<?php

namespace App\Policies;

use App\Models\UploadStaging;
use App\Models\User;

class UploadStagingPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, UploadStaging $staging): bool
    {
        return $user->id === $staging->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, UploadStaging $staging): bool
    {
        return $user->id === $staging->user_id;
    }

    public function delete(User $user, UploadStaging $staging): bool
    {
        return $user->id === $staging->user_id;
    }
}
