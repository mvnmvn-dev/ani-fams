<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSignatureRequest extends Model
{
    protected $table = 'document_signature_requests';
    
    protected $fillable = [
        'document_id',
        'requested_by',
        'signer_email',
        'signer_name',
        'message',
        'token',
        'status',
        'signed_at',
        'signature_path',
        'signature_ip',
        'expires_at',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'pending')
            ->where('expires_at', '<', now());
    }

    public function isExpired(): bool
    {
        return $this->status === 'pending' && $this->expires_at < now();
    }
}
