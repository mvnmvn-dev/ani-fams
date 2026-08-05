<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Document extends Model {
    public $timestamps = false;
    const CREATED_AT = 'created_at';
    protected $fillable = ['category_id','title','description','document_number','current_version_id','owner_id','confidentiality','status'];
    public function category(): BelongsTo { return $this->belongsTo(DocumentCategory::class, 'category_id'); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function currentVersion(): BelongsTo { return $this->belongsTo(DocumentVersion::class, 'current_version_id'); }
    public function versions(): HasMany { return $this->hasMany(DocumentVersion::class); }
    public function tags(): BelongsToMany { return $this->belongsToMany(DocumentTag::class, 'document_tag_map', 'document_id', 'tag_id'); }
}