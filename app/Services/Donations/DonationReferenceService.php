<?php

namespace App\Services\Donations;

use App\Models\Donation;
use Illuminate\Support\Str;

class DonationReferenceService
{
    public function generate(): string
    {
        do {
            $reference = 'ELV-DON-' . strtoupper(Str::random(6));
        } while (
            Donation::query()
                ->where('reference', $reference)
                ->exists()
        );

        return $reference;
    }
}
