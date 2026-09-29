<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class RetentionAssignment extends Model {
    public $timestamps = false;
    protected $fillable = [
        'document_id', 'policy_id', 'retention_start_date', 'calculated_disposal_date', 'status',
        'ai_category', 'archive_status', 'compliance_status', 'disposal_workflow_status', 'archive_date',
    ];
    protected $casts = ['retention_start_date'=>'date','calculated_disposal_date'=>'date'];
    public function document(): BelongsTo { return $this->belongsTo(Document::class); }
    public function policy(): BelongsTo { return $this->belongsTo(RetentionPolicy::class, 'policy_id'); }
}