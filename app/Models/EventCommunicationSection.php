<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventCommunicationSection extends Model
{
    protected $fillable = [
        'event_communication_id',
        'title',
        'content',
        'image_path',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'event_communication_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function communication(): BelongsTo
    {
        return $this->belongsTo(
            EventCommunication::class,
            'event_communication_id'
        );
    }
}
