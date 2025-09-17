<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
     public function login(){
        $user=User::where('email',request('email'))->first();

        if($user && Hash::check(request('password'),$user->password)){
            $token=$user->createToken('login',['consulta']);

            return response()->json([
                'token'=>$token,
                'user'=>$user
            ],200);
        }

        return response()->json([
            'message'=>'No autorizado'
        ],401);
    }
}
