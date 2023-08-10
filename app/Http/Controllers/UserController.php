<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\ForgetPassword;
use Illuminate\Support\Str;
use Mail;
class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['resetPassword']]);
    }

    public function index(Request $request)
    {
        $data = User::join('roles', 'users.id_role', 'roles.id')
                    ->leftJoin('dermaga as d', 'd.id', 'users.id_dermaga')
                    ->select('users.id','users.email', 'users.name', 'users.id_role', 'roles.nama_role', 'users.id_dermaga', 'd.nama_dermaga');
        if (isset($request->nama)) {
            $data = $data->where('name', 'like', '%'.$request->nama.'%');
        }
        if (isset($request->loket)) {
            $data = $data->where('d.nama_dermaga', 'like', '%'.$request->loket.'%');
        }
        $cnt = $data->count();
        if (isset($request->sortBy)) {
            if (isset($request->order)) {
                $data = $data->orderBy('users.'.$request->sortBy, $request->order);
            } else {
                $data = $data->orderBy('users.'.$request->sortBy, 'asc');
            }
        } else {
            if (isset($request->order)) {
                $data = $data->orderBy('users.id', $request->order);
            } else {
                $data = $data->orderBy('users.id', 'asc');
            }
        }
        if (isset($request->limit)) {
            $data = $data->limit($request->limit);
            if (isset($request->pagenumber)) {
                $data = $data->offset(($request->pagenumber - 1) * $request->limit);
            }
        }
        $data = $data->get();
        $totalpage = $request->limit > 0 ? ceil($cnt/$request->limit) : 0;

        return response()->json([
            'data' => $data,
            'cnt' => $cnt,
            'totalPage' => $totalpage,
        ], 200);
    }

    public function view($id)
    {
        $data = User::join('roles', 'users.id_role', 'roles.id')
                    ->select('users.email', 'users.name', 'users.id_role', 'roles.nama_role')
                    ->find($id);
        if (!is_null($data)) {
            return response()->json([
                'data' => $data,
            ], 200);
        }
        return response()->json([
            'message' => 'User tidak ditemukan.',
        ], 404);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'id_role' => 'required|integer',
        ]);
        if($validator->fails()) {          
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        if (($request['id_role'] == 2 or $request['id_role'] == 3) and !isset($request['id_dermaga'])) {
            return response()->json(['error'=>'Id dermaga wajib diisi sebagai id loket user operator.'], 400);
        }
        if (isset($request['id_dermaga']) and !is_integer($request['id_dermaga'])) {
            return response()->json(['error'=>'Id dermaga harus type data integer.'], 400);
        }
        $data = $validator->validated();
        $data['password'] = Hash::make($request->password);
        if ($request['id_role'] == 2 or $request['id_role'] == 3) {
            $data['id_dermaga'] = $request->id_dermaga;
        }
        User::create($data);

        return response()->json($data, 201);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'name' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'id_role' => 'required|integer',
        ]);
        if($validator->fails()) {          
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        if (($request['id_role'] == 2 or $request['id_role'] == 3) and !isset($request['id_dermaga'])) {
            return response()->json(['error'=>'Id dermaga wajib diisi sebagai id loket user operator.'], 400);
        }
        $data = $validator->validated();
        $data['password'] = Hash::make($request->password);
        $id = $data['id'];
        unset($data['id']);

        User::where('id', $id)->update($data);

        return response()->json(User::find($id), 201);
    }

    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ]);
        if($validator->fails()) {          
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        User::where('id', $request->id)->delete();

        return response()->json(["message" => "User berhasil dihapus."], 201);

    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'keyFP' => 'required',
        ]);
        if($validator->fails()) {          
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        $fp = ForgetPassword::where('key', $request->keyFP)->whereNull('reset_to')->first();
        if (is_null($fp)) {
            return response()->json(['error'=>'Link tidak berfungsi'], 400);
        }
        $errors = array();
        try {
            $fp->reset_to = Str::random(6);
            $fp->update();
            
            User::where('email', $fp->email)->update([
                'password' => Hash::make($fp->reset_to)
            ]);
            $user = User::where('email', $fp->email)->first();
            $fp->name = $user->name;
            Mail::to($fp->email)->send(new PasswordResetMail(collect($fp)));
            return response()->json([
                'message' => 'Reset password berhasil.',
            ], 201);
        } catch (\Throwable $th) {
            array_push($errors, $th->getMessage());
        }
        return response()->json(["message" => "Password anda gagal direset.", "errors" => $errors], 501);
    }
}
