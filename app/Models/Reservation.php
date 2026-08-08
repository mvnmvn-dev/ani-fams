<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reservation extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'facility_id',
        'requested_by',
        'purpose',
        'start_datetime',
        'end_datetime',
        'status',
        'approved_by',
        'approved_at',
        'series_id',
        'recurrence_rule',
        'recurrence_until',
        'is_maintenance',
        'reminder_sent_at',
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'recurrence_until' => 'date',
        'is_maintenance' => 'boolean',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    protected static function booted()
    {
        static::addGlobalScope(new \App\Models\Scopes\DepartmentScope('requester'));
    }
}
