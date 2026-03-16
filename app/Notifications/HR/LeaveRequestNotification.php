<?php

namespace App\Notifications\HR;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $message,
        public string $type,
        public string $link,
        public string $icon = 'mdi-calendar',
        public string $color = 'primary',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => $this->message,
            'type' => $this->type,
            'link' => $this->link,
            'icon' => $this->icon,
            'color' => $this->color,
        ];
    }
}
