<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function upload(User $user): bool
    {
        return $user->can('documents.upload');
    }

    public function download(User $user, Document $document): bool
    {
        if ($user->can('documents.download')) {
            return true;
        }

        return $document->application->user_id === $user->id;
    }

    public function adminUpload(User $user): bool
    {
        return $user->can('documents.admin-upload');
    }

    public function reviewerUpload(User $user): bool
    {
        return $user->can('documents.reviewer-upload');
    }
}
