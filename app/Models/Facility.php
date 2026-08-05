<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Facility extends Model {
    public $timestamps = false;
    const CREATED_AT = 'created_at';
    protected $fillable = ['name','type','location','capacity','status'];
}