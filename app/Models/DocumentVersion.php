<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class DocumentVersion extends Model {
    public $timestamps = false;
    protected $fillable = ['document_id','version_no','file_path','checksum','uploaded_by','uploaded_at','change_notes','ocr_processed','ocr_processed_at','ocr_confidence'];
    protected $casts = ['uploaded_at'=>'datetime','ocr_processed'=>'boolean','ocr_processed_at'=>'datetime','ocr_confidence'=>'decimal:2'];
    public function document(): BelongsTo { return $this->belongsTo(Document::class); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
    
    public function scopeOcrProcessed($query)
    {
        return $query->where('ocr_processed', true);
    }
    
    public function scopeNeedsOcr($query)
    {
        return $query->where('ocr_processed', false);
    }
}