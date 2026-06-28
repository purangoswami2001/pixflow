<?php

namespace App\Support;

class ScheduleTimezones
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'UTC' => 'UTC',
            'America/New_York' => 'Eastern (US)',
            'America/Chicago' => 'Central (US)',
            'America/Denver' => 'Mountain (US)',
            'America/Los_Angeles' => 'Pacific (US)',
            'Europe/London' => 'London',
            'Europe/Paris' => 'Paris / CET',
            'Asia/Kolkata' => 'India (IST)',
            'Asia/Dubai' => 'Dubai',
            'Asia/Singapore' => 'Singapore',
            'Australia/Sydney' => 'Sydney',
        ];
    }
}
