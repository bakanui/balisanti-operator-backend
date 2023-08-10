<?php

namespace App\Http\Controllers\Laporan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LapPenumpangController extends Controller {
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => []]);
    }
    public function index(Request $request) {
        $validator = Validator::make($request->all(), [
            'tanggal' => 'required|date',
            'tanggal_akhir' => 'date',
            'id_loket' => 'integer',
        ]);
        if($validator->fails()) {
            return response()->json(['error'=>$validator->errors()], 400);
        }

        $subQuery = "SELECT jt.id, `to`.tanggal, COUNT(jt.id) AS jmlpenum FROM tiket_ordered `to`
                        INNER JOIN jadwal_jenispenumpang jjp ON jjp.id = `to`.`id_jjp`
                        INNER JOIN jadwal_tiket jt ON jt.id = jjp.`id_jadwal`
                        INNER JOIN kapal kp ON kp.id = jt.`id_kapal`
                        INNER JOIN rute r ON r.id = jt.`id_rute`
                        INNER JOIN dermaga d1 ON d1.id = r.`id_dermaga_awal`
                        INNER JOIN dermaga d2 ON d2.id = r.`id_dermaga_tujuan`";

        $data = DB::table('jadwal_tiket as jt')
                    ->join('kapal as kp', 'jt.id_kapal', 'kp.id')
                    ->join('rute as r', 'r.id', 'jt.id_rute')
                    ->join('dermaga as d1', 'd1.id', 'r.id_dermaga_awal')
                    ->join('dermaga as d2', 'd2.id', 'r.id_dermaga_tujuan');
        
        if (isset($request['tanggal_akhir'])) {
            $subQuery = $subQuery." WHERE `to`.tanggal BETWEEN '".$request['tanggal']."' AND '".$request['tanggal_akhir']."'";
        } else {
            $subQuery = $subQuery." WHERE `to`.tanggal = '".$request['tanggal']."'";
        }
        
        if (isset($request['id_dermaga'])) {
            $subQuery = $subQuery." AND d1.id = ".$request['id_dermaga']." GROUP BY jt.id";
            $data = $data->crossJoin(DB::raw('('.$subQuery.') AS `to`'))->where('d1.id', $request['id_loket']);
        } else {
            $subQuery = $subQuery." GROUP BY jt.id";
            $data = $data->crossJoin(DB::raw('('.$subQuery.') AS `to`'));
        }

        $cnt = DB::query()->fromSub(function ($query) use ($request, $subQuery) {
            $query = $query->from('jadwal_tiket as jt')
                        ->join('kapal as kp', 'jt.id_kapal', 'kp.id')
                        ->join('rute as r', 'r.id', 'jt.id_rute')
                        ->join('dermaga as d1', 'd1.id', 'r.id_dermaga_awal')
                        ->join('dermaga as d2', 'd2.id', 'r.id_dermaga_tujuan')
                        ->crossJoin(DB::raw('('.$subQuery.') AS `to`'));
            if (isset($request['id_dermaga'])) {
                $query = $query->where('d1.id', $request['id_dermaga']);
            }
            $query = $query->selectRaw('CONCAT(jt.id, "|", to.tanggal)')->groupByRaw('jt.id, to.id');
            // dd($query->toSql());
        }, 'to')->count();

        $data = $data->select(
            'tanggal',
            'jt.id as id_jadwal',
            'kp.nama_kapal', 
            'd1.nama_dermaga', 
            DB::raw('CONCAT(DATE_FORMAT(to.tanggal, "%d/%m/%Y"), " ", jt.waktu_berangkat) AS keberangkatan'),
            'd1.nama_dermaga as dermaga_awal',
            'd2.nama_dermaga as dermaga_tujuan',
            DB::raw('IF(`to`.id=jt.id, `to`.jmlpenum, 0) AS penumpang')
        );
        if (isset($request->sortBy)) {
            if (isset($request->order)) {
                $data = $data->orderBy($request->sortBy, $request->order);
            } else {
                $data = $data->orderBy($request->sortBy, 'asc');
            }
        } else {
            if (isset($request->order)) {
                $data = $data->orderBy('jt.id', $request->order);
            } else {
                $data = $data->orderBy('jt.id', 'asc');
            }
        }
        if (isset($request->limit)) {
            $data = $data->limit($request->limit);
            if (isset($request->pagenumber)) {
                $data = $data->offset(($request->pagenumber - 1) * $request->limit);
            }
        }
        $data = $data->groupByRaw('jt.id, `to`.id')->get();
        $totalpage = $request->limit > 0 ? ceil($cnt/$request->limit) : 1;

        return response()->json([
            'data' => $data,
            'cnt' => $cnt,
            'totalPage' => $totalpage,
        ], 200);
    }
    
    public function recap(Request $request) {
        $validator = Validator::make($request->all(), [
            'tanggal' => 'required|date',
            'tanggal_akhir' => 'date',
            'id_loket' => 'integer',
        ]);
        if($validator->fails()) {
            return response()->json(['error'=>$validator->errors()], 400);
        }

        $subQuery = "SELECT jt.id, `to`.tanggal, COUNT(jt.id) AS jmlpenum FROM tiket_ordered `to`
                        INNER JOIN jadwal_jenispenumpang jjp ON jjp.id = `to`.`id_jjp`
                        INNER JOIN jadwal_tiket jt ON jt.id = jjp.`id_jadwal`
                        INNER JOIN kapal kp ON kp.id = jt.`id_kapal`
                        INNER JOIN rute r ON r.id = jt.`id_rute`
                        INNER JOIN dermaga d1 ON d1.id = r.`id_dermaga_awal`
                        INNER JOIN dermaga d2 ON d2.id = r.`id_dermaga_tujuan`";

        $data = DB::table('jadwal_tiket as jt')
                    ->join('kapal as kp', 'jt.id_kapal', 'kp.id')
                    ->join('rute as r', 'r.id', 'jt.id_rute')
                    ->join('dermaga as d1', 'd1.id', 'r.id_dermaga_awal')
                    ->join('dermaga as d2', 'd2.id', 'r.id_dermaga_tujuan');
        
        if (isset($request['tanggal_akhir'])) {
            $subQuery = $subQuery." WHERE `to`.tanggal BETWEEN '".$request['tanggal']."' AND '".$request['tanggal_akhir']."'";
        } else {
            $subQuery = $subQuery." WHERE `to`.tanggal = '".$request['tanggal']."'";
        }
        
        if (isset($request['id_dermaga'])) {
            $subQuery = $subQuery." AND d1.id = ".$request['id_dermaga']." GROUP BY jt.id";
            $data = $data->crossJoin(DB::raw('('.$subQuery.') AS `to`'))->where('d1.id', $request['id_loket']);
        } else {
            $subQuery = $subQuery." GROUP BY jt.id";
            $data = $data->crossJoin(DB::raw('('.$subQuery.') AS `to`'));
        }
        $data = $data->select('to.tanggal', 'jt.waktu_berangkat', DB::raw('IF(`to`.id=jt.id, `to`.jmlpenum, 0) AS penumpang'))
                    ->orderByRaw('to.tanggal, jt.waktu_berangkat')
                    ->groupByRaw('to.tanggal, jt.waktu_berangkat')->get();

        return response()->json([
            'data' => $data,
        ], 200);
    }
}