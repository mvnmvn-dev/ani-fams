<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class DocumentVersion extends Model {
    public $timestamps = false;
    protected $fillable = ['document_id','version_no','file_path','checksum','uploaded_by','uploaded_at','change_notes'];
    protected $casts = ['uploaded_at'=>'datetime'];
    public function document(): BelongsTo { return $this->belongsTo(Document::class); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
}