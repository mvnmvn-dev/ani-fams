<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitorVehicle extends Model
{
    protected $table = 'visitor_vehicles';
    
    protected $fillable = [
        'visitor_id',
        'visit_id',
        'plate_number',
        'vehicle_make',
        'vehicle_model',
        'vehicle_color',
        'notes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function scopeForVisitor($query, $visitorId)
    {
        return $query->where('visitor_id', $visitorId);
    }

    public function scopeForVisit($query, $visitId)
    {
        return $query->where('visit_id', $visitId);
    }
}
