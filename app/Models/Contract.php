<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'contract_number',
        'title',
        'contract_type_id',
        'template_id',
        'counterparty_name',
        'counterparty_contact',
        'owner_id',
        'value_amount',
        'currency',
        'start_date',
        'end_date',
        'auto_renew',
        'renewal_notice_days',
        'status',
        'related_case_id',
        'signature_token',
        'signed_at',
        'signed_by_name',
        'signed_ip',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'auto_renew' => 'boolean',
        'value_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'signed_at' => 'datetime',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(ContractType::class, 'contract_type_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function relatedCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'related_case_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ContractApproval::class);
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(ContractAmendment::class);
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'contract_documents');
    }

    protected static function booted()
    {
        static::addGlobalScope(new \App\Models\Scopes\DepartmentScope('owner'));
    }
}
