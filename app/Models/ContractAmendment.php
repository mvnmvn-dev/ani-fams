<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ContractAmendment extends Model {
    public $timestamps = false;
    const CREATED_AT = 'created_at';
    protected $fillable = ['contract_id','amendment_number','description','effective_date','document_id','created_by'];
    protected $casts = ['effective_date'=>'date','created_at'=>'datetime'];
    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
    public function document(): BelongsTo { return $this->belongsTo(Document::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}