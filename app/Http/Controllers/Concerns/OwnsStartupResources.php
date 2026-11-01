<?php
namespace App\Http\Controllers\Concerns;
use App\Models\Startup; use Illuminate\Http\Request;
trait OwnsStartupResources { protected function startup(Request $r, int $id): Startup { return Startup::where('id',$id)->whereHas('founder',fn($q)=>$q->where('user_id',$r->user()->id))->firstOrFail(); } protected function owns(Request $r,$model){ $founder = $model instanceof Startup ? $model->founder : $model->startup->founder; abort_unless($founder?->user_id === $r->user()->id,404); return $model; } }
