<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'category_id',
        'title',
        'description',
        'document_number',
        'current_version_id',
        'owner_id',
        'confidentiality',
        'status',
        'review_by_date',
        'review_reminder_sent_at',
        'last_reviewed_at',
        'last_reviewed_by',
        'checked_out_by',
        'checked_out_at',
        'checked_out_notes',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'category_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(DocumentTag::class, 'document_tag_map', 'document_id', 'tag_id');
    }

    public function lastReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_reviewed_by');
    }

    public function checkedOutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }

    public function signatureRequests(): HasMany
    {
        return $this->hasMany(DocumentSignatureRequest::class);
    }

    protected $casts = [
        'review_by_date' => 'date',
        'review_reminder_sent_at' => 'datetime',
        'last_reviewed_at' => 'datetime',
        'checked_out_at' => 'datetime',
    ];

    public function scopeNeedsReview($query)
    {
        return $query->where('review_by_date', '<=', now()->toDateString())
            ->where('status', 'active');
    }

    public function scopeCheckedOut($query)
    {
        return $query->whereNotNull('checked_out_by');
    }

    public function isCheckedOut(): bool
    {
        return ! is_null($this->checked_out_by);
    }

    protected static function booted()
    {
        static::addGlobalScope(new \App\Models\Scopes\DepartmentScope('owner'));
    }
}
