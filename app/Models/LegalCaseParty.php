<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LegalCaseParty extends Model {
    public $timestamps = false;
    protected $fillable = ['case_id','party_name','role','contact_info'];
}