<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DocumentTag extends Model {
    public $timestamps = false;
    protected $fillable = ['name'];
}