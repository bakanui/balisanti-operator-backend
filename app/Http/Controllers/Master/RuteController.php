<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\Rute;

class RuteController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index(Request $request)
    {
        $data = Rute::join('dermaga as d1', 'rute.id_dermaga_awal', 'd1.id')
            ->join('dermaga as d2', 'rute.id_dermaga_tujuan', 'd2.id')
            ->select('rute.*', 'd1.nama_dermaga as dermaga_awal', 'd2.nama_dermaga as dermaga_tujuan')
            ->whereNull('rute.deleted_at');
        if (isset($request->nama)) {
            $data = $data->where('nama_rute', 'like', '%'.$request->nama.'%');
        }
        if (isset($request->status)) {
            $data = $data->where('status_rute', $request->status);
        }
        $cnt = $data->count();
        if (isset($request->sortBy)) {
            if (isset($request->order)) {
                $data = $data->orderBy('rute.'.$request->sortBy, $request->order);
            } else {
                $data = $data->orderBy('rute.'.$request->sortBy, 'asc');
            }
        } else {
            if (isset($request->order)) {
                $data = $data->orderBy('rute.id', $request->order);
            } else {
                $data = $data->orderBy('rute.id', 'asc');
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
        $data = Rute::join('dermaga as d1', 'rute.id_dermaga_awal', 'd1.id')
                    ->join('dermaga as d2', 'rute.id_dermaga_tujuan', 'd2.id')
                    ->select('rute.*', 'd1.nama_dermaga as dermaga_awal', 'd2.nama_dermaga as dermaga_tujuan')->find($id);
        if (!is_null($data)) {
            return response()->json([
                'data' => $data,
            ], 200);
        }
        return response()->json([
            'message' => 'Rute tidak ditemukan.',
        ], 404);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_rute' => 'required|string',
            'id_dermaga_awal' => 'required|integer',
            'id_dermaga_tujuan' => 'required|integer',
            'status_rute' => 'required|integer|min:0|max:1',
        ]);
        if($validator->fails()) {          
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        Rute::create($validator->validated());
        Log::channel('single')->info('Sukses menambahkan rute baru oleh '.auth()->user()->name.'.', $validator->validated());

        return response()->json($validator->validated(), 201);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'nama_rute' => 'required|string',
            'id_dermaga_awal' => 'required|integer',
            'id_dermaga_tujuan' => 'required|integer',
            'status_rute' => 'required|integer|min:0|max:1',
        ]);
        if($validator->fails()) {          
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        $data = $validator->validated();
        $id = $data['id'];
        unset($data['id']);

        Rute::where('id', $id)->update($data);
        Log::channel('single')->info('Sukses mengupdate rute id '.$request->id.' oleh '.auth()->user()->name.'.', $validator->validated());

        return response()->json(Rute::find($id), 201);
    }

    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ]);
        if($validator->fails()) {          
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        Rute::where('id', $request->id)->delete();
        Log::channel('single')->info('Sukses menghapus rute id '.$request->id.' oleh '.auth()->user()->name.'.');

        return response()->json(["message" => "Rute berhasil dihapus."], 201);

    }
}
