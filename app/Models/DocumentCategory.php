<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DocumentCategory extends Model {
    public $timestamps = false;
    protected $fillable = ['name','parent_id'];
}