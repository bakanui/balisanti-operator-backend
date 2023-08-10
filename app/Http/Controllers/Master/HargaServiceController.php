<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\HargaService;

class HargaServiceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index(Request $request)
    {
        $data = HargaService::leftJoin('dermaga as d', 'harga_service.id_dermaga_tujuan', 'd.id')
                    ->select('harga_service.*', 'd.nama_dermaga')
                    ->whereNull('harga_service.deleted_at');
        if (isset($request->nama)) {
            $data = $data->where('harga_service.area_jemput', 'like', '%'.$request->nama.'%');
        }
        if (isset($request->status)) {
            $data = $data->where('harga_service.status_service', $request->status);
        }
        $cnt = $data->count();
        if (isset($request->sortBy)) {
            if (isset($request->order)) {
                $data = $data->orderBy('harga_service.'.$request->sortBy, $request->order);
            } else {
                $data = $data->orderBy('harga_service.'.$request->sortBy, 'asc');
            }
        } else {
            if (isset($request->order)) {
                $data = $data->orderBy('harga_service.id', $request->order);
            } else {
                $data = $data->orderBy('harga_service.id', 'asc');
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
        $data = HargaService::leftJoin('dermaga as d', 'harga_service.id_dermaga_tujuan', 'd.id')
                    ->select('harga_service.*', 'd.nama_dermaga')
                    ->find($id);
        if (!is_null($data)) {
            return response()->json([
                'data' => $data,
            ], 200);
        }
        return response()->json([
            'message' => 'Harga service tidak ditemukan.',
        ], 404);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'area_jemput' => 'required|string',
            'id_dermaga_tujuan' => 'required|integer',
            'harga' => 'required|decimal:0,10',
            'status_service' => 'required|integer|min:0|max:1',
        ]);
        if($validator->fails()) {          
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        HargaService::create($validator->validated());
        Log::channel('single')->info('Sukses menambahkan biaya service baru oleh '.auth()->user()->name.'.', $validator->validated());

        return response()->json($validator->validated(), 201);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'area_jemput' => 'required|string',
            'id_dermaga_tujuan' => 'required|integer',
            'harga' => 'required|decimal:0,10',
            'status_service' => 'required|integer|min:0|max:1',
        ]);
        if($validator->fails()) {          
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        $data = $validator->validated();
        $id = $data['id'];
        unset($data['id']);
        HargaService::where('id', $id)->update($data);
        Log::channel('single')->info('Sukses mengupdate biaya service id '.$request['id'].' oleh '.auth()->user()->name.'.', $validator->validated());

        return response()->json(HargaService::find($id), 201);
    }

    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ]);
        if($validator->fails()) {          
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        HargaService::where('id', $request->id)->delete();
        Log::channel('single')->info('Sukses menghapus biaya service id '.$request['id'].' oleh '.auth()->user()->name.'.');

        return response()->json(["message" => "Harga service berhasil dihapus."], 201);

    }

    public function massStore(Request $request)
    {
        if(!isset($request['data'])) {
            return response()->json(['error'=> "Request failed"], 400);
        }
        if(count($request['data']) == 0) {
            return response()->json(['error'=> "Request should not be empty"], 400);
        }
        $data = array();
        foreach ($request['data'] as $r) {
            $validator = Validator::make($r, [
                'id_jenis_penumpang' => 'required|integer',
                'area_jemput' => 'required|string',
                'id_dermaga_tujuan' => 'required|integer',
                'harga' => 'required|decimal:0,10',
                'status_service' => 'required|integer|min:0|max:1',
            ]);
            if($validator->fails()) {          
                return response()->json(['error'=>$validator->errors()], 400);                        
            }
            $temp = $validator->validated();
            $temp["created_at"] = date('Y-m-d H:i:s');
            $temp["updated_at"] = date('Y-m-d H:i:s');
            array_push($data, $temp);
        }
        HargaService::insert($data);

        return response()->json($data, 201);
    }

    public function massUpdate(Request $request)
    {
        if(!isset($request['data'])) {
            return response()->json(['error'=> "Request failed"], 400);
        }
        if(count($request['data']) == 0) {
            return response()->json(['error'=> "Request should not be empty"], 400);
        }
        $arrId = array();
        foreach ($request['data'] as $r) {
            $validator = Validator::make($r, [
                'id' => 'required',
                'id_jenis_penumpang' => 'required|integer',
                'area_jemput' => 'required|string',
                'id_dermaga_tujuan' => 'required|integer',
                'harga' => 'required|decimal:0,10',
                'status_service' => 'required|integer|min:0|max:1',
            ]);
            if($validator->fails()) {          
                return response()->json(['error'=>$validator->errors()], 400);                        
            }
            $data = $validator->validated();
            $id = $data['id'];
            array_push($arrId, $id);
        }
        DB::transaction(function () use ($request) {
            foreach ($request['data'] as $r) {
                $data = $r;
                $id = $data['id'];
                unset($data['id']);
                // DB::update(
                //     'UPDATE harga_service SET id_jenis_penumpang=?, area_jemput=?, id_dermaga_tujuan=?, harga=?, status_service=? WHERE id=?',
                //     array($data['id_jenis_penumpang'], $data['area_jemput'], $data['id_dermaga_tujuan'], $data['harga'], $data['status_service'], $id)
                // );
                HargaService::where('id', $id)->update($data);
            }
        });
        
        return response()->json(HargaService::whereIn('id', $arrId)->get(), 201);
    }

    public function massDelete(Request $request)
    {
        if(!isset($request['data'])) {
            return response()->json(['error'=> "Request failed"], 400);
        }
        if(count($request['data']) == 0) {
            return response()->json(['error'=> "Request should not be empty"], 400);
        }
        $arrId = array();
        foreach ($request['data'] as $r) {
            $validator = Validator::make($r, [
                'id' => 'required|integer',
            ]);
            if($validator->fails()) {          
                return response()->json(['error'=>$validator->errors()], 400);                        
            }
            array_push($arrId, $r['id']);
        }
        HargaService::whereIn('id', $arrId)->delete();

        return response()->json(["message" => "Harga service berhasil dihapus."], 201);

    }
}
