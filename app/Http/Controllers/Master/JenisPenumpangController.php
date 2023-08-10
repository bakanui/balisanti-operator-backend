<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\JenisPenumpang;

class JenisPenumpangController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index(Request $request)
    {
        $data = JenisPenumpang::whereNull('deleted_at');
        if (isset($request->nama)) {
            $data = $data->where('tipe', 'like', '%'.$request->nama.'%');
        }
        if (isset($request->status)) {
            $data = $data->where('status_jenis_penumpang', $request->status);
        }
        $cnt = $data->count();
        if (isset($request->sortBy)) {
            if (isset($request->order)) {
                $data = $data->orderBy($request->sortBy, $request->order);
            } else {
                $data = $data->orderBy($request->sortBy, 'asc');
            }
        } else {
            if (isset($request->order)) {
                $data = $data->orderBy('id', $request->order);
            } else {
                $data = $data->orderBy('id', 'asc');
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
        $data = JenisPenumpang::find($id);
        if (!is_null($data)) {
            return response()->json([
                'data' => $data,
            ], 200);
        }
        return response()->json([
            'message' => 'Jenis penumpang tidak ditemukan.',
        ], 404);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tipe' => 'required|string',
            'jenis' => 'required|string',
            'status_jenis_penumpang' => 'required|integer|min:0|max:1',
        ]);
        if($validator->fails()) {          
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        JenisPenumpang::create($validator->validated());
        Log::channel('single')->info('Sukses menambahkan jenis penumpang baru oleh '.auth()->user()->name.'.', $validator->validated());

        return response()->json($validator->validated(), 201);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'tipe' => 'required|string',
            'jenis' => 'required|string',
            'status_jenis_penumpang' => 'required|integer|min:0|max:1',
        ]);
        if($validator->fails()) {          
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        $data = $validator->validated();
        $id = $data['id'];
        unset($data['id']);

        JenisPenumpang::where('id', $id)->update($data);
        Log::channel('single')->info('Sukses mengupdate jenis penumpang id '.$request->id.' oleh '.auth()->user()->name.'.', $validator->validated());

        return response()->json(JenisPenumpang::find($id), 201);
    }

    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ]);
        if($validator->fails()) {          
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        JenisPenumpang::where('id', $request->id)->delete();
        Log::channel('single')->info('Sukses menghapus jenis penumpang id '.$request->id.' oleh '.auth()->user()->name.'.');

        return response()->json(["message" => "Jenis penumpang berhasil dihapus."], 201);

    }
}
