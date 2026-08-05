<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Visitor extends Model {
    public $timestamps = false;
    const CREATED_AT = 'created_at';
    protected $fillable = ['full_name','id_number','company','email','phone','photo_path','is_watchlisted'];
    protected $casts = ['is_watchlisted'=>'boolean'];
}