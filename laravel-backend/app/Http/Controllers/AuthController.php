<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\LoginRequest;
use App\Traits\HttpResponses;
use Error;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    use HttpResponses;
    
    public function login(LoginRequest $req)
    {
      $req->validated($req->all());

      if(!Auth::attempt(['email' => $req->email, 'password' => $req->password])){
        return $this->error('', 'Login failed. Invalid email or password.', 500);
      } 
      
      $user = User::where('email', $req->email)->with('roles:id,name')->first();

      Auth::login($user);
  
      $req->session()->regenerate();

      return $this->success($user, 'Login Successful');
    }
    public function logout(Request $request){
      
      Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();
      
      return $this->success('', 'Logged out successfully');
    }
}
