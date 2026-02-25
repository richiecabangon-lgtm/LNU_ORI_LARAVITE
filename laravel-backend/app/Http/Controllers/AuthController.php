<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Enums\RoleEnum;
use Illuminate\Support\Facades\Hash;
use App\Traits\useFileHandler;
use App\Http\Requests\LoginRequest;
use App\Traits\HttpResponses;
use Error;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    use HttpResponses;
    use useFileHandler;

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

    public function register(Request $request)
    {
        $request->validate([
            'fname' => 'required|string|max:255',
            'lname' => 'required|string|max:255',
            'mi' => 'nullable|string|max:1',
            'suffix' => 'nullable|string|max:30',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'college' => 'required|string',
            'unit' => 'required|string',
            'academic_rank' => 'required|string',
            'image_path' => 'nullable|string' // Assuming they upload the image via your public upload endpoint first
        ]);

        $user = User::create([
            'fname' => $request->fname,
            'lname' => $request->lname,
            'mi' => $request->mi,
            'suffix' => $request->suffix,
            'college' => $request->college,
            'unit' => $request->unit,
            'academic_rank' => $request->academic_rank,
            'email' => $request->email,
            'image_path' => $request->image_path,
            'password' => Hash::make($request->password),
        ]);
        $user->assignRole(RoleEnum::FACULTY);
        return response()->json([
            'status' => 'Request was successfull',
            'message' => 'Registration successful! You can now log in.',
            'user' => $user
        ], 201);
    }
}
