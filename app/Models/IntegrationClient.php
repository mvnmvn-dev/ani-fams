<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationClient extends Model
{
    protected $table = 'integration_clients';

    protected $fillable = [
        'system_code', 'name', 'key_prefix', 'key_hash', 'signing_secret',
        'scopes', 'allowed_ips', 'is_active', 'last_used_at',
    ];

    protected $hidden = ['key_hash', 'signing_secret'];

    protected $casts = [
        'scopes' => 'array',
        'signing_secret' => 'encrypted',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public $timestamps = true;
}
