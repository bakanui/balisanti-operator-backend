<?php

namespace App\Http\Controllers\Laporan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LapOperatorController extends Controller {
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

        $subQuery = "SELECT `pi`.`id`, `pi`.`nominal_bayar`, `pi`.`created_at`, `pi`.`id_user_bayar`, `pi`.`metode_bayar` FROM `pembayaran_invoice` AS `pi` 
                        LEFT JOIN `users` AS `u` ON `u`.`id` = `pi`.`id_user_bayar` 
                        INNER JOIN `dermaga` AS `d` ON `d`.`id` = `u`.`id_dermaga` 
                        WHERE `u`.`id_role` = 2";
        $data = DB::table('users as u')
                    ->join('dermaga as d', 'd.id', 'u.id_dermaga');
        
        if (isset($request['tanggal_akhir'])) {
            $subQuery = $subQuery." AND DATE(pi.created_at) BETWEEN '".$request['tanggal']."' AND '".$request['tanggal_akhir']."'";
        } else {
            $subQuery = $subQuery." AND DATE(pi.created_at) = '".$request['tanggal']."'";
        }
        
        if (isset($request['id_loket'])) {
            $subQuery = $subQuery." AND d.id = ".$request['id_loket'];
            $data = $data->leftJoin(DB::raw('('.$subQuery.') AS pi'), 'pi.id_user_bayar', 'u.id')->where('u.id_dermaga', $request['id_loket']);
        } else {
            $data = $data->leftJoin(DB::raw('('.$subQuery.') AS pi'), 'pi.id_user_bayar', 'u.id');
        }
        $cnt = DB::query()->fromSub(function ($query) use ($request, $subQuery) {
            $query = $query->from('users as u')
                        ->leftJoin(DB::raw('('.$subQuery.') AS pi'), 'pi.id_user_bayar', 'u.id')
                        ->join('dermaga as d', 'd.id', 'u.id_dermaga')
                        ->where('u.id_role', 2);
            if (isset($request['id_loket'])) {
                $query = $query->where('u.id_dermaga', $request['id_loket']);
            }
            $query = $query->select('u.id')->groupBy('u.id');
        }, 'to')->count();
        $data = $data->select(
            'd.nama_dermaga', 
            'u.name as nama_operator', 
            DB::raw('"Pembelian Tiket" AS jenis_transaksi'),
            DB::raw('IFNULL(pi.metode_bayar, "") AS metode_bayar'),
            DB::raw('IFNULL(SUM(pi.nominal_bayar), 0) AS nominal'),
            DB::raw('IFNULL(DATE_FORMAT(MAX(pi.created_at), "%d/%m/%Y %H.%i"), "") AS waktu_bayar')
        );
        if (isset($request->sortBy)) {
            if (isset($request->order)) {
                $data = $data->orderBy($request->sortBy, $request->order);
            } else {
                $data = $data->orderBy($request->sortBy, 'asc');
            }
        } else {
            if (isset($request->order)) {
                $data = $data->orderBy('u.id', $request->order);
            } else {
                $data = $data->orderBy('u.id', 'asc');
            }
        }
        if (isset($request->limit)) {
            $data = $data->limit($request->limit);
            if (isset($request->pagenumber)) {
                $data = $data->offset(($request->pagenumber - 1) * $request->limit);
            }
        }
        $data = $data->groupBy('u.id')->get();
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

        $data = DB::table('pembayaran_invoice as pi')->join('users as u', 'u.id', 'pi.id_user_bayar');
        if (isset($request['tanggal_akhir'])) {
            $data = $data->whereDate('pi.created_at', '>=', $request['tanggal'])->whereDate('pi.created_at', '<=', $request['tanggal_akhir']);
        } else {
            $data = $data->whereDate('pi.created_at', $request['tanggal']);
        }
        
        if (isset($request['id_loket'])) {
            $data = $data->where('u.id_dermaga', $request['id_loket']);
        }
        // dd(
        //     $data = $data->select(
        //         DB::raw('SUM(IF(metode_bayar="Tunai", nominal_bayar, 0)) AS tunai'),
        //         DB::raw('SUM(IF(metode_bayar<>"Tunai", nominal_bayar, 0)) AS non_tunai')
        //     )->toSql()
        // );
        $data = $data->select(
            DB::raw('SUM(IF(metode_bayar="Tunai", nominal_bayar, 0)) AS tunai'),
            DB::raw('SUM(IF(metode_bayar<>"Tunai", nominal_bayar, 0)) AS non_tunai')
        )->first();

        return response()->json([
            'data' => $data,
        ], 200);
    }
}