<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetentionPolicyTemplate extends Model
{
    protected $table = 'retention_policy_templates';
    
    protected $fillable = [
        'name',
        'regulation_code',
        'description',
        'retention_period_months',
        'trigger_event',
        'disposal_action',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByRegulation($query, string $regulationCode)
    {
        return $query->where('regulation_code', $regulationCode);
    }
}
