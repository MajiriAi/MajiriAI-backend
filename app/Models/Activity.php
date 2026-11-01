<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    use HasFactory;

    protected $fillable=['startup_id','title','description','type','occurred_at'];
    protected $casts=['occurred_at'=>'datetime'];
    public function startup(){return $this->belongsTo(Startup::class);}
}
