<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Founder extends Model
{
    use HasFactory;

    protected $fillable=['user_id','background','skills','experience','interests','personal_goals','resources'];
    public function user(){return $this->belongsTo(User::class);}
    public function startups(){return $this->hasMany(Startup::class);}
}
