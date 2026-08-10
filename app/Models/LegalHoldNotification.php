<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalHoldNotification extends Model
{
    protected $table = 'legal_hold_notifications';
    
    protected $fillable = [
        'legal_hold_id',
        'user_id',
        'notification_type',
        'read_at',
    ];

    protected $casts = [
        'notified_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function legalHold(): BelongsTo
    {
        return $this->belongsTo(LegalHold::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeForHold($query, int $holdId)
    {
        return $query->where('legal_hold_id', $holdId);
    }

    public function markAsRead(): void
    {
        $this->update(['read_at' => now()]);
    }
}
