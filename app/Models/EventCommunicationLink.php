<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventCommunicationLink extends Model
{
    protected $fillable = [
        'event_communication_id',
        'label',
        'url',
        'open_in_new_tab',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'open_in_new_tab' => 'boolean',
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
