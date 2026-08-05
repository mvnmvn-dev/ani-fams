<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class LegalHold extends Model {
    public $timestamps = false;
    protected $fillable = ['reason','related_case_id','placed_by','placed_at','released_at'];
    protected $casts = ['placed_at'=>'datetime','released_at'=>'datetime'];
    public function placer(): BelongsTo { return $this->belongsTo(User::class, 'placed_by'); }
    public function relatedCase(): BelongsTo { return $this->belongsTo(LegalCase::class, 'related_case_id'); }
    public function documents(): BelongsToMany { return $this->belongsToMany(Document::class, 'legal_hold_documents'); }
}