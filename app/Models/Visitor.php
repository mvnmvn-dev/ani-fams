<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Visitor extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'full_name',
        'id_number',
        'company',
        'email',
        'phone',
        'photo_path',
        'is_watchlisted',
    ];

    protected $casts = [
        'is_watchlisted' => 'boolean',
    ];
}
