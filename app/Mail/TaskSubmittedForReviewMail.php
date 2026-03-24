<?php

namespace App\Mail;

use App\Models\ApplicationTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TaskSubmittedForReviewMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ApplicationTask $task) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('tasks.submitted_for_review_subject', [
                'reference' => $this->task->application->reference_number,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.task-submitted-for-review',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
