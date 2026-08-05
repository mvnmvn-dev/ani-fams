<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ContractType extends Model {
    public $timestamps = false;
    protected $fillable = ['name'];
}