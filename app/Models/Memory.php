<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Memory extends Model
{
    use HasFactory;

    public const CATEGORIES = ['founder','startup','customer','market','product','strategy','decision','constraint','insight'];
    public const CONFIDENCES = ['explicit','inferred'];

    protected $fillable=['content','category','importance','source','confidence','key','superseded'];
    public function startup(){return $this->belongsTo(Startup::class);}
}
