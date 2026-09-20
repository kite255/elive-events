<?php

namespace App\Services\Tickets;

use App\Models\EventTicketTemplate;
use App\Models\OrganizationTicketTemplate;

final readonly class ResolvedTicketTemplate
{
    public function __construct(
        public string $source,
        public EventTicketTemplate|OrganizationTicketTemplate|null $template,
    ) {
    }
}
