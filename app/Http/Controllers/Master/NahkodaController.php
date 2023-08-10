<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\Nahkoda;

class NahkodaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index(Request $request)
    {
        $data = Nahkoda::leftJoin('kecakapan_nahkoda as kn', 'nahkoda.id_kecakapan', 'kn.id')
                    ->select('nahkoda.*', 'kn.nama_kecakapan')
                    ->whereNull('nahkoda.deleted_at');
        if (isset($request->nama)) {
            $data = $data->where('nahkoda.nama_nahkoda', 'like', '%'.$request->nama.'%');
        }
        if (isset($request->status)) {
            $data = $data->where('status_nahkoda', $request->status);
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
                $data = $data->orderBy('nahkoda.id', $request->order);
            } else {
                $data = $data->orderBy('nahkoda.id', 'asc');
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
        $data = Nahkoda::leftJoin('kecakapan_nahkoda as kn', 'nahkoda.id_kecakapan', 'kn.id')
                    ->select('nahkoda.*', 'kn.nama_kecakapan')->find($id);
        if (!is_null($data)) {
            return response()->json([
                'data' => $data,
            ], 200);
        }
        return response()->json([
            'message' => 'Nahkoda tidak ditemukan.',
        ], 404);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_nahkoda' => 'required|string',
            'no_telp' => 'required|numeric',
            'email' => 'required|email',
            'id_kecakapan' => 'required|integer',
            'status_nahkoda' => 'required|integer|min:0|max:1',
        ]);
        if($validator->fails()) {          
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        $data = $validator->validated();
        Nahkoda::create($data);
        Log::channel('single')->info('Sukses menambahkan Nahkoda baru oleh '.auth()->user()->name.'.', $validator->validated());

        return response()->json($validator->validated(), 201);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'nama_nahkoda' => 'required|string',
            'no_telp' => 'required|numeric',
            'email' => 'required|email',
            'id_kecakapan' => 'required|integer',
            'status_nahkoda' => 'required|integer|min:0|max:1',
        ]);
        if($validator->fails()) {          
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        $data = $validator->validated();
        $id = $data['id'];
        unset($data['id']);
        Nahkoda::where('id', $id)->update($data);
        Log::channel('single')->info('Sukses mengupdate nahkoda id '.$request->id.' oleh '.auth()->user()->name.'.', $validator->validated());

        return response()->json(Nahkoda::find($id), 201);
    }

    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ]);
        if($validator->fails()) {          
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        Nahkoda::where('id', $request->id)->delete();
        Log::channel('single')->info('Sukses menghapus nahkoda id '.$request->id.' oleh '.auth()->user()->name.'.');

        return response()->json(["message" => "Nahkoda berhasil dihapus."], 201);

    }
}
