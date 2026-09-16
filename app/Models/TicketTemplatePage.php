<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketTemplatePage extends Model
{
    protected $fillable = [
        'organization_ticket_template_id',
        'event_ticket_template_id',
        'page_number',
        'name',
        'background_image_path',
        'definition',
    ];

    protected function casts(): array
    {
        return [
            'page_number' => 'integer',
            'definition' => 'array',
        ];
    }

    public function eventTemplate(): BelongsTo
    {
        return $this->belongsTo(
            EventTicketTemplate::class,
            'event_ticket_template_id'
        );
    }

    public function organizationTemplate(): BelongsTo
    {
        return $this->belongsTo(
            OrganizationTicketTemplate::class,
            'organization_ticket_template_id'
        );
    }
}
