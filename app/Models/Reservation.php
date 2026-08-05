<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Reservation extends Model {
    public $timestamps = false;
    const CREATED_AT = 'created_at';
    protected $fillable = ['facility_id','requested_by','purpose','start_datetime','end_datetime','status','approved_by','approved_at'];
    protected $casts = ['start_datetime'=>'datetime','end_datetime'=>'datetime','approved_at'=>'datetime','created_at'=>'datetime'];
    public function facility(): BelongsTo { return $this->belongsTo(Facility::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}