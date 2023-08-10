<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => []]);
    }
    
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tanggal' => 'required|date',
            'tanggal_akhir' => 'date',
        ]);
        if($validator->fails()) {
            return response()->json(['error'=>$validator->errors()], 400);
        }

        $pendapatan = DB::table('tiket_ordered');
        $pembayaran = DB::table('pembayaran_invoice')
                        ->select(
                            DB::raw('DATE(created_at) AS tgl'),
                            DB::raw('IFNULL(SUM(IF(metode_bayar != "Angsur", nominal_bayar, 0)), 0) AS tunai'), 
                            DB::raw('IFNULL(SUM(IF(metode_bayar = "Angsur", nominal_bayar, 0)), 0) AS non_tunai'), 
                        );
        if (isset($request['tanggal_akhir'])) {
            $pendapatan = $pendapatan->whereDate('created_at', '>=', $request['tanggal'])
                            ->whereDate('created_at', '<=', $request['tanggal_akhir']);
            $pembayaran = $pembayaran->whereDate('created_at', '>=', $request['tanggal'])
                            ->whereDate('created_at', '<=', $request['tanggal_akhir']);
        } else {
            $pendapatan = $pendapatan->whereDate('created_at', $request['tanggal']);
            $pembayaran = $pembayaran->whereDate('created_at', $request['tanggal']);
        }
        $pendapatan = $pendapatan->where('flag_cancel', 0)
                        ->whereNull('deleted_at')
                        ->sum('total');
        $pembayaran = $pembayaran->whereNull('deleted_at')->groupBy(DB::raw('DATE(created_at)'))->get();
        $pembayarans = [];
        $ts_date = strtotime($request['tanggal']);
        $ts_date_end = isset($request['tanggal_akhir']) ? strtotime($request['tanggal_akhir']) : strtotime($request['tanggal']);
        while ($ts_date <= $ts_date_end) {
            $date = date("Y-m-d", $ts_date);
            $tunai = $non_tunai = 0;
            foreach ($pembayaran as $pem) {
                $pem = (array) $pem;
                if ($pem['tgl'] == $date) {
                    $tunai = $pem['tunai'];
                    $non_tunai = $pem['non_tunai'];
                    break;
                }
            }
            $pembayarans[$date] = ['tunai' =>$tunai, 'non_tunai' =>$non_tunai];
            $ts_date = strtotime($date."+ 1 days");
        }

        // $pembayaran = (array) $pembayaran;
        // $pembayarans['tunai'] = number_format($pembayaran['tunai'], 0, ",", ".");
        // $pembayarans['non_tunai'] = number_format($pembayaran['non_tunai'], 0, ",", ".");
                        
        $kapals = DB::table('kapal as kp')->select(
                    'nama_kapal',
                    'mesin',
                    'jk.nama_jenis_kapal',
                    'kapasitas_penumpang',
                    'status_kapal'
                )
                ->join('jenis_kapal as jk', 'jk.id', 'kp.id_jenis_kapal')
                ->whereNull('kp.deleted_at')
                ->get();

        $jadwals = DB::table('jadwal_tiket as jk')
                    ->select(
                        'jk.waktu_berangkat', 
                        DB::raw('CONCAT(d1.nama_dermaga, "-", d2.nama_dermaga) AS rute'),
                        'n.nama_nahkoda',
                        'kp.nama_kapal'
                    )
                    ->join('rute as r', 'jk.id_rute', 'r.id')
                    ->join('dermaga as d1', 'r.id_dermaga_awal', 'd1.id')
                    ->join('dermaga as d2', 'r.id_dermaga_tujuan', 'd2.id')
                    ->join('nahkoda as n', 'n.id', 'jk.id_nahkoda')
                    ->join('kapal as kp', 'kp.id', 'jk.id_kapal')
                    ->whereNull('jk.deleted_at')
                    ->whereNull('r.deleted_at')
                    ->whereNull('d1.deleted_at')
                    ->whereNull('d2.deleted_at')
                    ->whereNull('n.deleted_at')
                    ->whereNull('kp.deleted_at')
                    ->where('jk.status_jadwal', 1)
                    ->orderBy('jk.waktu_berangkat')
                    ->get();

        return response()->json([
            'kapals' => $kapals,
            'jadwals' => $jadwals,
            'pendapatan' => number_format($pendapatan, 0, ",", "."),
            'pembayaran' => $pembayarans
        ], 200);
    }
}

