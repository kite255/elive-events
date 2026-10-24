<?php

return [
    // Deliberately off until cash reconciliation controls are independently reviewed.
    'payout_requests_enabled' => (bool) env('FINANCE_PAYOUT_REQUESTS_ENABLED', false),
];
