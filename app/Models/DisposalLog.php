<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DisposalLog extends Model {
    public $timestamps = false;
    protected $fillable = ['retention_assignment_id','action_taken','performed_by','performed_at','certificate_path'];
    protected $casts = ['performed_at'=>'datetime'];
}