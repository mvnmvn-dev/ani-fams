<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LegalCaseOutcome extends Model
{
    use SoftDeletes;

    protected $table = 'legal_case_outcomes';

    protected $fillable = [
        'case_id',
        'outcome_type',
        'outcome_date',
        'description',
        'settlement_amount',
        'settlement_terms',
        'settlement_date',
        'judgment_summary',
        'awarded_amount',
        'is_appealed',
        'appeal_reason',
        'appeal_deadline',
        'recorded_by',
    ];

    protected $casts = [
        'outcome_date' => 'date',
        'settlement_date' => 'date',
        'appeal_deadline' => 'date',
        'settlement_amount' => 'decimal:2',
        'awarded_amount' => 'decimal:2',
        'is_appealed' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
