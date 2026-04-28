<?php

namespace App\Notifications;

use App\Models\QuestionnaireInstances;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuestionnaireSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public QuestionnaireInstances $instance,
        public bool $isUpdate = false,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $clientName = $this->instance->recipientContact->name ?? 'A client';
        $verb = $this->isUpdate ? 'updated' : 'submitted';

        $subject = "{$clientName} {$verb} the {$this->instance->name}";

        $mail = (new MailMessage())
            ->subject($subject)
            ->greeting('Heads up,')
            ->line("{$clientName} has {$verb} the {$this->instance->name} for booking {$this->instance->booking->name}.");

        $event = $this->instance->booking->events->first();
        if ($event && $event->key) {
            $mail->action('View on event', route('events.show', $event->key));
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'instance_id' => $this->instance->id,
            'questionnaire_name' => $this->instance->name,
            'is_update' => $this->isUpdate,
        ];
    }
}
