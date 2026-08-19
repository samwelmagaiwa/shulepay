<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class PaymentRecordedSms extends Notification
{
    public function via($notifiable): array { return ['sms']; }
}
