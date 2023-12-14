<?php

namespace App\Http\Controllers\Flow;

use App\Http\Controllers\Controller;
use App\Models\Agen;
use App\Models\Dermaga;
use App\Models\TiketOrdered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\JadwalTiket;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Log;

class PembayaranAgenController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }
    
    public function index(Request $request)
    {
        $subQueryPI = DB::table('pembayaran_invoice as pin')
                        ->join('tiket_ordered as tor', 'tor.no_invoice', 'pin.no_invoice')
                        ->select('pin.no_invoice', DB::raw('SUM(nominal_bayar) AS nominal_bayar'));
        if (isset($request->nama)) {
            $subQueryPI = $subQueryPI->where('tor.nama_agen', 'like', '%'.$request->nama.'%');
        }
        if (isset($request->tanggal_awal) && isset($request->tanggal_akhir)) {
            $subQueryPI = $subQueryPI->whereDate('tor.created_at', '>=', $request->tanggal_awal)
                        ->whereDate('tor.created_at', '<=', $request->tanggal_akhir);
        }
        $subQueryPI = $subQueryPI->groupBy('pin.no_invoice');
        $data = TiketOrdered::join('agen as a', 'tiket_ordered.id_agen', 'a.id')
                    ->leftJoin(DB::raw('('.$this->getQueries($subQueryPI).') as pin'), 'pin.no_invoice', 'tiket_ordered.no_invoice')
                    ->select('a.id', 'a.nama_agen', 'a.batas_limit',
                        DB::raw('SUM(tiket_ordered.total) AS tagihan'), 
                        DB::raw('IFNULL(SUM(pin.nominal_bayar), 0) AS sudahbayar'), 
                        DB::raw('a.batas_limit - SUM(tiket_ordered.total) AS sisa_limit')
                    );
        // $dataCnt = TiketOrdered::join('agen as a', 'tiket_ordered.id_agen', 'a.id')->select('a.id');
        if (isset($request->nama)) {
            $data = $data->where('nama_agen', 'like', '%'.$request->nama.'%');
            // $dataCnt = $dataCnt->where('nama_agen', 'like', '%'.$request->nama.'%');
        }
        if (isset($request->tanggal_awal) && isset($request->tanggal_akhir)) {
            $data = $data->whereDate('tiket_ordered.created_at', '>=', $request->tanggal_awal)
                        ->whereDate('tiket_ordered.created_at', '<=', $request->tanggal_akhir);
            // $dataCnt = $dataCnt->whereDate('tiket_ordered.created_at', '>=', $request->tanggal_awal)
            //             ->whereDate('tiket_ordered.created_at', '<=', $request->tanggal_akhir);
        }
        $cnt = DB::query()->fromSub(function ($query) use ($request) {
            $query = $query->from('tiket_ordered as to')
                        ->join('agen as a', 'to.id_agen', 'a.id')
                        ->select('a.id');
            if (isset($request->nama)) {
                $query = $query->where('nama_agen', 'like', '%'.$request->nama.'%');
            }
            if (isset($request->tanggal_awal) && isset($request->tanggal_akhir)) {
                $query = $query->whereDate('to.created_at', '>=', $request->tanggal_awal)
                            ->whereDate('to.created_at', '<=', $request->tanggal_akhir);
            }
            $query = $query->groupBy('a.id');
        }, 'to')->count();
        // $cnt = count($dataCnt->groupBy('id_agen')->get());
        if (isset($request->sortBy)) {
            if (isset($request->order)) {
                $data = $data->orderBy($request->sortBy, $request->order);
            } else {
                $data = $data->orderBy($request->sortBy, 'asc');
            }
        } else {
            if (isset($request->order)) {
                $data = $data->orderBy('id_agen', $request->order);
            } else {
                $data = $data->orderBy('id_agen', 'asc');
            }
        }
        if (isset($request->limit)) {
            $data = $data->limit($request->limit);
            if (isset($request->pagenumber)) {
                $data = $data->offset(($request->pagenumber - 1) * $request->limit);
            }
        }
        $data = $data->groupBy('tiket_ordered.id_agen')->get();
        $totalpage = $request->limit > 0 ? ceil($cnt/$request->limit) : 1;

        return response()->json([
            'data' => $data,
            'cnt' => $cnt,
            'totalPage' => $totalpage,
        ], 200);
    }

    public function getListInvoice(Request $request, $id_agen) {
        $dataAgen = Agen::select('id', 'nama_agen', 'no_telp', 'email')->find($id_agen);
        $data = TiketOrdered::join('agen as a', 'tiket_ordered.id_agen', 'a.id')
                    ->select('no_invoice', DB::raw('DATE(tiket_ordered.created_at) AS tanggal_invoice'), 
                        DB::raw('SUM(tiket_ordered.total) AS nominal'), 'flag_lunas'
                    )->where('id_agen', $id_agen)->where('flag_cancel', 0);
        $subquery = '(SELECT no_invoice, SUM(nominal_bayar) AS nominal_bayar FROM pembayaran_invoice GROUP BY no_invoice) AS pi';
        $dataRecap = DB::query()->fromSub(function ($query) {
                        $query->from('tiket_ordered')
                            ->select('id_agen', 'no_invoice', DB::raw('SUM(total) AS total'))
                            ->where('flag_cancel', 0)->groupBy('no_invoice');
                    }, 'to')->select(
                            DB::raw('SUM(to.total) AS total_tagihan'), 
                            DB::raw('COALESCE(SUM(pi.nominal_bayar), 0) AS sudah_dibayarkan'),
                            DB::raw('COALESCE(SUM(to.total) - SUM(pi.nominal_bayar), SUM(to.total)) AS terhutang')
                        )
                        ->join('agen as a', 'to.id_agen', 'a.id')
                        ->leftJoin(DB::raw($subquery), function ($join) {
                            $join->on('to.no_invoice', '=', 'pi.no_invoice');
                        })
                        ->where('id_agen', $id_agen);
        if (isset($request->no_invoice)) {
            $data = $data->where('no_invoice', 'like', '%'.$request->no_invoice.'%');
            $dataRecap = $dataRecap->where('to.no_invoice', 'like', '%'.$request->no_invoice.'%');
        }
        $cnt = DB::query()->fromSub(function ($query) use ($request, $id_agen) {
            $query = $query->from('tiket_ordered')
                        ->join('agen as a', 'tiket_ordered.id_agen', 'a.id')
                        ->select('no_invoice')
                        ->where('id_agen', $id_agen);
            if (isset($request->no_invoice)) {
                $query = $query->where('no_invoice', 'like', '%'.$request->no_invoice.'%');
            }
        }, 'to')->count();
        $dataRecap = $dataRecap->get();
        if (isset($request->sortBy)) {
            if (isset($request->order)) {
                $data = $data->orderBy($request->sortBy, $request->order);
            } else {
                $data = $data->orderBy($request->sortBy, 'asc');
            }
        } else {
            if (isset($request->order)) {
                $data = $data->orderBy('tiket_ordered.id', $request->order);
            } else {
                $data = $data->orderBy('tiket_ordered.id', 'asc');
            }
        }
        if (isset($request->limit)) {
            $data = $data->limit($request->limit);
            if (isset($request->pagenumber)) {
                $data = $data->offset(($request->pagenumber - 1) * $request->limit);
            }
        }
        $data = $data->groupBy('no_invoice')->get();
        $totalpage = $request->limit > 0 ? ceil($cnt/$request->limit) : 1;

        

        return response()->json([
            'data' => $data,
            'agen' => $dataAgen,
            'cnt' => $cnt,
            'recap' => $dataRecap,
            'totalPage' => $totalpage,
        ], 200);
    }

    public function getListInvoiceByDermaga(Request $request, $id_dermaga) {
        $dataDermaga = Dermaga::select('id', 'nama_dermaga', 'lokasi_dermaga', 'status_dermaga')->find($id_dermaga);
        $data = TiketOrdered::join('jadwal_jenispenumpang as jjp', 'tiket_ordered.id_jjp', 'jjp.id')
                    ->join('jadwal_tiket as jt', 'jt.id', 'jjp.id_jadwal')
                    ->join('rute as r', 'r.id', 'jt.id_rute')
                    ->join('dermaga as d', function($join) {
                        $join->on('d.id', '=', 'r.id_dermaga_awal')->orOn('d.id', '=', 'r.id_dermaga_tujuan');
                    })
                    ->select(
                        'no_invoice', 
                        DB::raw('DATE(tiket_ordered.created_at) AS tanggal_invoice'), 
                        DB::raw('SUM(tiket_ordered.total) AS nominal'), 
                        'flag_lunas'
                    )->where('d.id', $id_dermaga)->where('flag_cancel', 0);
        $subquery = '(SELECT no_invoice, SUM(nominal_bayar) AS nominal_bayar FROM pembayaran_invoice GROUP BY no_invoice) AS pi';
        $dataRecap = DB::query()->fromSub(function ($query) {
                        $query->from('tiket_ordered')->select('id_jjp', 'no_invoice', DB::raw('SUM(total) AS total'))->groupBy('no_invoice');
                    }, 'to')->select(
                            DB::raw('SUM(to.total) AS total_tagihan'), 
                            DB::raw('COALESCE(SUM(pi.nominal_bayar), 0) AS sudah_dibayarkan'),
                            DB::raw('COALESCE(SUM(to.total) - SUM(pi.nominal_bayar), SUM(to.total)) AS terhutang')
                        )
                        ->join('jadwal_jenispenumpang as jjp', 'to.id_jjp', 'jjp.id')
                        ->join('jadwal_tiket as jt', 'jt.id', 'jjp.id_jadwal')
                        ->join('rute as r', 'r.id', 'jt.id_rute')
                        ->join('dermaga as d', function($join) {
                            $join->on('d.id', '=', 'r.id_dermaga_awal')->orOn('d.id', '=', 'r.id_dermaga_tujuan');
                        })
                        ->leftJoin(DB::raw($subquery), function ($join) {
                            $join->on('to.no_invoice', '=', 'pi.no_invoice');
                        })
                        ->where('d.id', $id_dermaga);
        if (isset($request->no_invoice)) {
            $data = $data->where('no_invoice', 'like', '%'.$request->no_invoice.'%');
            $dataRecap = $dataRecap->where('to.no_invoice', 'like', '%'.$request->no_invoice.'%');
        }
        $cnt = DB::query()->fromSub(function ($query) use ($id_dermaga, $request) {
            $query = $query->from('tiket_ordered')->join('jadwal_jenispenumpang as jjp', 'tiket_ordered.id_jjp', 'jjp.id')
            ->join('jadwal_tiket as jt', 'jt.id', 'jjp.id_jadwal')
            ->join('rute as r', 'r.id', 'jt.id_rute')
            ->join('dermaga as d', function($join) {
                $join->on('d.id', '=', 'r.id_dermaga_awal')->orOn('d.id', '=', 'r.id_dermaga_tujuan');
            })
            ->select('no_invoice')->where('d.id', $id_dermaga);
            if (isset($request->no_invoice)) {
                $query = $query->where('no_invoice', 'like', '%'.$request->no_invoice.'%');
            }
        }, 'to')->count();
        $dataRecap = $dataRecap->get();
        if (isset($request->sortBy)) {
            if (isset($request->order)) {
                $data = $data->orderBy($request->sortBy, $request->order);
            } else {
                $data = $data->orderBy($request->sortBy, 'asc');
            }
        } else {
            if (isset($request->order)) {
                $data = $data->orderBy('tiket_ordered.id', $request->order);
            } else {
                $data = $data->orderBy('tiket_ordered.id', 'asc');
            }
        }
        if (isset($request->limit)) {
            $data = $data->limit($request->limit);
            if (isset($request->pagenumber)) {
                $data = $data->offset(($request->pagenumber - 1) * $request->limit);
            }
        }
        $data = $data->groupBy('no_invoice')->get();
        $totalpage = $request->limit > 0 ? ceil($cnt/$request->limit) : 1;

        return response()->json([
            'data' => $data,
            'dermaga' => $dataDermaga,
            'cnt' => $cnt,
            'recap' => $dataRecap,
            'totalPage' => $totalpage,
        ], 200);
    }

    public function getListPenumpangByDermaga(Request $request, $id_dermaga) {
        $dataDermaga = Dermaga::select('id', 'nama_dermaga', 'lokasi_dermaga', 'status_dermaga')->find($id_dermaga);
        $data = DB::table('tiket_ordered')->join('jadwal_jenispenumpang as jjp', 'tiket_ordered.id_jjp', 'jjp.id')
                    ->join('jadwal_tiket as jt', 'jt.id', 'jjp.id_jadwal')
                    ->join('rute as r', 'r.id', 'jt.id_rute')
                    ->join('dermaga as d', function($join) {
                        $join->on('d.id', '=', 'r.id_dermaga_awal')->orOn('d.id', '=', 'r.id_dermaga_tujuan');
                    })
                    ->leftJoin('manifest as m', 'm.kode_booking', 'tiket_ordered.kode_booking')
                    ->leftJoin('users as u', 'u.id', 'tiket_ordered.id_user_input')
                    ->leftJoin('roles as rl', 'u.id_role', 'rl.id')
                    ->leftJoin('collect as c', 'c.no_invoice', 'tiket_ordered.no_invoice')
                    ->select(
                        'tiket_ordered.kode_booking','tiket_ordered.no_invoice', 'nama_penumpang', 'tiket_ordered.email', 'jenis_kelamin', 'no_identitas',
                        'tanggal', 'tiket_ordered.waktu_berangkat', DB::raw('IF(m.id IS NULL, "Belum", "Sudah") AS status_manifest'),
                        'u.id as id_created_by', 'u.name as created_by',
                        DB::raw('IF(c.id IS NULL, FALSE, TRUE) AS status_collect')
                    )->where('d.id', $id_dermaga);
        $dataCnt = DB::table('tiket_ordered')->join('jadwal_jenispenumpang as jjp', 'tiket_ordered.id_jjp', 'jjp.id')
                    ->join('jadwal_tiket as jt', 'jt.id', 'jjp.id_jadwal')
                    ->join('rute as r', 'r.id', 'jt.id_rute')
                    ->join('dermaga as d', function($join) {
                        $join->on('d.id', '=', 'r.id_dermaga_awal')->orOn('d.id', '=', 'r.id_dermaga_tujuan');
                    })
                    ->leftJoin('manifest as m', 'm.kode_booking', 'tiket_ordered.kode_booking')
                    ->leftJoin('users as u', 'u.id', 'tiket_ordered.id_user_input')
                    ->leftJoin('roles as rl', 'u.id_role', 'rl.id')
                    ->select('kode_booking')->where('d.id', $id_dermaga);
        
        if (isset($request->no_invoice)) {
            $data = $data->where('no_invoice', 'like', '%'.$request->no_invoice.'%');
            $dataCnt = $dataCnt->where('no_invoice', 'like', '%'.$request->no_invoice.'%');
        }
        if (isset($request->tanggal)) {
            if (isset($request->tanggal_akhir)) {
                $data = $data->where('tanggal', '>=', $request->tanggal)->where('tanggal', '<=', $request->tanggal_akhir);
                $dataCnt = $dataCnt->where('tanggal', '>=', $request->tanggal)->where('tanggal', '<=', $request->tanggal_akhir);
            } else {
                $data = $data->where('tanggal', $request->tanggal);
                $dataCnt = $dataCnt->where('tanggal', $request->tanggal);
            }
        }
        if (isset($request->waktu_berangkat)) {
            $data = $data->where('tiket_ordered.waktu_berangkat', $request->waktu_berangkat);
            $dataCnt = $dataCnt->where('tiket_ordered.waktu_berangkat', $request->waktu_berangkat);
        }
        if (isset($request->nama_penumpang)) {
            $data = $data->where('nama_penumpang', 'like', '%'.$request->nama_penumpang.'%');
            $dataCnt = $dataCnt->where('nama_penumpang', 'like', '%'.$request->nama_penumpang.'%');
        }
        if (isset($request->status_checker)) {
            if ($request->status_checker == 1) {
                $data = $data->whereNotNull('m.id');
                $dataCnt = $dataCnt->whereNotNull('m.id');
            } else {
                $data = $data->whereNull('m.id');
                $dataCnt = $dataCnt->whereNull('m.id');
            }
        }
        if (isset($request->id_created_by)) {
            $data = $data->where('rl.id', $request->id_created_by);
            $dataCnt = $dataCnt->where('rl.id', $request->id_created_by);
        }
        $data = $data->where(function($query) {
            $query->whereNull('tiket_ordered.deleted_at')->orWhere(function($query) {
                $query->whereNotNull('tiket_ordered.deleted_at')->where('flag_cancel', 1);
            });
        });
        $dataCnt = $dataCnt->where(function($query) {
            $query->whereNull('tiket_ordered.deleted_at')->orWhere(function($query) {
                $query->whereNotNull('tiket_ordered.deleted_at')->where('flag_cancel', 1);
            });
        });
        $cnt = $dataCnt->count();
        $data = $data->orderBy('flag_cancel', 'desc');
        if (isset($request->sortBy)) {
            if (isset($request->order)) {
                $data = $data->orderBy($request->sortBy, $request->order);
            } else {
                $data = $data->orderBy($request->sortBy, 'asc');
            }
        } else {
            if (isset($request->order)) {
                $data = $data->orderBy('tiket_ordered.id', $request->order);
            } else {
                $data = $data->orderBy('tiket_ordered.id', 'asc');
            }
        }
        if (isset($request->limit)) {
            $data = $data->limit($request->limit);
            if (isset($request->pagenumber)) {
                $data = $data->offset(($request->pagenumber - 1) * $request->limit);
            }
        }

        $data = $data->get();
        $totalpage = $request->limit > 0 ? ceil($cnt/$request->limit) : 1;

        return response()->json([
            'data' => $data,
            'dermaga' => $dataDermaga,
            'cnt' => $cnt,
            'totalPage' => $totalpage,
        ], 200);
    }

    public function getListPenumpangByJadwal(Request $request, $id_jadwal) {
        $validator = Validator::make($request->all(), [
            'from_dermaga' => 'integer',
            'to_dermaga' => 'integer',
        ]);
        if($validator->fails()) {
            return response()->json(['error'=>$validator->errors()], 400);
        }
        if (isset($request['from_dermaga']) and !isset($request['to_dermaga'])) {
            return response()->json(['error'=>'to_dermaga must not null if from_dermaga is set.'], 400);
        }
        $dataJadwal = JadwalTiket::select('id', 'jenis_jadwal', 'waktu_berangkat', 'status_jadwal')->find($id_jadwal);
        $data = DB::table('tiket_ordered')->join('jadwal_jenispenumpang as jjp', 'tiket_ordered.id_jjp', 'jjp.id')
                    ->join('jadwal_tiket as jt', 'jt.id', 'jjp.id_jadwal')
                    ->join('rute as r', 'r.id', 'jt.id_rute')
                    ->join('dermaga as d1', 'd1.id', 'r.id_dermaga_awal')
                    ->join('dermaga as d2', 'd2.id', 'r.id_dermaga_tujuan')
                    ->leftJoin('manifest as m', 'm.kode_booking', 'tiket_ordered.kode_booking')
                    ->leftJoin('users as u', 'u.id', 'tiket_ordered.id_user_input')
                    ->leftJoin('roles as rl', 'u.id_role', 'rl.id')
                    ->leftJoin('agen as a', 'tiket_ordered.id_agen', 'a.id')
                    ->leftJoin('collect as c', 'c.no_invoice', 'tiket_ordered.no_invoice')
                    ->select(
                        'tiket_ordered.kode_booking','tiket_ordered.no_invoice', 'nama_penumpang', 'tiket_ordered.email', 'd2.nama_dermaga AS tujuan', 'jenis_kelamin', 'no_identitas',
                        'tanggal', 'tiket_ordered.waktu_berangkat', DB::raw('IF(m.id IS NULL, "Belum Datang", IF(m.status_checker=1, "Sudah Datang", "Sudah Masuk")) AS status_manifest'),
                        'rl.id as id_created_by', 'u.name as created_by', DB::raw('IFNULL(a.nama_agen, "-") AS nama_agen'),
                        DB::raw('IF(tiket_ordered.id_service IS NULL, 0, 1) AS service'), 'tiket_ordered.flag_cancel', 
                        DB::raw('IF(c.id IS NULL, FALSE, TRUE) AS status_collect')
                    )->where('jt.id', $id_jadwal);
        $dataCnt = DB::table('tiket_ordered')->join('jadwal_jenispenumpang as jjp', 'tiket_ordered.id_jjp', 'jjp.id')
                    ->join('jadwal_tiket as jt', 'jt.id', 'jjp.id_jadwal')
                    ->join('rute as r', 'r.id', 'jt.id_rute')
                    ->join('dermaga as d1', 'd1.id', 'r.id_dermaga_awal')
                    ->join('dermaga as d2', 'd2.id', 'r.id_dermaga_tujuan')
                    ->leftJoin('manifest as m', 'm.kode_booking', 'tiket_ordered.kode_booking')
                    ->leftJoin('users as u', 'u.id', 'tiket_ordered.id_user_input')
                    ->leftJoin('roles as rl', 'u.id_role', 'rl.id')
                    ->select('kode_booking')->where('jt.id', $id_jadwal);
        
        if (isset($request->no_invoice)) {
            $data = $data->where('no_invoice', 'like', '%'.$request->no_invoice.'%');
            $dataCnt = $dataCnt->where('no_invoice', 'like', '%'.$request->no_invoice.'%');
        }
        if (isset($request->tanggal)) {
            if (isset($request->tanggal_akhir)) {
                $data = $data->where('tanggal', '>=', $request->tanggal)->where('tanggal', '<=', $request->tanggal_akhir);
                $dataCnt = $dataCnt->where('tanggal', '>=', $request->tanggal)->where('tanggal', '<=', $request->tanggal_akhir);
            } else {
                $data = $data->where('tanggal', $request->tanggal);
                $dataCnt = $dataCnt->where('tanggal', $request->tanggal);
            }
        }
        if (isset($request->waktu_berangkat)) {
            $data = $data->where('tiket_ordered.waktu_berangkat', $request->waktu_berangkat);
            $dataCnt = $dataCnt->where('tiket_ordered.waktu_berangkat', $request->waktu_berangkat);
        }
        if (isset($request->nama_penumpang)) {
            $data = $data->where('nama_penumpang', 'like', '%'.$request->nama_penumpang.'%');
            $dataCnt = $dataCnt->where('nama_penumpang', 'like', '%'.$request->nama_penumpang.'%');
        }
        if (isset($request->status_checker)) {
            if ($request->status_checker > 0) {
                $data = $data->where('m.status_checker', $request->status_checker);
                $dataCnt = $dataCnt->where('m.status_checker', $request->status_checker);
            } else {
                $data = $data->whereNull('m.status_checker');
                $dataCnt = $dataCnt->whereNull('m.status_checker');
            }
        }
        if (isset($request->id_created_by)) {
            $data = $data->where('rl.id', $request->id_created_by);
            $dataCnt = $dataCnt->where('rl.id', $request->id_created_by);
        }
        $data = $data->where(function($query) {
            $query->whereNull('tiket_ordered.deleted_at')->orWhere(function($query) {
                $query->whereNotNull('tiket_ordered.deleted_at')->where('flag_cancel', 1);
            });
        });
        $dataCnt = $dataCnt->where(function($query) {
            $query->whereNull('tiket_ordered.deleted_at')->orWhere(function($query) {
                $query->whereNotNull('tiket_ordered.deleted_at')->where('flag_cancel', 1);
            });
        });
        $cnt = $dataCnt->count();
        $data = $data->orderBy('flag_cancel', 'desc');
        if (isset($request->sortBy)) {
            if (isset($request->order)) {
                $data = $data->orderBy($request->sortBy, $request->order);
            } else {
                $data = $data->orderBy($request->sortBy, 'asc');
            }
        } else {
            if (isset($request->order)) {
                $data = $data->orderBy('tiket_ordered.id', $request->order);
            } else {
                $data = $data->orderBy('tiket_ordered.id', 'asc');
            }
        }
        if (isset($request->limit)) {
            $data = $data->limit($request->limit);
            if (isset($request->pagenumber)) {
                $data = $data->offset(($request->pagenumber - 1) * $request->limit);
            }
        }

        $data = $data->get();
        $totalpage = $request->limit > 0 ? ceil($cnt/$request->limit) : 1;

        return response()->json([
            'data' => $data,
            'jadwal' => $dataJadwal,
            'cnt' => $cnt,
            'totalPage' => $totalpage,
        ], 200);
    }

    public function getListPenumpangByRutes(Request $request) {
        $request['rutes'] = explode(",", $request['rutes']);
        $validator = Validator::make($request->all(), [
            'rutes' => 'required|array',
        ]);
        if($validator->fails()) {
            return response()->json(['error'=>$validator->errors()], 400);
        }
        $request['rutes'] = array_map(function ($e) {
            $temp = explode("-", $e);
            return ['dermaga_awal' => $temp[0], 'dermaga_tujuan' => $temp[1]];
        }, $request['rutes']);
        // foreach ($request['rutes'] as $r) {
        //     if (!is_int($r)) {
        //         return response()->json(['error'=>'id rute must be integer'], 400);
        //     }
        // }
        $dataJadwal = JadwalTiket::select('jadwal_tiket.id', 'jadwal_tiket.jenis_jadwal', 'jadwal_tiket.waktu_berangkat', 'jadwal_tiket.status_jadwal')
                        ->join('rute as r', 'r.id', 'jadwal_tiket.id_rute');
        $data = DB::table('tiket_ordered')->join('jadwal_jenispenumpang as jjp', 'tiket_ordered.id_jjp', 'jjp.id')
                    ->join('jadwal_tiket as jt', 'jt.id', 'jjp.id_jadwal')
                    ->join('rute as r', 'r.id', 'jt.id_rute')
                    ->join('dermaga as d1', 'd1.id', 'r.id_dermaga_awal')
                    ->join('dermaga as d2', 'd2.id', 'r.id_dermaga_tujuan')
                    ->leftJoin('manifest as m', 'm.kode_booking', 'tiket_ordered.kode_booking')
                    ->leftJoin('users as u', 'u.id', 'tiket_ordered.id_user_input')
                    ->leftJoin('roles as rl', 'u.id_role', 'rl.id')
                    ->leftJoin('agen as a', 'tiket_ordered.id_agen', 'a.id')
                    ->leftJoin('collect as c', 'c.no_invoice', 'tiket_ordered.no_invoice')
                    ->select(
                        'tiket_ordered.kode_booking','tiket_ordered.no_invoice', 'nama_penumpang', 'tiket_ordered.email', 'jenis_kelamin', 'no_identitas',
                        'tanggal', 'tiket_ordered.waktu_berangkat', DB::raw('IF(m.id IS NULL, "Belum Datang", IF(m.status_checker=1, "Sudah Datang", "Sudah Masuk")) AS status_manifest'),
                        'rl.id as id_created_by', 'u.name as created_by', DB::raw('IFNULL(a.nama_agen, "-") AS nama_agen'),
                        DB::raw('IF(tiket_ordered.id_service IS NULL, 0, 1) AS service'), 'tiket_ordered.flag_cancel',
                        DB::raw('IF(c.id IS NULL, FALSE, TRUE) AS status_collect')
                    );
        $dataCnt = DB::table('tiket_ordered')->join('jadwal_jenispenumpang as jjp', 'tiket_ordered.id_jjp', 'jjp.id')
                    ->join('jadwal_tiket as jt', 'jt.id', 'jjp.id_jadwal')
                    ->join('rute as r', 'r.id', 'jt.id_rute')
                    ->join('dermaga as d1', 'd1.id', 'r.id_dermaga_awal')
                    ->join('dermaga as d2', 'd2.id', 'r.id_dermaga_tujuan')
                    ->leftJoin('manifest as m', 'm.kode_booking', 'tiket_ordered.kode_booking')
                    ->leftJoin('users as u', 'u.id', 'tiket_ordered.id_user_input')
                    ->leftJoin('roles as rl', 'u.id_role', 'rl.id')
                    ->select('kode_booking');
        $data = $data->where(function($q) use ($request) {
            foreach ($request['rutes'] as $key => $rute) {
                if ($key == 0) {
                    $q->where(function($query) use ($rute) {
                        $query->where('r.id_dermaga_awal', $rute['dermaga_awal'])->where('r.id_dermaga_tujuan', $rute['dermaga_tujuan']);
                    });
                } else {
                    $q->orWhere(function($query) use ($rute) {
                        $query->where('r.id_dermaga_awal', $rute['dermaga_awal'])->where('r.id_dermaga_tujuan', $rute['dermaga_tujuan']);
                    });
                }
            }
        });
        $dataJadwal = $dataJadwal->where(function($q) use ($request) {
            foreach ($request['rutes'] as $key => $rute) {
                if ($key == 0) {
                    $q->where(function($query) use ($rute) {
                        $query->where('r.id_dermaga_awal', $rute['dermaga_awal'])->where('r.id_dermaga_tujuan', $rute['dermaga_tujuan']);
                    });
                } else {
                    $q->orWhere(function($query) use ($rute) {
                        $query->where('r.id_dermaga_awal', $rute['dermaga_awal'])->where('r.id_dermaga_tujuan', $rute['dermaga_tujuan']);
                    });
                }
            }
        });
        $dataCnt = $dataCnt->where(function($q) use ($request) {
            foreach ($request['rutes'] as $key => $rute) {
                if ($key == 0) {
                    $q->where(function($query) use ($rute) {
                        $query->where('r.id_dermaga_awal', $rute['dermaga_awal'])->where('r.id_dermaga_tujuan', $rute['dermaga_tujuan']);
                    });
                } else {
                    $q->orWhere(function($query) use ($rute) {
                        $query->where('r.id_dermaga_awal', $rute['dermaga_awal'])->where('r.id_dermaga_tujuan', $rute['dermaga_tujuan']);
                    });
                }
            }
        });
        $dataJadwal = $dataJadwal->get();
        
        if (isset($request->no_invoice)) {
            $data = $data->where('no_invoice', 'like', '%'.$request->no_invoice.'%');
            $dataCnt = $dataCnt->where('no_invoice', 'like', '%'.$request->no_invoice.'%');
        }
        if (isset($request->tanggal)) {
            if (isset($request->tanggal_akhir)) {
                $data = $data->where('tanggal', '>=', $request->tanggal)->where('tanggal', '<=', $request->tanggal_akhir);
                $dataCnt = $dataCnt->where('tanggal', '>=', $request->tanggal)->where('tanggal', '<=', $request->tanggal_akhir);
            } else {
                $data = $data->where('tanggal', $request->tanggal);
                $dataCnt = $dataCnt->where('tanggal', $request->tanggal);
            }
        }
        if (isset($request->waktu_berangkat)) {
            $data = $data->where('tiket_ordered.waktu_berangkat', $request->waktu_berangkat);
            $dataCnt = $dataCnt->where('tiket_ordered.waktu_berangkat', $request->waktu_berangkat);
        }
        if (isset($request->nama_penumpang)) {
            $data = $data->where('nama_penumpang', 'like', '%'.$request->nama_penumpang.'%');
            $dataCnt = $dataCnt->where('nama_penumpang', 'like', '%'.$request->nama_penumpang.'%');
        }
        if (isset($request->status_checker)) {
            if ($request->status_checker > 0) {
                $data = $data->where('m.status_checker', $request->status_checker);
                $dataCnt = $dataCnt->where('m.status_checker', $request->status_checker);
            } else {
                $data = $data->whereNull('m.status_checker');
                $dataCnt = $dataCnt->whereNull('m.status_checker');
            }
        }
        if (isset($request->id_created_by)) {
            $data = $data->where('rl.id', $request->id_created_by);
            $dataCnt = $dataCnt->where('rl.id', $request->id_created_by);
        }
        $data = $data->where(function($query) {
            $query->whereNull('tiket_ordered.deleted_at')->orWhere(function($query) {
                $query->whereNotNull('tiket_ordered.deleted_at')->where('flag_cancel', 1);
            });
        });
        $dataCnt = $dataCnt->where(function($query) {
            $query->whereNull('tiket_ordered.deleted_at')->orWhere(function($query) {
                $query->whereNotNull('tiket_ordered.deleted_at')->where('flag_cancel', 1);
            });
        });
        // dd($data->toSql());
        $cnt = $dataCnt->count();
        $data = $data->orderBy('flag_cancel', 'desc');
        if (isset($request->sortBy)) {
            if (isset($request->order)) {
                $data = $data->orderBy($request->sortBy, $request->order);
            } else {
                $data = $data->orderBy($request->sortBy, 'asc');
            }
        } else {
            if (isset($request->order)) {
                $data = $data->orderBy('tiket_ordered.id', $request->order);
            } else {
                $data = $data->orderBy('tiket_ordered.id', 'asc');
            }
        }
        if (isset($request->limit)) {
            $data = $data->limit($request->limit);
            if (isset($request->pagenumber)) {
                $data = $data->offset(($request->pagenumber - 1) * $request->limit);
            }
        }

        $data = $data->get();
        $totalpage = $request->limit > 0 ? ceil($cnt/$request->limit) : 1;

        return response()->json([
            'data' => $data,
            'jadwal' => $dataJadwal,
            'cnt' => $cnt,
            'totalPage' => $totalpage,
        ], 200);
    }

    public function pelunasan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'no_invoice' => 'required|string',
        ]);
        if($validator->fails()) {
            return response()->json(['error'=>$validator->errors()], 400);
        }
        TiketOrdered::where('no_invoice', $request['no_invoice'])->where('flag_cancel', 0)->update([
            'flag_lunas' => 1,
            'user_lunas' => auth()->user()->name
        ]);
        Log::channel('single')->info('Pelunasan agen oleh user ['.auth()->user()->name.'].', $validator->validated());
        $data = TiketOrdered::where('no_invoice', $request['no_invoice'])->where('flag_cancel', 0)->get();
        
        return response()->json([
            'data' => $data,
            'message' => 'Pembayaran tiket berhasil.'
        ], 201);
    }

    private static function getQueries(Builder $builder)
    {
        $addSlashes = str_replace('?', "'?'", $builder->toSql());
        return vsprintf(str_replace('?', '%s', $addSlashes), $builder->getBindings());
    }
}

