<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\KecakapanNahkoda;

class KecakapanNahkodaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index(Request $request)
    {
        $data = KecakapanNahkoda::whereNull('deleted_at');
        if (isset($request->nama)) {
            $data = $data->where('nama_kecakapan', 'like', '%'.$request->nama.'%');
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
        $data = KecakapanNahkoda::find($id);
        if (!is_null($data)) {
            return response()->json([
                'data' => $data,
            ], 200);
        }
        return response()->json([
            'message' => 'Kecakapan nahkoda tidak ditemukan.',
        ], 404);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_kecakapan' => 'required|string',
        ]);
        if($validator->fails()) {          
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        $data = $validator->validated();
        KecakapanNahkoda::create($data);
        Log::channel('single')->info('Sukses menambahkan kecakapan nahkoda baru oleh '.auth()->user()->name.'.', $validator->validated());

        return response()->json($validator->validated(), 201);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'nama_kecakapan' => 'required|string',
        ]);
        if($validator->fails()) {          
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        $data = $validator->validated();
        $id = $data['id'];
        unset($data['id']);
        KecakapanNahkoda::where('id', $id)->update($data);
        Log::channel('single')->info('Sukses mengupdate kecakapan nahkoda id '.$request->id.' oleh '.auth()->user()->name.'.', $validator->validated());

        return response()->json(KecakapanNahkoda::find($id), 201);
    }

    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ]);
        if($validator->fails()) {          
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        KecakapanNahkoda::where('id', $request->id)->delete();
        Log::channel('single')->info('Sukses menghapus kecakapan nahkoda id '.$request->id.' oleh '.auth()->user()->name.'.');

        return response()->json(["message" => "Kecakapan nahkoda berhasil dihapus."], 201);

    }
}
