<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class FeeReminderSms extends Notification
{
    public function via(\$notifiable): array { return ['sms']; }
}
