<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LegalCaseMilestone extends Model {
    public $timestamps = false;
    protected $fillable = ['case_id','milestone_type','milestone_date','description','is_completed'];
    protected $casts = ['milestone_date'=>'datetime','is_completed'=>'boolean'];
}