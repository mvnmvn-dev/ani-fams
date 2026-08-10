<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class DisposalLog extends Model {
    public $timestamps = false;
    protected $fillable = ['retention_assignment_id','action_taken','performed_by','performed_at','certificate_path','certificate_generated','certificate_generated_at','certificate_generated_by'];
    protected $casts = ['performed_at'=>'datetime','certificate_generated'=>'boolean','certificate_generated_at'=>'datetime'];
    
    public function retentionAssignment(): BelongsTo
    {
        return $this->belongsTo(RetentionAssignment::class);
    }
    
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
    
    public function certificateGenerator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'certificate_generated_by');
    }
    
    public function scopeWithCertificate($query)
    {
        return $query->where('certificate_generated', true);
    }
    
    public function scopeWithoutCertificate($query)
    {
        return $query->where('certificate_generated', false);
    }
}