<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Startup extends Model
{
    use HasFactory;

    protected $fillable=['founder_id','name','description','problem','solution','target_customers','industry','stage','current_challenges'];
    public function founder(){return $this->belongsTo(Founder::class);}
    public function goals(){return $this->hasMany(Goal::class);}
    public function tasks(){return $this->hasMany(Task::class);}
    public function activities(){return $this->hasMany(Activity::class);}
    public function memories(){return $this->hasMany(Memory::class);}
    public function aiInsights(){return $this->hasMany(AiInsight::class);}
}
