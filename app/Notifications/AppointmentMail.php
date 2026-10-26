<?php

namespace App\Notifications;

use Illuminate\Mail\Mailable;

final class AppointmentMail extends Mailable
{
    public function __construct(public readonly string $calendar, public readonly bool $cancelled) {}

    public function build(): self
    {
        $body = $this->cancelled
            ? 'Your clinic appointment has been cancelled. Sign in to your clinic portal for details.'
            : 'Your clinic appointment has been updated. Sign in to your clinic portal for details.';

        return $this->subject($this->cancelled ? 'Appointment cancelled' : 'Appointment confirmation')
            ->html('<p>'.$body.'</p>')
            ->attachData($this->calendar, 'appointment.ics', ['mime' => 'text/calendar; charset=UTF-8; method='.($this->cancelled ? 'CANCEL' : 'REQUEST')]);
    }
}
