<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketTemplateAsset extends Model
{
    public const TYPE_BACKGROUND = 'background';

    public const TYPE_LOGO = 'logo';

    public const TYPE_SPONSOR_LOGO = 'sponsor_logo';

    public const TYPE_IMAGE = 'image';

    protected $fillable = [
        'organization_id',
        'organization_ticket_template_id',
        'event_ticket_template_id',
        'asset_type',
        'label',
        'file_path',
        'mime_type',
        'width',
        'height',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
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
