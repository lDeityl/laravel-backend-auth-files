<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    //

    public function register(Request $request)
    {

        $validator = Validator::make($request -> all(), [
            'email' => 'required|email',
            'password' => 'required|min:3',
            'first_name' => 'required',
            'last_name' => 'required',
        ]);

        if($validator -> fails()){
            return response() -> json([
                'success' => false,
                'message' => $validator->errors()->toArray(),
            ], 422);
        }

        $user = \App\Models\User::create([
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
        ]);

        $token = $user->createToken('AuthToken')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'token' => $token
        ], 200);
    }

    public function authenticate(Request $request)
    {

        $validator = Validator::make($request -> all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if($validator -> fails()){
            return response() -> json([
                'success' => false,
                'message' => $validator->errors()->toArray(),
            ], 422);
        }

        $credentials = $validator->validated();

        if(Auth::attempt($credentials)){
            $token = auth()->user()->createToken('AuthToken')->plainTextToken;
            return response()->json([
                'success' => true,
                'message' => 'Success',
                'token' => $token
            ], 200);
        }
        else {
            return response()->json([
                'success' => false,
                'message' => 'Login failed',
            ], 401);
        }
    }

    public function logout(Request $request){
        $user= $request->user();
        if($user){
            $user->tokens()->delete();
            return response()->json([
                'success'=>true,
                'message' => 'Logout',
            ], 200);
        }
        else {
            return response()->json([
                'success'=>true,
                'message' => 'User is not logged in',
            ], 401);
        }
    }

}
