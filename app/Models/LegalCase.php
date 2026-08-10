<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LegalCase extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $table = 'legal_cases';

    protected $fillable = [
        'case_number',
        'title',
        'case_type',
        'status',
        'filed_date',
        'court_or_agency',
        'opposing_party',
        'assigned_counsel_id',
        'external_counsel_name',
        'risk_level',
        'description',
    ];

    protected $casts = [
        'filed_date' => 'date',
        'created_at' => 'datetime',
    ];

    public function counsel(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_counsel_id');
    }

    public function parties(): HasMany
    {
        return $this->hasMany(LegalCaseParty::class, 'case_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(LegalCaseMilestone::class, 'case_id');
    }

    public function costs(): HasMany
    {
        return $this->hasMany(LegalCaseCost::class, 'case_id');
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'legal_case_documents', 'case_id', 'document_id');
    }

    public function outcomes(): HasMany
    {
        return $this->hasMany(LegalCaseOutcome::class, 'case_id');
    }

    public function partyConflicts(): HasMany
    {
        return $this->hasMany(LegalCasePartyConflict::class, 'case_id');
    }

    public function counselConflicts(): HasMany
    {
        return $this->hasMany(LegalCaseCounselConflict::class, 'case_id');
    }

    protected static function booted()
    {
        // Counsel is an Employee with department_id directly.
        static::addGlobalScope(new \App\Models\Scopes\DepartmentScope('counsel'));
    }
}
