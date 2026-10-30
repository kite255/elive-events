<?php

return [
    'enabled' => (bool) env('DAILY_TICKET_SUMMARY_EMAIL_ENABLED', false),
    // Comma-separated, explicitly approved internal report recipients only.
    'recipients' => array_values(array_filter(array_map('trim', explode(',', (string) env('DAILY_TICKET_SUMMARY_RECIPIENTS', ''))))),
    'organizer_enabled' => (bool) env('DAILY_TICKET_ORGANIZER_EMAIL_ENABLED', false),
    'timezone' => 'Africa/Dar_es_Salaam',
];
