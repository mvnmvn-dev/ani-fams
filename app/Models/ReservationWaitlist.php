<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationWaitlist extends Model
{
    protected $table = 'reservation_waitlist';
    
    protected $fillable = [
        'facility_id',
        'requested_by',
        'purpose',
        'preferred_start_datetime',
        'preferred_end_datetime',
        'status',
        'offered_reservation_id',
        'notes',
    ];

    protected $casts = [
        'preferred_start_datetime' => 'datetime',
        'preferred_end_datetime' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function offeredReservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'offered_reservation_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForFacility($query, $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('requested_by', $userId);
    }
}
