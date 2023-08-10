<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Agen;

class AgenController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index(Request $request)
    {
        $data = Agen::leftJoin(
            DB::raw('(SELECT a.id AS id_agen, a.batas_limit-sum(tor.total) AS sisa_limit FROM tiket_ordered tor
                        INNER JOIN agen a on tor.id_agen = a.id
                        WHERE tor.flag_lunas = 0
                        GROUP BY tor.id_agen) as tor'), 'tor.id_agen', 'agen.id'
                    )
                    ->select('agen.*', DB::raw('IFNULL(tor.sisa_limit, agen.batas_limit) AS sisa_limit'))
                    ->whereNull('deleted_at');
        if (isset($request->nama)) {
            $data = $data->where('nama_agen', 'like', '%'.$request->nama.'%');
        }
        if (isset($request->status)) {
            $data = $data->where('status_agen', $request->status);
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
        $data = Agen::find($id);
        if (!is_null($data)) {
            return response()->json([
                'data' => $data,
            ], 200);
        }
        return response()->json([
            'message' => 'Agen tidak ditemukan.',
        ], 404);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_agen' => 'required|string',
            'no_telp' => 'required|numeric',
            'email' => 'required|email',
            'batas_limit' => 'required|decimal:0,10',
            'jenis_diskon' => 'required|string',
            'nominal_diskon' => 'required|decimal:0,10',
            'status_agen' => 'required|integer|min:0|max:1',
        ]);
        if($validator->fails()) {          
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        Agen::create($validator->validated());
        Log::channel('single')->info('Sukses menambahkan agen baru oleh '.auth()->user()->name.'.', $validator->validated());

        return response()->json($validator->validated(), 201);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'nama_agen' => 'required|string',
            'no_telp' => 'required|numeric',
            'email' => 'required|email',
            'batas_limit' => 'required|decimal:0,10',
            'jenis_diskon' => 'required|string',
            'nominal_diskon' => 'required|decimal:0,10',
            'status_agen' => 'required|integer|min:0|max:1',
        ]);
        if($validator->fails()) {          
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        $data = $validator->validated();
        $id = $data['id'];
        unset($data['id']);

        Agen::where('id', $id)->update($data);
        Log::channel('single')->info('Sukses mengupdate agen id '.$request['id'].' oleh '.auth()->user()->name.'.', $validator->validated());

        return response()->json(Agen::find($id), 201);
    }

    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ]);
        if($validator->fails()) {          
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        Agen::where('id', $request->id)->delete();
        Log::channel('single')->info('Sukses menghapus agen id '.$request->id.' oleh '.auth()->user()->name.'.');

        return response()->json(["message" => "Agen berhasil dihapus."], 201);

    }
}
