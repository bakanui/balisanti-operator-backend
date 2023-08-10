<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\ForgetPassword;
use Laravel\Socialite\Facades\Socialite;
use App\Mail\LupaPasswordMail;
use Mail;

class AuthController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login','register', 'lupaPassword']]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);
        $credentials = $request->only('email', 'password');

        $token = Auth::attempt($credentials);
        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        $authuser = Auth::user();
        $user = User::join('roles', 'users.id_role', 'roles.id')
                    ->leftJoin('dermaga as d', 'd.id', 'users.id_dermaga')
                    ->select('users.email', 'users.name', 'users.id_role', 'roles.nama_role', 'users.id_dermaga', 'd.nama_dermaga')
                    ->find($authuser->id);
        Log::channel('single')->info('User '.$user->email.' berhasil login.');

        return response()->json([
                'status' => 'success',
                'user' => $user,
                'authorisation' => [
                    'token' => $token,
                    'type' => 'bearer',
                ]
            ]);

    }

    protected function _registerOrLoginUser($data)
    {
        $user = User::where('email',$data->email)->first();
        if(!$user){
            // $user = new User;
            // $user->name = $data->name;
            // $user->email = $data->email;
            // $user->password = Hash::make("google123");
            $user = User::create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => Hash::make("google123"),
                'id_role' => 2
            ]);
        }
        $token = Auth::attempt($user);
        return response()->json([
            'status' => 'success',
            'user' => $user,
            'authorisation' => [
                'token' => $token,
                'type' => 'bearer',
            ]
        ]);
    }

    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }
        
    //Google callback  
    public function handleGoogleCallback()
    {
        $user = Socialite::driver('google')->stateless()->user();
        $this->_registerorLoginUser($user);
        return redirect()->route('home');
    }

    public function register(Request $request){
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = Auth::attempt($user);
        return response()->json([
            'status' => 'success',
            'message' => 'User created successfully',
            'user' => $user,
            'authorisation' => [
                'token' => $token,
                'type' => 'bearer',
            ]
        ]);
    }

    public function logout()
    {
        Auth::logout();
        return response()->json([
            'status' => 'success',
            'message' => 'Successfully logged out',
        ]);
    }

    public function refresh()
    {
        return response()->json([
            'status' => 'success',
            'user' => Auth::user(),
            'authorisation' => [
                'token' => Auth::refresh(),
                'type' => 'bearer',
            ]
        ]);
    }

    public function lupaPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
        ]);
        $user = User::where('email', $request->email)->first();
        $errors = array();
        if (!is_null($user)) {
            try {
                $user->keyFP = sha1(time());
                // kirim email ke user
                Mail::to($user->email)->send(new LupaPasswordMail(collect($user)));
                // simpan request ke DB
                ForgetPassword::create([
                    'email' => $request->email,
                    'key' => $user->keyFP,
                ]);
                return response()->json([
                    'message' => 'Pengajuan lupa password berhasil',
                ], 200);
            } catch (\Throwable $th) {
                array_push($errors, $th->getMessage());
            }
        }
        return response()->json([
            'message' => 'Kesalahan pada sistem',
            'errors' => $errors
        ], 501);
    }
}