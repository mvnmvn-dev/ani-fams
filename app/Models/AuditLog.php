<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class AuditLog extends Model {
    public $timestamps = false;
    const CREATED_AT = 'created_at';
    protected $fillable = ['user_id','module','action','entity_type','entity_id','old_values','new_values','ip_address'];
    protected $casts = ['old_values'=>'array','new_values'=>'array','created_at'=>'datetime'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}