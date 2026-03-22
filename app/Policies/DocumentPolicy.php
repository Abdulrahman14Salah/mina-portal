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

    public function delete(User $user, Document $document): bool
    {
        if ($user->can('documents.delete')) {
            return true;
        }

        if (! $user->can('documents.delete-own')) {
            return false;
        }

        if ($document->uploaded_by !== $user->id) {
            return false;
        }

        if ($document->application_task_id === null) {
            return true;
        }

        $task = $document->task;

        return $task !== null && ! in_array($task->status, ['completed', 'rejected']);
    }
}
