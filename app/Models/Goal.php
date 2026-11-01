<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Goal extends Model
{
    use HasFactory;

    protected $fillable=['startup_id','title','description','status','progress','deadline'];
    protected $casts=['deadline'=>'date'];
    public function startup(){return $this->belongsTo(Startup::class);}
    public function tasks(){return $this->hasMany(Task::class);}
}
