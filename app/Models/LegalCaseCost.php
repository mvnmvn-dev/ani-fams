<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LegalCaseCost extends Model {
    public $timestamps = false;
    protected $fillable = ['case_id','description','amount','incurred_date'];
    protected $casts = ['incurred_date'=>'date','amount'=>'decimal:2'];
}