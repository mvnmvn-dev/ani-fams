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
        'cost_amount',
        'cost_currency',
        'chargeback_department_id',
        'cost_note',
        'no_show',
        'no_show_reported_by',
        'no_show_reported_at',
        'cancellation_reason',
        'cancelled_by',
        'cancelled_at',
        'feedback_rating',
        'feedback_comment',
        'feedback_submitted_at',
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'recurrence_until' => 'date',
        'is_maintenance' => 'boolean',
        'cost_amount' => 'decimal:2',
        'no_show' => 'boolean',
        'no_show_reported_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'feedback_submitted_at' => 'datetime',
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

    public function chargebackDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'chargeback_department_id');
    }

    public function noShowReporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'no_show_reported_by');
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    protected static function booted()
    {
        static::addGlobalScope(new \App\Models\Scopes\DepartmentScope('requester'));
    }
}
