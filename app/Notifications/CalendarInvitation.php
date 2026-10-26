<?php

namespace App\Notifications;

use App\Models\Appointment;

final class CalendarInvitation
{
    public function render(Appointment $appointment): string
    {
        $cancelled = $appointment->status === 'cancelled';
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//SmileSuite//Appointments//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:'.($cancelled ? 'CANCEL' : 'REQUEST'),
            'BEGIN:VEVENT',
            'UID:appointment-'.$appointment->tenant_id.'-'.$appointment->id.'@smilesuite.app',
            'SEQUENCE:'.$appointment->version,
            'DTSTAMP:'.$appointment->updated_at->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$appointment->start_at->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$appointment->end_at->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->escape('Clinic appointment'),
            'DESCRIPTION:'.$this->escape($cancelled ? 'Your appointment has been cancelled.' : 'Sign in to your clinic portal to view your appointment.'),
            'STATUS:'.($cancelled ? 'CANCELLED' : 'CONFIRMED'),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', "\r\n", "\r", "\n", ';', ','], ['\\\\', '\\n', '\\n', '\\n', '\\;', '\\,'], $value);
    }

    private function fold(string $line): string
    {
        $parts = [];
        $limit = 75;
        while (strlen($line) > $limit) {
            $part = mb_strcut($line, 0, $limit, 'UTF-8');
            $parts[] = $part;
            $line = substr($line, strlen($part));
            $limit = 74;
        }
        $parts[] = $line;

        return implode("\r\n ", $parts);
    }
}
