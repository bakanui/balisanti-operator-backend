<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\Kapal;

class KapalController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index(Request $request)
    {
        $data = Kapal::join('jenis_kapal as jk', 'jk.id', 'kapal.id_jenis_kapal')
            ->select('kapal.*', 'jk.nama_jenis_kapal')
            ->whereNull('kapal.deleted_at');
        if (isset($request->nama)) {
            $data = $data->where('nama_kapal', 'like', '%'.$request->nama.'%');
        }
        if (isset($request->jenis_kapal)) {
            $data = $data->where('id_jenis_kapal', $request->jenis_kapal);
        }
        if (isset($request->status)) {
            $data = $data->where('status_kapal', $request->status);
        }
        $cnt = $data->count();
        if (isset($request->sortBy)) {
            if (isset($request->order)) {
                $data = $data->orderBy('kapal.'.$request->sortBy, $request->order);
            } else {
                $data = $data->orderBy('kapal.'.$request->sortBy, 'asc');
            }
        } else {
            if (isset($request->order)) {
                $data = $data->orderBy('kapal.id', $request->order);
            } else {
                $data = $data->orderBy('kapal.id', 'asc');
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
        $data = Kapal::join('jenis_kapal as jk', 'jk.id', 'kapal.id_jenis_kapal')->select('kapal.*', 'jk.nama_jenis_kapal')->find($id);
        if (!is_null($data)) {
            return response()->json([
                'data' => $data,
            ], 200);
        }
        return response()->json([
            'message' => 'Kapal tidak ditemukan.',
        ], 404);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_kapal' => 'required|string',
            'mesin' => 'required|string',
            'panjang' => 'required|decimal:0,10',
            'lebar' => 'required|decimal:0,10',
            'kilometer' => 'required|decimal:0,10',
            'kedalaman' => 'required|decimal:0,10',
            'grt' => 'required|string',
            'dwt' => 'required|string',
            'status_kapal' => 'required|integer',
            'id_jenis_kapal' => 'required|integer',
            'kapasitas_awak' => 'required|integer',
            'kapasitas_penumpang' => 'required|integer',
        ]);
        if($validator->fails()) {          
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        Kapal::create($validator->validated());
        Log::channel('single')->info('Sukses menambahkan kapal baru oleh '.auth()->user()->name.'.', $validator->validated());

        return response()->json($validator->validated(), 201);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'nama_kapal' => 'required|string',
            'mesin' => 'required|string',
            'panjang' => 'required|decimal:0,10',
            'lebar' => 'required|decimal:0,10',
            'kilometer' => 'required|decimal:0,10',
            'kedalaman' => 'required|decimal:0,10',
            'grt' => 'required|string',
            'dwt' => 'required|string',
            'status_kapal' => 'required|integer',
            'id_jenis_kapal' => 'required|integer',
            'kapasitas_awak' => 'required|integer',
            'kapasitas_penumpang' => 'required|integer',
        ]);
        if($validator->fails()) {          
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        $data = $validator->validated();
        $id = $data['id'];
        unset($data['id']);

        Kapal::where('id', $id)->update($data);
        Log::channel('single')->info('Sukses mengupdate kapal id '.$request->id.' oleh '.auth()->user()->name.'.', $validator->validated());

        return response()->json(Kapal::find($id), 201);
    }

    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ]);
        if($validator->fails()) {          
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        Kapal::where('id', $request->id)->delete();
        Log::channel('single')->info('Sukses menghapus kapal id '.$request->id.' oleh '.auth()->user()->name.'.');

        return response()->json(["message" => "Kapal berhasil dihapus."], 201);

    }
}
