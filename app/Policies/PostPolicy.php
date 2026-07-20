<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function view(User $user, Post $post): bool
    {
        if (! $user->belongsToOrganization($post->organization_id)) {
            return false;
        }

        return $user->isAdmin() || (int) $post->user_id === (int) $user->id;
    }

    public function update(User $user, Post $post): bool
    {
        return $post->canBeEditedBy($user);
    }

    public function delete(User $user, Post $post): bool
    {
        return $post->canBeDeletedBy($user);
    }

    public function review(User $user, Post $post): bool
    {
        return $user->belongsToOrganization($post->organization_id) && $user->isAdmin();
    }
}
