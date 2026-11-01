<?php
namespace App\Http\Controllers;
use App\Models\Founder; use App\Models\User; use Illuminate\Http\Request; use Illuminate\Support\Facades\Hash;
class AuthController extends Controller {
 public function register(Request $r){$data=$r->validate(['name'=>'required|string|max:255','email'=>'required|email|unique:users','password'=>'required|string|min:8|confirmed']); $u=User::create($data); Founder::create(['user_id'=>$u->id]); return response()->json(['user'=>$u,'token'=>$u->createToken('founder-copilot')->plainTextToken],201);}
 public function login(Request $r){$data=$r->validate(['email'=>'required|email','password'=>'required|string']); $u=User::where('email',$data['email'])->first(); if(!$u||!Hash::check($data['password'],$u->password)) return response()->json(['message'=>'Invalid credentials.'],422); return ['user'=>$u,'token'=>$u->createToken('founder-copilot')->plainTextToken];}
 public function logout(Request $r){$r->user()->currentAccessToken()?->delete(); return response()->noContent();}
 public function me(Request $r){return ['user'=>$r->user(),'founder'=>$r->user()->founder,'startups'=>$r->user()->founder?->startups];}
 public function updateProfile(Request $r){$d=$r->validate(['name'=>'sometimes|string|max:255','background'=>'nullable|string|max:5000','skills'=>'nullable|string|max:5000','experience'=>'nullable|string|max:5000','interests'=>'nullable|string|max:5000','personal_goals'=>'nullable|string|max:5000','resources'=>'nullable|string|max:5000']); $r->user()->update(array_filter($d,fn($v,$k)=>$k==='name',ARRAY_FILTER_USE_BOTH)); $r->user()->founder()->updateOrCreate([],array_diff_key($d,['name'=>true])); return $this->me($r);}
}
