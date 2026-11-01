<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable=['startup_id','goal_id','title','description','status','priority','due_date'];
    protected $casts=['due_date'=>'date'];
    public function startup(){return $this->belongsTo(Startup::class);}
    public function goal(){return $this->belongsTo(Goal::class);}
}
