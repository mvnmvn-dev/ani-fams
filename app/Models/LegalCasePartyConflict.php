<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalCasePartyConflict extends Model
{
    protected $table = 'legal_case_party_conflicts';

    protected $fillable = [
        'case_id',
        'party_a_id',
        'party_b_id',
        'conflict_type',
        'description',
        'severity',
        'conflict_flagged',
        'flagged_by',
        'flagged_at',
        'resolution_notes',
        'resolution_status',
    ];

    protected $casts = [
        'conflict_flagged' => 'boolean',
        'flagged_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function partyA(): BelongsTo
    {
        return $this->belongsTo(LegalCaseParty::class, 'party_a_id');
    }

    public function partyB(): BelongsTo
    {
        return $this->belongsTo(LegalCaseParty::class, 'party_b_id');
    }

    public function flaggedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'flagged_by');
    }
}
