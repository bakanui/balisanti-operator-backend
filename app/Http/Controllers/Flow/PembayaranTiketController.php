<?php

namespace App\Http\Controllers\Flow;

use App\Http\Controllers\Controller;
use App\Models\PembayaranInvoice;
use App\Models\TiketOrdered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

class PembayaranTiketController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['downloadInvoicePDF', 'downloadMultiInvoicePDF']]);
    }
    
    public function searchByInvoice(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'no_invoice' => 'required|string',
        ]);
        if($validator->fails()) {
            return response()->json(['error'=>$validator->errors()], 400);
        }
        $request['no_invoice'] = explode("/",$request['no_invoice'])[0];
        $data = TiketOrdered::where('tiket_ordered.no_invoice', $request['no_invoice'])
                    ->select(
                        DB::raw('COUNT(tiket_ordered.id) AS jumlah_tiket'),
                        'jenis_penumpang', 
                        'harga_tiket', DB::raw('SUM(harga_tiket) AS subtotal_tiket'),
                        'diskon_agen', DB::raw('SUM(diskon_agen) AS subtotal_diskon'),
                        'a.jenis_diskon', 'a.nominal_diskon',
                        DB::raw('SUM(IF(id_service IS NULL, 0, 1)) AS jumlah_service'), 'harga_service', DB::raw('SUM(harga_service) AS subtotal_service'),
                        DB::raw('SUM(total) AS subtotal'), DB::raw('IF(tiket_ordered.is_pp = 1, "true", "false") AS is_pp'),
                        DB::raw('IF(c.id IS NULL, FALSE, TRUE) AS status_collect'),
                        DB::raw('IF(c.id IS NULL, 0, c.jumlah) AS jumlah_collect'),
                    )
                    ->leftJoin('agen as a', 'a.id', 'tiket_ordered.id_agen')
                    ->leftJoin('collect as c', 'tiket_ordered.no_invoice', 'c.no_invoice')
                    ->where('tiket_ordered.flag_cancel', 0)
                    ->groupBy('jenis_penumpang')->get();
        $pi = PembayaranInvoice::where('no_invoice', $request['no_invoice'])
                ->select('no_invoice', 'total_tagihan', DB::raw('total_tagihan - sisa_tagihan AS sudah_bayar'), 'sisa_tagihan', DB::raw('IF(sisa_tagihan = 0, "Lunas", "Belum Lunas") AS status_lunas'))
                ->orderBy('id', 'desc')->limit(1)->first();
        $inv_data = DB::table('tiket_ordered as tor')
                        ->select(DB::raw('"Invoice Dibuat" AS ket'), 'tor.created_at')->where('tor.no_invoice', $request['no_invoice'])->limit(1);
        $hist_bayar = DB::table('pembayaran_invoice')
                        ->select(DB::raw('"Pembayaran" AS ket'), 'created_at')
                        ->where('no_invoice', $request['no_invoice'])
                        ->union($inv_data)
                        ->orderBy('created_at')
                        ->get();

        $agen = DB::table('tiket_ordered as to')
                    ->select('a.*')
                    ->join('agen as a', 'to.id_agen', 'a.id')
                    ->where('no_invoice', $request['no_invoice'])
                    ->first();
        $service = DB::table('tiket_ordered as to')
                    ->select('hs.*')
                    ->join('harga_service as hs', 'to.id_service', 'hs.id')
                    ->where('no_invoice', $request['no_invoice'])
                    ->first();
        $collect = DB::table('collect')->where('no_invoice', $request['no_invoice'])->first();
        
        $appUrl = is_null(env('APP_URL')) ? 'http://maiharta.ddns.net:3333' : env('APP_URL');
        $penumpang = DB::table('tiket_ordered as to')
                        ->select(
                            'jt.id as id_jadwal',
                            'kode_booking', 'nama_penumpang', 'no_identitas', 'jenis_kelamin', 'email',
                            'to.tanggal', 'to.waktu_berangkat as jam', 'kp.nama_kapal',
                            'd1.nama_dermaga as dermaga_awal', 'd2.nama_dermaga as dermaga_tujuan', 'jp.id as id_jenis_penumpang',
                            'to.id_jjp as id_jadwal_jenispenumpang', 'jp.tipe as tipe_penumpang',
                            'jp.jenis as jenis_penumpang',
                            DB::raw('CONCAT("'.$appUrl.'/storage/img/qrcodes/", kode_booking, ".png") AS qrcode'),
                            'to.keterangan'
                        )
                        ->leftJoin('jadwal_jenispenumpang as jjp', 'to.id_jjp', 'jjp.id')
                        ->leftJoin('jenis_penumpang as jp', 'jp.id', 'jjp.id_jenis_penumpang')
                        ->leftJoin('jadwal_tiket as jt', 'jjp.id_jadwal', 'jt.id')
                        ->leftJoin('kapal as kp', 'jt.id_kapal', 'kp.id')
                        ->leftJoin('rute as r', 'jt.id_rute', 'r.id')
                        ->leftJoin('dermaga as d1', 'r.id_dermaga_awal', 'd1.id')
                        ->leftJoin('dermaga as d2', 'r.id_dermaga_tujuan', 'd2.id')
                        ->where('to.no_invoice', $request['no_invoice'])
                        ->where('to.flag_cancel', 0)
                        ->whereNull('to.deleted_at')
                        ->get()->toArray();

        $arrHari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $arrBulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November'];
        $penumpang = array_map(function ($e) use ($arrHari, $arrBulan) {
            $temp = strtotime($e->tanggal);
            $hari = $arrHari[date('w', $temp)];
            $bulan = $arrBulan[date('n', $temp)-1];
            $e->waktu_berangkat = $hari.", ".date('d', $temp)." ".$bulan." ".date('Y', $temp)." ".$e->jam;
            return $e;
        }, $penumpang);
        
        if (is_null($pi)) {
            $total_tagihan = array_reduce($data->toArray(), function ($t, $e) {
                $t += $e['subtotal'];
                return $t;
            }, 0);
            if (!is_null($collect)) {
                $total_tagihan += (double) $collect->jumlah;
                $collect->jumlah = (double) $collect->jumlah;
            }
            $pi['no_invoice'] = $request['no_invoice'];
            $pi['total_tagihan'] = $total_tagihan;
            $pi['sudah_bayar'] = 0;
            $pi['sisa_tagihan'] = $total_tagihan;
        } else {
            $pi = $pi->toArray();
        }

        if (count($penumpang) > 0) {
            $penum = (array) $penumpang[0];
            $penum2 = (array) $penumpang[count($penumpang) - 1];
            $tanggal = $penum['tanggal'];
            $jt_id = [$penum['id_jadwal']];
            array_push($jt_id, $penum2['id_jadwal']);
            $detailJadwal = DB::table('jadwal_tiket as jt')
                                ->join('rute as r', 'r.id', 'jt.id_rute')
                                ->join('dermaga as d1', 'd1.id', 'r.id_dermaga_awal')
                                ->join('dermaga as d2', 'd2.id', 'r.id_dermaga_tujuan')
                                ->join('nahkoda as n', 'n.id', 'jt.id_nahkoda')
                                ->join('kapal as kp', 'kp.id', 'jt.id_kapal')
                                ->leftJoin(
                                    DB::raw(
                                        '(SELECT jt.id AS id_jadwal, COUNT(tor.id) AS dibooking, kp.`kapasitas_penumpang`-COUNT(tor.id) AS sisa_kursi, kp.kapasitas_penumpang FROM tiket_ordered tor
                                        INNER JOIN jadwal_jenispenumpang jjp ON jjp.id=tor.`id_jjp`
                                        INNER JOIN jadwal_tiket jt ON jt.id=jjp.`id_jadwal`
                                        INNER JOIN kapal kp ON kp.id=jt.`id_kapal`
                                        WHERE tor.tanggal = "'.$tanggal.'" AND tor.flag_cancel = 0 AND tor.deleted_at IS NULL
                                        GROUP BY jt.id) as tor'
                                    ), 'tor.id_jadwal', 'jt.id'
                                )
                                ->select(
                                    'jt.id', 'jt.waktu_berangkat', 'n.nama_nahkoda', 'r.nama_rute',
                                    'kp.nama_kapal', 'r.id_dermaga_awal', 'd1.nama_dermaga as dermaga_awal', 'r.id_dermaga_tujuan', 'd2.nama_dermaga as dermaga_tujuan',
                                    DB::raw('IFNULL(tor.sisa_kursi, kp.kapasitas_penumpang) as sisa_kursi'), 
                                    'kp.kapasitas_penumpang'
                                )
                                ->whereIn('jt.id', $jt_id)
                                ->whereNull('jt.deleted_at')->where('jt.status_jadwal', 1)
                                ->whereNull('kp.deleted_at')->where('kp.status_kapal', 1)
                                ->whereNull('n.deleted_at')->where('n.status_nahkoda', 1)
                                ->whereNull('r.deleted_at')->where('r.status_rute', 1)
                                ->whereNull('d1.deleted_at')->where('d1.status_dermaga', 1)
                                ->whereNull('d2.deleted_at')->where('d2.status_dermaga', 1)
                                ->get();
            
            if (count($detailJadwal) > 0) {
                if (count($detailJadwal) > 1) {
                    $detailJadwal[1]->tanggal_berangkat = $penum2['tanggal'];
                }
                $detailJadwal[0]->tanggal_berangkat = $penum['tanggal'];
            }
        } else {
            $detailJadwal = [[], []];
        }

        return response()->json([
            'data' => $data,
            'pembayaran' => $pi,
            'history' => $hist_bayar,
            'agen' => $agen,
            'service' => $service,
            'penumpang' => $penumpang,
            'detail_jadwal' => $detailJadwal[0],
            'detail_jadwal_pulang' => (count($data) > 0 and $data[0]->is_pp == "true") ? (isset($detailJadwal[1]) ? $detailJadwal[1] : null) : null,
            'collect' => $collect
        ], 200);
    }

    public function pelunasan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'no_invoice' => 'required|string',
            'nominal' => 'required|decimal:0,11',
            'metode_bayar' => 'required|string',
        ]);
        if($validator->fails()) {
            return response()->json(['error'=>$validator->errors()], 400);
        }
        DB::beginTransaction();
        try {
            $to = TiketOrdered::where('no_invoice', $request['no_invoice'])->where('flag_cancel', 0)->sum('total');
            $pi = PembayaranInvoice::where('no_invoice', $request['no_invoice'])->orderBy('id', 'desc')->limit(1)->sharedLock()->first();
            $sh = $to;
            $collect = DB::table('collect')->where('no_invoice', $request['no_invoice'])->first();
            if (!is_null($collect)) {
                $sh += (double) $collect->jumlah;
            }
            if ($request['nominal'] > $sh) {
                return response()->json(['message'=>'Nominal yang dibayarkan melebihi total harga tiket.'], 400);
            }
            if (!is_null($pi)) {
                if ($request['nominal'] > $pi->sisa_tagihan) {
                    return response()->json(['message'=>'Nominal yang dibayarkan melebihi sisa hutang.'], 400);
                }
                $sh = $pi->sisa_tagihan;
            }
            $data = new PembayaranInvoice;
            $data->no_invoice = $request['no_invoice'];
            $data->total_tagihan = $to;
            $data->nominal_bayar = $request['nominal'];
            $data->sisa_tagihan = $sh - $request['nominal'];
            $data->metode_bayar = $request['metode_bayar'];
            if ($file = $request->file('bukti_bayar')) {
                $validator = Validator::make($request->all(), [
                    'bukti_bayar' => 'mimes:pdf|max:2048',
                ]);
                if($validator->fails()) {
                    return response()->json(['error'=>$validator->errors()], 400);
                }
                $file->store('public/bukti_bayar');
                $data->bukti_bayar = env('APP_URL')."/storage/bukti_bayar/".$file->hashName();
            }
            $data->id_user_bayar = auth()->user()->id;
            $data->save();
            if ($data->sisa_tagihan == 0) {
                TiketOrdered::where('no_invoice', $request['no_invoice'])->where('flag_cancel', 0)->update(['flag_lunas' => 1]);
            }
            DB::commit();
            Log::channel('single')->info('Pelunasan tiket sukses oleh user ['.auth()->user()->name.'].', $validator->validated());
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::channel('single')->error('Pelunasan tiket gagal oleh user ['.auth()->user()->name.'].', $th->getTrace());
        }
        

        return response()->json([
            'data' => $data,
            'message' => 'Pembayaran invoice berhasil.'
        ], 201);
    }

    public function getRecap(Request $request) {
        $recap = DB::table(DB::raw('(SELECT no_invoice, SUM(total) AS total, created_at FROM tiket_ordered GROUP BY no_invoice) AS t'))
                    ->select(
                        DB::raw('SUM(t.total) AS total_tagihan'), 
                        DB::raw('SUM(pi.nominal_bayar) AS sudah_dibayarkan'), 
                        DB::raw('SUM(t.total) - SUM(pi.nominal_bayar) AS terhutang'), 
                    )
                    ->leftJoin('pembayaran_invoice as pi', 't.no_invoice', 'pi.no_invoice');
        if (isset($request->tanggal_awal) && isset($request->tanggal_akhir)) {
            $validator = Validator::make($request->all(), [
                'tanggal_awal' => 'date',
                'tanggal_akhir' => 'date',
            ]);
            if($validator->fails()) {
                return response()->json(['error'=>$validator->errors()], 400);
            }
            $recap = $recap->whereDate('t.created_at', '>=', $request['tanggal_awal'])
                        ->whereDate('t.created_at', '<=', $request['tanggal_akhir']);
        }
        
        return response()->json([
            'data' => $recap->get()
        ], 200);
    }

    public function downloadInvoicePDF(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'no_invoice' => 'required|string',
        ]);
        if($validator->fails()) {
            return response()->json(['error'=>$validator->errors()], 400);
        }
        $data = TiketOrdered::where('tiket_ordered.no_invoice', $request['no_invoice'])
                    ->where('flag_cancel', 0)
                    ->select(
                        DB::raw('COUNT(tiket_ordered.id) AS jumlah_tiket'),
                        'jenis_penumpang', 
                        'harga_tiket', DB::raw('SUM(harga_tiket) AS subtotal_tiket'),
                        'diskon_agen', DB::raw('SUM(diskon_agen) AS subtotal_diskon'),
                        'a.jenis_diskon', 'a.nominal_diskon',
                        DB::raw('SUM(IF(id_service IS NULL, 0, 1)) AS jumlah_service'), 'harga_service', DB::raw('SUM(harga_service) AS subtotal_service'),
                        DB::raw('SUM(total) AS subtotal'), DB::raw('IF(tiket_ordered.is_pp = 1, "true", "false") AS is_pp'),
                        DB::raw('IF(c.id IS NULL, FALSE, TRUE) AS status_collect'),
                        DB::raw('IF(c.id IS NULL, 0, c.jumlah) AS jumlah_collect'),
                    )
                    ->leftJoin('agen as a', 'a.id', 'tiket_ordered.id_agen')
                    ->leftJoin('collect as c', 'tiket_ordered.no_invoice', 'c.no_invoice')
                    ->groupBy('jenis_penumpang')->get();
        $pi = PembayaranInvoice::where('no_invoice', $request['no_invoice'])
                ->select('no_invoice', 'total_tagihan', DB::raw('total_tagihan - sisa_tagihan AS sudah_bayar'), 'sisa_tagihan', DB::raw('IF(sisa_tagihan = 0, "Lunas", "Belum Lunas") AS status_lunas'))
                ->orderBy('id', 'desc')->limit(1)->first();
        $inv_data = DB::table('tiket_ordered')->select(DB::raw('"Invoice Dibuat" AS ket'), 'created_at')
                        ->where('no_invoice', $request['no_invoice'])
                        ->where('flag_cancel', 0)
                        ->limit(1);
        $hist_bayar = DB::table('pembayaran_invoice')
                        ->select(DB::raw('"Pembayaran" AS ket'), 'created_at')
                        ->where('no_invoice', $request['no_invoice'])
                        ->union($inv_data)
                        ->orderBy('created_at')
                        ->get();

        $agen = DB::table('tiket_ordered as to')
                    ->select('a.*')
                    ->join('agen as a', 'to.id_agen', 'a.id')
                    ->where('no_invoice', $request['no_invoice'])
                    ->where('to.flag_cancel', 0)
                    ->first();
        $collect = DB::table('collect')->where('no_invoice', $request['no_invoice'])->first();
        
        $appUrl = is_null(env('APP_URL')) ? 'http://maiharta.ddns.net:3333' : env('APP_URL');
        $penumpang = DB::table('tiket_ordered as to')
                        ->select(
                            'kode_booking', 'nama_penumpang', 'no_identitas', 'jenis_kelamin', 'email',
                            'to.tanggal', 'to.waktu_berangkat as jam', 'kp.nama_kapal',
                            'd1.nama_dermaga as dermaga_awal', 'd2.nama_dermaga as dermaga_tujuan',
                            DB::raw('CONCAT("'.$appUrl.'/storage/img/qrcodes/", kode_booking, ".png") AS qrcode')
                        )
                        ->leftJoin('jadwal_jenispenumpang as jjp', 'to.id_jjp', 'jjp.id')
                        ->leftJoin('jadwal_tiket as jt', 'jjp.id_jadwal', 'jt.id')
                        ->leftJoin('kapal as kp', 'jt.id_kapal', 'kp.id')
                        ->leftJoin('rute as r', 'jt.id_rute', 'r.id')
                        ->leftJoin('dermaga as d1', 'r.id_dermaga_awal', 'd1.id')
                        ->leftJoin('dermaga as d2', 'r.id_dermaga_tujuan', 'd2.id')
                        ->where('no_invoice', $request['no_invoice'])
                        ->where('flag_cancel', 0)
                        ->get()->toArray();

        $arrHari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $arrBulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November'];
        $penumpang = array_map(function ($e) use ($arrHari, $arrBulan) {
            $temp = strtotime($e->tanggal);
            $hari = $arrHari[date('w', $temp)];
            $bulan = $arrBulan[date('n', $temp)-1];
            $e->waktu_berangkat = $hari.", ".date('d', $temp)." ".$bulan." ".date('Y', $temp)." ".$e->jam;
            return $e;
        }, $penumpang);
        
        if (is_null($pi)) {
            $total_tagihan = array_reduce($data->toArray(), function ($t, $e) {
                $t += $e['subtotal'];
                return $t;
            }, 0);
            if (!is_null($collect)) {
                $total_tagihan += (double) $collect->jumlah;
                $collect->jumlah = (double) $collect->jumlah;
            }
            $pi['no_invoice'] = $request['no_invoice'];
            $pi['total_tagihan'] = $total_tagihan;
            $pi['sudah_bayar'] = 0;
            $pi['sisa_tagihan'] = $total_tagihan;
        } else {
            $pi = $pi->toArray();
        }

        // dd(date('n', strtotime($hist_bayar[0]->created_at)));
        $tanggal = date('d', strtotime($hist_bayar[0]->created_at))." "
                    .$arrBulan[date('n', strtotime($hist_bayar[0]->created_at)) - 1]." "
                    .date('Y', strtotime($hist_bayar[0]->created_at));
        
        $data = [
            'data' => $data,
            'pembayaran' => $pi,
            'history' => $hist_bayar,
            'agen' => $agen,
            'penumpang' => $penumpang,
            'tanggal_pembuatan' => $tanggal,
            'collect' => $collect
        ];
        // return $data;
        
        $pdf = Pdf::loadView('pdf.invoice', $data);
        // return $pdf->download('Wahana Virendhra '.$request['no_invoice'].'.pdf');
        return $pdf->stream();
    }

    public function downloadMultiInvoicePDF(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'no_invoice' => 'required|string',
        ]);
        if($validator->fails()) {
            return response()->json(['error'=>$validator->errors()], 400);
        }
        $request['no_invoice'] = explode(",", $request['no_invoice']);

        $data = TiketOrdered::whereIn('tiket_ordered.no_invoice', $request['no_invoice'])
                    ->where('flag_cancel', 0)
                    ->select(
                        'tiket_ordered.no_invoice',
                        DB::raw('COUNT(tiket_ordered.id) AS jumlah_tiket'),
                        'jenis_penumpang', 
                        'harga_tiket', DB::raw('SUM(harga_tiket) AS subtotal_tiket'),
                        'diskon_agen', DB::raw('SUM(diskon_agen) AS subtotal_diskon'),
                        'a.jenis_diskon', 'a.nominal_diskon',
                        DB::raw('SUM(IF(id_service IS NULL, 0, 1)) AS jumlah_service'), 'harga_service', DB::raw('SUM(harga_service) AS subtotal_service'),
                        DB::raw('SUM(total) AS subtotal'), DB::raw('IF(tiket_ordered.is_pp = 1, "true", "false") AS is_pp'),
                        DB::raw('IF(c.id IS NULL, FALSE, TRUE) AS status_collect'),
                        DB::raw('IF(c.id IS NULL, 0, c.jumlah) AS jumlah_collect'),
                    )
                    ->leftJoin('agen as a', 'a.id', 'tiket_ordered.id_agen')
                    ->leftJoin('collect as c', 'tiket_ordered.no_invoice', 'c.no_invoice')
                    ->groupBy('tiket_ordered.no_invoice', 'jenis_penumpang')->get();
        $data = $this->separateByInvoice($data);
        $pi = PembayaranInvoice::whereIn('no_invoice', $request['no_invoice'])
                ->select('no_invoice', 'total_tagihan', DB::raw('total_tagihan - sisa_tagihan AS sudah_bayar'), 'sisa_tagihan', DB::raw('IF(sisa_tagihan = 0, "Lunas", "Belum Lunas") AS status_lunas'))
                ->orderBy('id', 'desc')->groupBy('no_invoice')->get()->toArray();
        $pi = $this->separateByInvoice($pi);
        $inv_data = DB::table('tiket_ordered')
                        ->select('no_invoice', DB::raw('"Invoice Dibuat" AS ket'), 'created_at')
                        ->whereIn('no_invoice', $request['no_invoice'])
                        ->groupBy('no_invoice');
        $hist_bayar = DB::table('pembayaran_invoice')
                        ->select('no_invoice', DB::raw('"Pembayaran" AS ket'), 'created_at')
                        ->whereIn('no_invoice', $request['no_invoice'])
                        ->union($inv_data)
                        ->orderBy('created_at')
                        ->get()->toArray();
        $hist_bayar = $this->separateByInvoice($hist_bayar);

        $agen = DB::table('tiket_ordered as to')
                    ->select('to.no_invoice', 'a.*')
                    ->leftJoin('agen as a', 'to.id_agen', 'a.id')
                    ->whereIn('no_invoice', $request['no_invoice'])
                    ->groupBy('no_invoice', 'id_agen')
                    ->get()->toArray();
        $agen = $this->separateByInvoice($agen);
        
        $collect = DB::table('collect')->whereIn('no_invoice', $request['no_invoice'])->get()->toArray();
        $appUrl = is_null(env('APP_URL')) ? 'http://maiharta.ddns.net:3333' : env('APP_URL');
        $penumpang = DB::table('tiket_ordered as to')
                        ->select(
                            'to.no_invoice', 
                            'kode_booking', 'nama_penumpang', 'no_identitas', 'jenis_kelamin', 'email',
                            'to.tanggal', 'to.waktu_berangkat as jam', 'kp.nama_kapal',
                            'd1.nama_dermaga as dermaga_awal', 'd2.nama_dermaga as dermaga_tujuan',
                            DB::raw('CONCAT("'.$appUrl.'/storage/img/qrcodes/", kode_booking, ".png") AS qrcode')
                        )
                        ->leftJoin('jadwal_jenispenumpang as jjp', 'to.id_jjp', 'jjp.id')
                        ->leftJoin('jadwal_tiket as jt', 'jjp.id_jadwal', 'jt.id')
                        ->leftJoin('kapal as kp', 'jt.id_kapal', 'kp.id')
                        ->leftJoin('rute as r', 'jt.id_rute', 'r.id')
                        ->leftJoin('dermaga as d1', 'r.id_dermaga_awal', 'd1.id')
                        ->leftJoin('dermaga as d2', 'r.id_dermaga_tujuan', 'd2.id')
                        ->whereIn('no_invoice', $request['no_invoice'])
                        ->where('flag_cancel', 0)
                        ->get()->toArray();
        
        $arrHari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $arrBulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November'];
        $penumpang = array_map(function ($e) use ($arrHari, $arrBulan) {
            $temp = strtotime($e->tanggal);
            $hari = $arrHari[date('w', $temp)];
            $bulan = $arrBulan[date('n', $temp)-1];
            $e->waktu_berangkat = $hari.", ".date('d', $temp)." ".$bulan." ".date('Y', $temp)." ".$e->jam;
            return $e;
        }, $penumpang);
        $penumpang = $this->separateByInvoice($penumpang);
        
        $pembayaran = [];
        foreach ($data as $key => $d) {
            if (!array_key_exists($key, $pi)) {
                $total_tagihan = array_reduce($d, function ($t, $e) {
                    $t += $e['subtotal'];
                    return $t;
                }, 0);
                $cl = array_filter($collect, function($e) use ($d) {
                    $e = (array) $e;
                    return $e['no_invoice'] == $d[0]['no_invoice'];
                });
                if (count($cl) == 1) {
                    $total_tagihan += (double) $cl[0]->jumlah;
                }
                $pembayaran[$key] = [
                    'no_invoice' => $key,
                    'total_tagihan' => $total_tagihan,
                    'sudah_bayar' => 0,
                    'sisa_tagihan' => $total_tagihan,
                    'status_lunas' => 'Belum Lunas'
                ];
            } else {
                $pembayaran[$key] = $pi[$key][0];
            }
        }

        $tanggals = [];
        foreach ($hist_bayar as $h) {
            $h = $h[0];
            $tanggal = date('d', strtotime($h['created_at']))." "
                        .$arrBulan[date('n', strtotime($h['created_at'])) - 1]." "
                        .date('Y', strtotime($h['created_at']));
            $tanggals[$h['no_invoice']] = $tanggal;
        }
        
        
        $data = [
            'data' => $data,
            'pembayaran' => $pembayaran,
            'history' => $hist_bayar,
            'agen' => $agen,
            'penumpang' => $penumpang,
            'tanggal_pembuatan' => $tanggals,
            'collect' => $collect
        ];
        // return $data;
        
        $pdf = Pdf::loadView('pdf.multi-invoice', $data);
        // return $pdf->download('Wahana Virendhra '.$request['no_invoice'].'.pdf');
        return $pdf->stream();
    }

    public function noInvoiceSuggestion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'search' => 'string',
            'limit' => 'integer'
        ]);
        if($validator->fails()) {
            return response()->json(['error'=>$validator->errors()], 400);
        }
        $data = DB::table('tiket_ordered')->select(DB::raw('DISTINCT CONCAT(no_invoice, "/", nama_penumpang) as no_invoice'))
                    ->where(DB::raw('CONCAT(no_invoice, "/", nama_penumpang)'), 'like', '%'.$request['search'].'%')
                    ->orderBy('created_at', 'desc')
                    ->limit($request['limit'])->get()->toArray();
        $temp = array_map(function($e) {
            return $e->no_invoice;
        }, $data);

        return response()->json([
            'data' => $temp
        ], 200);
    }

    private function separateByInvoice($data)
    {
        $temp = [];
        foreach ($data as $key => $value) {
            if ($value instanceof \stdClass) {
                $value = (array) $value;
            }
            if (array_key_exists($value['no_invoice'], $temp)) {
                array_push($temp[$value['no_invoice']], $value);
            } else {
                $temp[$value['no_invoice']] = [$value];
            }
        }
        
        return $temp;
    }
}

