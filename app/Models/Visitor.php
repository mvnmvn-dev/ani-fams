<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'watchlist_reason',
        'watchlist_expiry_date',
        'watchlist_added_by',
        'watchlist_added_at',
    ];

    protected $casts = [
        'is_watchlisted' => 'boolean',
        'watchlist_expiry_date' => 'date',
        'watchlist_added_at' => 'datetime',
    ];

    public function vehicles(): HasMany
    {
        return $this->hasMany(VisitorVehicle::class);
    }

    public function scopeWatchlisted($query)
    {
        return $query->where('is_watchlisted', true);
    }

    public function scopeExpiredWatchlist($query)
    {
        return $query->where('is_watchlisted', true)
            ->where('watchlist_expiry_date', '<', now()->toDateString());
    }

    public function isWatchlistExpired(): bool
    {
        return $this->is_watchlisted && 
               $this->watchlist_expiry_date && 
               $this->watchlist_expiry_date < now()->toDateString();
    }
}
