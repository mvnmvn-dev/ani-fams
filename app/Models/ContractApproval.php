<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ContractApproval extends Model {
    public $timestamps = false;
    protected $fillable = ['contract_id','approver_id','sequence_order','status','decided_at','comments'];
    protected $casts = ['decided_at'=>'datetime'];
    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approver_id'); }
}