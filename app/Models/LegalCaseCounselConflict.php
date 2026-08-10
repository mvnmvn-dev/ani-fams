<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalCaseCounselConflict extends Model
{
    protected $table = 'legal_case_counsel_conflicts';

    protected $fillable = [
        'case_id',
        'counsel_id',
        'external_party',
        'conflict_type',
        'description',
        'severity',
        'conflict_verified',
        'verified_by',
        'verified_at',
        'mitigation_plan',
        'status',
    ];

    protected $casts = [
        'conflict_verified' => 'boolean',
        'verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function counsel(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'counsel_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
