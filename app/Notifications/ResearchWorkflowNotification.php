<?php

namespace App\Notifications;

use App\Enums\SubmissionStatus;
use App\Models\ResearchSubmission;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ResearchWorkflowNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ResearchSubmission $submission,
        public User $actor,
        public string $action,
        public SubmissionStatus $status,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $url = match (true) {
            $notifiable instanceof User && $notifiable->isFaculty() => route('faculty.submissions.show', $this->submission->uuid),
            $notifiable instanceof User && $notifiable->isCoordinator() => route('coordinator.evaluations.show', $this->submission->uuid),
            $notifiable instanceof User && $notifiable->isAdmin() => route('admin.approvals.show', $this->submission->uuid),
            default => route('dashboard'),
        };

        return [
            'title' => match ($this->action) {
                'submitted' => 'New research submission',
                'evaluated' => 'Research submission evaluated',
                'approved' => 'Research submission approved',
                'rejected' => 'Research submission rejected',
                default => 'Research submission updated',
            },
            'message' => $this->actor->name.' '.$this->action.' "'.$this->submission->title.'".',
            'submission_uuid' => $this->submission->uuid,
            'reference_code' => $this->submission->reference_code,
            'status' => $this->status->value,
            'action' => $this->action,
            'url' => $url,
            'created_at' => now()->toIso8601String(),
        ];
    }
}
