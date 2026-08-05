<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Visit extends Model {
    public $timestamps = false;
    protected $fillable = ['visitor_id','host_employee_id','purpose','scheduled_at','check_in_time','check_out_time','badge_code','status','notes'];
    protected $casts = ['scheduled_at'=>'datetime','check_in_time'=>'datetime','check_out_time'=>'datetime'];
    public function visitor(): BelongsTo { return $this->belongsTo(Visitor::class); }
    public function host(): BelongsTo { return $this->belongsTo(Employee::class, 'host_employee_id'); }
    public function areas(): BelongsToMany { return $this->belongsToMany(VisitArea::class, 'visit_area_access'); }
}