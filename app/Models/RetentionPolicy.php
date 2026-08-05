<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RetentionPolicy extends Model {
    public $timestamps = false;
    const CREATED_AT = 'created_at';
    protected $fillable = ['name','record_category','retention_period_months','trigger_event','disposal_action','legal_basis'];
}