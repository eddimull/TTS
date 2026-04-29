<?php

namespace App\Notifications;

use App\Models\QuestionnaireInstances;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuestionnaireSent extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public QuestionnaireInstances $instance)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $band = $this->instance->booking->band;
        $url = route('portal.booking.questionnaire.show', [
            'booking' => $this->instance->booking_id,
            'instance' => $this->instance->id,
        ]);

        $mail = (new MailMessage())
            ->subject($band->name . ': Please complete the ' . $this->instance->name)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line($band->name . ' has sent you a questionnaire to complete: ' . $this->instance->name)
            ->action('Open Questionnaire', $url)
            ->line('You can save your progress as you go and return to finish later.');

        if ($band->email) {
            $mail->from($band->email, $band->name);
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'instance_id' => $this->instance->id,
            'questionnaire_name' => $this->instance->name,
            'booking_id' => $this->instance->booking_id,
        ];
    }
}
