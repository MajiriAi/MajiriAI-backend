<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable=['startup_id','user_id','title'];
    public function messages(){return $this->hasMany(Message::class);}
    public function startup(){return $this->belongsTo(Startup::class);}
}
