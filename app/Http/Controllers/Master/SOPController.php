<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\SOP;

class SOPController extends Controller
{
    public function index(Request $request)
    {
        $data = SOP::whereNull('deleted_at');
        if (isset($request->nama)) {
            $data = $data->where('nama_sop', 'like', '%'.$request->nama.'%');
        }
        if (isset($request->status)) {
            $data = $data->where('status_sop', $request->status);
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
        $data = SOP::find($id);
        if (!is_null($data)) {
            return response()->json([
                'data' => $data,
            ], 200);
        }
        return response()->json([
            'message' => 'SOP tidak ditemukan.',
        ], 404);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_sop' => 'required|string',
            'deskripsi_sop' => 'required',
            'status_sop' => 'required|integer|min:0|max:1',
        ]);
        if($validator->fails()) {          
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        SOP::create($validator->validated());
        Log::channel('single')->info('Sukses menambahkan SOP baru oleh '.auth()->user()->name.'.', $validator->validated());

        return response()->json($validator->validated(), 201);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'nama_sop' => 'required|string',
            'deskripsi_sop' => 'required',
            'status_sop' => 'required|integer|min:0|max:1',
        ]);
        if($validator->fails()) {          
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        $data = $validator->validated();
        $id = $data['id'];
        unset($data['id']);

        SOP::where('id', $id)->update($data);
        Log::channel('single')->info('Sukses mengupdate SOP id '.$request->id.' oleh '.auth()->user()->name.'.', $validator->validated());

        return response()->json(SOP::find($id), 201);
    }

    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ]);
        if($validator->fails()) {          
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        SOP::where('id', $request->id)->delete();
        Log::channel('single')->info('Sukses menghapus SOP id '.$request->id.' oleh '.auth()->user()->name.'.');

        return response()->json(["message" => "SOP berhasil dihapus."], 201);

    }
}
