<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkUpload extends Model
{
    protected $table = 'bulk_uploads';
    
    protected $fillable = [
        'uploaded_by',
        'module',
        'file_count',
        'status',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeForModule($query, $module)
    {
        return $query->where('module', $module);
    }
}
