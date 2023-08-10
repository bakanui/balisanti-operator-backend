<?php

namespace App\Http\Controllers\Laporan;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LapAgenController extends Controller {
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => []]);
    }

    public function index(Request $request) {
        $validator = Validator::make($request->all(), [
            'tanggal' => 'date',
            'tanggal_akhir' => 'date',
            'nama_agen' => 'string',
        ]);
        if($validator->fails()) {
            return response()->json(['error'=>$validator->errors()], 400);
        }
        $request['nama_agen'] = str_replace("'", "", $request['nama_agen']);

        $subQueryTO = "SELECT id_agen, no_invoice, SUM(total) AS total FROM tiket_ordered t INNER JOIN agen a ON a.id=t.id_agen WHERE 1=1";
        $subQueryPI = "SELECT no_invoice, SUM(nominal_bayar) AS nominal_bayar FROM pembayaran_invoice WHERE 1=1";
        $dataCnt = DB::table('agen');
        if (isset($request['tanggal'])) {
            if (isset($request['tanggal_akhir'])) {
                $subQueryTO .= " AND DATE(t.created_at) BETWEEN '".$request['tanggal']."' AND '".$request['tanggal_akhir']."'";
                $subQueryPI .= " AND DATE(created_at) BETWEEN '".$request['tanggal']."' AND '".$request['tanggal_akhir']."'";
            } else {
                $subQueryTO .= " AND DATE(t.created_at) = '".$request['tanggal']."'";
                $subQueryPI .= " AND DATE(created_at) = '".$request['tanggal']."'";
            }
        }
        
        if (isset($request['nama_agen'])) {
            $subQueryTO .= " AND a.nama_agen LIKE '%".strip_tags(trim($request['nama_agen']))."%'";
            $dataCnt = $dataCnt->where('nama_agen', 'like', '%'.$request['nama_agen'].'%');
        }
        $subQueryTO .= " GROUP BY no_invoice";
        $subQueryPI .= " GROUP BY no_invoice";

        $data = DB::table('agen as a')
                    ->leftJoin(DB::raw("(".$subQueryTO.") AS `to`"), 'to.id_agen', 'a.id')
                    ->leftJoin(DB::raw("(".$subQueryPI.") AS pi"), 'to.no_invoice', 'pi.no_invoice')
                    ->select(
                        'a.id as id_agen', 
                        'a.nama_agen', 
                        'a.batas_limit', 
                        'a.status_agen', 
                        DB::raw('IFNULL(SUM(to.total), 0) AS total_tagihan'),
                        DB::raw('IFNULL(SUM(pi.nominal_bayar), 0) AS terbayar'),
                        DB::raw('IFNULL(SUM(to.total), 0)-IFNULL(SUM(pi.nominal_bayar), 0) AS tagihan'),
                        DB::raw('a.batas_limit-(IFNULL(SUM(to.total), 0)-IFNULL(SUM(pi.nominal_bayar), 0)) AS sisa_limit')
                    );
        if (strlen($request['nama_agen']) > 0) {
            $data = $data->where('a.nama_agen', 'like', '%'.$request['nama_agen'].'%');
        }

        $cnt = $dataCnt->count();
        if (isset($request->sortBy)) {
            if (isset($request->order)) {
                $data = $data->orderBy($request->sortBy, $request->order);
            } else {
                $data = $data->orderBy($request->sortBy, 'asc');
            }
        } else {
            if (isset($request->order)) {
                $data = $data->orderBy('a.id', $request->order);
            } else {
                $data = $data->orderBy('a.id', 'asc');
            }
        }
        if (isset($request->limit)) {
            $data = $data->limit($request->limit);
            if (isset($request->pagenumber)) {
                $data = $data->offset(($request->pagenumber - 1) * $request->limit);
            }
        }
        $data = $data->groupBy('a.id')->get();
        $totalpage = $request->limit > 0 ? ceil($cnt/$request->limit) : 1;
        
        return response()->json([
            'data' => $data,
            'cnt' => $cnt,
            'totalPage' => $totalpage,
        ], 200);
        
    }

    public function recap(Request $request) {
        $validator = Validator::make($request->all(), [
            'tanggal' => 'date',
            'tanggal_akhir' => 'date',
            'nama_agen' => 'string',
        ]);
        if($validator->fails()) {
            return response()->json(['error'=>$validator->errors()], 400);
        }
        $request['nama_agen'] = str_replace("'", "", $request['nama_agen']);
        $subQueryPI = DB::table('pembayaran_invoice')
                        ->select('no_invoice', DB::raw('SUM(nominal_bayar) AS nominal_bayar'));
        $subQueryTO = DB::table('tiket_ordered as t')
                        ->join('agen as a', 'a.id', 't.id_agen')
                        ->select('no_invoice', 'id_agen', DB::raw('SUM(total) AS total'));
        if (isset($request['tanggal'])) {
            if (isset($request['tanggal_akhir'])) {
                $subQueryPI = $subQueryPI->whereDate('created_at', '>=', $request['tanggal'])->whereDate('created_at', '<=', $request['tanggal_akhir']);
                $subQueryTO = $subQueryTO->whereDate('t.created_at', '>=', $request['tanggal'])->whereDate('t.created_at', '<=', $request['tanggal_akhir']);
            } else {
                $subQueryPI = $subQueryPI->whereDate('created_at', $request['tanggal']);
                $subQueryTO = $subQueryTO->whereDate('t.created_at', $request['tanggal']);
            }
        }
        if (strlen($request['nama_agen']) > 0) {
            $subQueryTO = $subQueryTO->where('a.nama_agen', 'like', '%'.$request['nama_agen'].'%');
        }
        $data = DB::table(DB::raw("(".
                        $this->getQueries($subQueryTO->groupBy('no_invoice'))
                    .") as t"))
                    ->join('agen as a', 'a.id', 't.id_agen')
                    ->leftJoin(DB::raw("(".
                        $this->getQueries($subQueryPI->groupBy('no_invoice'))
                    .") as pi"), 'pi.no_invoice', 't.no_invoice')
                    ->select(
                        DB::raw('IFNULL(SUM(t.total), 0) AS total_tagihan'),
                        DB::raw('IFNULL(SUM(pi.nominal_bayar), 0) AS sudah_bayar'),
                        DB::raw('IFNULL(SUM(t.total), 0)-IFNULL(SUM(pi.nominal_bayar), 0) AS terhutang'),
                    );
        
        // dd($this->getQueries($data));
        $data = $data->first();

        return response()->json([
            'data' => $data,
        ], 200);
    }

    private static function getQueries(Builder $builder)
    {
        $addSlashes = str_replace('?', "'?'", $builder->toSql());
        return vsprintf(str_replace('?', '%s', $addSlashes), $builder->getBindings());
    }

}