<?php

namespace App\Http\Controllers\Flow;

use App\Http\Controllers\Controller;
use App\Models\TiketOrdered;
use App\Models\JadwalJenispenumpang;
use App\Models\Penumpang;
use App\Models\Siwalatri\Passengers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class PenumpangController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => []]);
    }
    public function edit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'kode_booking' => 'required|string',
            'nama_penumpang' => 'required|string|max:180',
            'no_identitas' => 'required|string|max:100',
            'jenis_kelamin' => 'required|string|max:1',
            'email' => 'required|string|max:180',
            'cancel' => 'integer|min:0|max:1',
        ]);
        if($validator->fails()) {
            return response()->json(['error'=>$validator->errors()], 400);
        }
        if (!isset($request['cancel'])) {
            $request['cancel'] = 0;
        } else {
            if ($request['cancel'] == 1) {
                $tiket = DB::table('tiket_ordered')
                    ->where('kode_booking', $request['kode_booking'])
                    ->first();
                if($request['payment'] !== "cash"){
                    if ($this->checkStatusBayar($tiket->no_invoice)) {
                        return response()->json(['message'=>'Pembatalan gagal. Invoice dari tiket terkait sudah menerima pembayaran'], 400);
                    }else{
                        $del = Http::withHeaders(['Content-Type' => 'application/json'])->send('POST', 'http://maiharta.ddns.net:8888/api/logs/deletion', [
                            'body' => json_encode([
                                'id_invoice' => $request['kode_booking'],
                            ])
                        ]);
                    }
                }
            }
        }
        
        DB::transaction(function() use ($request) {
            $tiket = DB::table('tiket_ordered')->where('kode_booking', $request['kode_booking'])->first();
            DB::table('tiket_ordered')
                ->where('kode_booking', $request['kode_booking'])
                ->update([
                    'nama_penumpang' => $request['nama_penumpang'],
                    'no_identitas' => $request['no_identitas'],
                    'jenis_kelamin' => $request['jenis_kelamin'],
                    'email' => $request['email'],
                    'flag_cancel' => $request['cancel'],
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            $cnt_tiket = DB::table('tiket_ordered')
                        ->where('no_invoice', $tiket->no_invoice)
                        ->where('flag_cancel', 0)
                        ->count();
            if ($cnt_tiket == 0) {
                DB::table('tiket_ordered')->where('no_invoice', $tiket->no_invoice)
                    ->update([
                        'deleted_at' => date('Y-m-d H:i:s')
                    ]);
            }
        });
        Log::channel('single')->info('Edit tiket sukses oleh user ['.auth()->user()->name.'].', $validator->validated());

        $tiket = TiketOrdered::join('jadwal_jenispenumpang as jjp', 'tiket_ordered.id_jjp', 'jjp.id')
                    ->join('jadwal_tiket as jt', 'jt.id', 'jjp.id_jadwal')
                    ->join('rute as r', 'r.id', 'jt.id_rute')
                    ->leftJoin('manifest as m', 'm.kode_booking', 'tiket_ordered.kode_booking')
                    ->leftJoin('users as u', 'u.id', 'tiket_ordered.id_user_input')
                    ->leftJoin('roles as rl', 'u.id_role', 'rl.id')
                    ->leftJoin('agen as a', 'tiket_ordered.id_agen', 'a.id')
                    ->select(
                        'tiket_ordered.kode_booking','no_invoice', 'nama_penumpang', 'tiket_ordered.email', 'jenis_kelamin', 'no_identitas',
                        'tanggal', 'tiket_ordered.waktu_berangkat', DB::raw('IF(m.id IS NULL, "Belum Datang", IF(m.status_checker=1, "Sudah Datang", "Sudah Masuk")) AS status_manifest'),
                        'rl.id as id_created_by', 'rl.nama_role as created_by', DB::raw('IFNULL(a.nama_agen, "-") AS nama_agen'),
                        DB::raw('IF(tiket_ordered.id_service IS NULL, 0, 1) AS service'), 'tiket_ordered.harga_service', 'tiket_ordered.flag_cancel'
                    )
                    ->where('tiket_ordered.kode_booking', $request['kode_booking'])->first();
    
        if (is_null($tiket)) {
            return response()->json([
                'message'=>'Semua tiket telah dibatalkan, invoice dihapus.',
            ], 200);
        }

        if ($request['cancel'] == 1) {
            return response()->json([
                'message'=>'Tiket telah dibatalkan.',
                'tiket' => $tiket
            ], 200);
        }
        return response()->json([
            'message'=>'Edit tiket berhasil',
            'tiket' => $tiket
        ], 200);
    }

    public function editInvoice(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'no_invoice' => 'required|string',
            // 'id_agen' => 'required|integer|min:0',
            // 'nama_perantara' => 'required|string',
            // 'tambahan_harga' => 'required|decimal:0,11',
            // 'id_service' => 'required|integer|min:0',
            'set_pp' => 'required|integer|min:0|max:1',
            // 'update_rute_berangkat' => 'required|boolean',
            // 'update_rute_pulang' => 'required|boolean',
            'tanggal_berangkat' => 'date',
            'tanggal_pulang' => 'date',
            'id_jjp' => 'array',
            'id_jjp_pulang' => 'array',
        ]);
        if($validator->fails()) {
            return response()->json(['error'=>$validator->errors()], 400);
        }
        if (isset($request['id_jjp'])) {
            foreach ($request['id_jjp'] as $v) {
                $validator2 = Validator::make($v, [
                    'id' => 'required|integer|min:1',
                ]);
                if($validator2->fails()) {
                    return response()->json(['error'=>$validator2->errors()], 400);
                }
            }
        }
        if (isset($request['id_jjp_pulang'])) {
            foreach ($request['id_jjp_pulang'] as $v) {
                $validator2 = Validator::make($v, [
                    'id' => 'required|integer|min:1',
                ]);
                if($validator2->fails()) {
                    return response()->json(['error'=>$validator2->errors()], 400);
                }
            }
        }
        
        if ($request['update_rute_berangkat'] and !isset($request['id_jjp'])) {
            return response()->json(['message'=>'Harap sertakan list id jadwal jenis penumpang keberangkatan'], 400);
        }
        if ($request['update_rute_pulang'] and !isset($request['id_jjp_pulang'])) {
            return response()->json(['message'=>'Harap sertakan list id jadwal jenis penumpang kepulangan'], 400);
        }
        $tinvoice = TiketOrdered::where('no_invoice', $request['no_invoice'])->first();
        if ($tinvoice->pembayaran !== "cash"){
            if ($this->checkStatusBayar($request['no_invoice'])) {
                return response()->json(['message'=>'Edit gagal. Invoice dari tiket terkait sudah menerima pembayaran'], 400);
            }
        }

        DB::beginTransaction();
        try {
            $tikets = DB::table('tiket_ordered as to')
                        ->select("to.*", DB::raw("REPLACE(to.kode_booking, SUBSTRING_INDEX(to.no_invoice, '-', -1), '') AS urutan"))
                        ->where('to.no_invoice', $request['no_invoice'])
                        ->where('to.flag_cancel', 0)
                        ->whereNull('to.deleted_at')
                        ->orderBy('to.id', 'asc')->lockForUpdate()->get();
            
            if ($request['set_pp'] == 1 and $tikets[0]->is_pp != 1) {
                if (!isset($request['id_jjp_pulang']) or count($request['id_jjp_pulang']) != count($tikets)) {
                    return response()->json(['message'=>'Id jadwal jenis penumpang pulang wajib diisi jika ingin mengubah invoice menjadi PP'], 400);
                }
                $this->createPenumpangPulang($request, $tikets);
            } else if ($request['set_pp'] == 0) {
                DB::table('tiket_ordered')->where('no_invoice', $request['no_invoice'])->where('keterangan', 'GO')
                    ->update(['is_pp' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
                DB::table('tiket_ordered')->where('no_invoice', $request['no_invoice'])->where('keterangan', 'RT')->delete();
            }
            if ($request['update_rute_berangkat']) {
                $this->updateRute($request['id_jjp'], $tikets, 'GO');
            }
            if ($request['update_rute_pulang']) {
                $this->updateRute($request['id_jjp_pulang'], $tikets, 'RT');
            }
            if (isset($request['tanggal_berangkat'])) {
                DB::table('tiket_ordered')->where('no_invoice', $request['no_invoice'])->where('keterangan', 'GO')->update(['tanggal' => $request['tanggal_berangkat']]);
            }
            if (isset($request['tanggal_pulang'])) {
                DB::table('tiket_ordered')->where('no_invoice', $request['no_invoice'])->where('keterangan', 'RT')->update(['tanggal' => $request['tanggal_pulang']]);
            }

            if (isset($request['penumpang'])) {
                $kode = preg_replace('/[^0-9]/','',$request['no_invoice']);
                foreach ($request['penumpang'] as $penum) {
                    $kode_booking = $kode . $loop->index + 1;
                    $tpenum = TiketOrdered::where('kode_booking', $kode_booking)->first();
                    $tpenum->nama_penumpang = $penum->nama;
                    $tpenum->save();
                }
            }

            $updates = [];
            $totalDBRaw = 'harga_tiket+harga_service';
            if ($request['id_agen'] > 0) {
                $agen = DB::table('agen')->where('status_agen', 1)->where('id', $request['id_agen'])->first();
                if (is_null($agen)) {
                    return response()->json(['message'=>'Edit gagal. Agen tidak ditemukan / id_agen invalid'], 400);
                }
                $agen = (array) $agen;
                $updates['id_agen'] = $agen['id'];
                if ($agen['jenis_diskon'] == 'nominal') {
                    $diskon_agen = $agen['nominal_diskon'];
                    $totalDBRaw .= '-'.$agen['nominal_diskon'];
                } else {
                    $diskon_agen = DB::raw('harga_tiket * '.$agen['nominal_diskon']);
                    $totalDBRaw .= '-harga_tiket*'.($agen['nominal_diskon']/100);
                }
                $updates['diskon_agen'] = $diskon_agen;
            } else {
                $updates['id_agen'] = null;
                $updates['diskon_agen'] = 0;
            }
            if (!is_null($request['nama_perantara']) or $request['nama_perantara'] != "") {
                $updates['nama_perantara'] = $request['nama_perantara'];
                $updates['tambahan_harga'] = $request['tambahan_harga'];
                $totalDBRaw .= '+'.$request['tambahan_harga'];
            } else {
                $updates['nama_perantara'] = null;
                $updates['tambahan_harga'] = 0;
            }
            $updates['total'] = DB::raw($totalDBRaw);
            DB::table('tiket_ordered')
                ->where('no_invoice', $request['no_invoice'])
                ->where('flag_cancel', 0)
                ->whereNull('deleted_at')
                ->update($updates);

            // UPDATE SERVICE PENJEMPUTAN KHUSUS TIKET PERGI
            $id_tikets = array_map(function($t) {
                $temp = (array) $t;
                return $temp['id'];
            }, $tikets->toArray());
            if ($tikets[0]->is_pp == 1) {
                $id_tikets = array_slice($id_tikets, 0, count($id_tikets)/2);
            }
            if ($request['id_service'] > 0) {
                $service = DB::table('harga_service')->where('status_service', 1)->where('id', $request['id_service'])->first();
                if (is_null($service)) {
                    return response()->json(['message'=>'Edit gagal. Service tidak ditemukan / id_service invalid'], 400);
                }
                $service = (array) $service;
                DB::table('tiket_ordered')->whereIn('id', $id_tikets)->update([
                    'id_service' => $service['id'],
                    'harga_service' => $service['harga'],
                    'total' => DB::raw('harga_tiket-diskon_agen+tambahan_harga+'.$service['harga'])
                ]);
            } else {
                DB::table('tiket_ordered')->whereIn('id', $id_tikets)->update([
                    'id_service' => null,
                    'harga_service' => 0,
                    'total' => DB::raw('harga_tiket-diskon_agen+tambahan_harga')
                ]);
            }

            DB::commit();
            Log::channel('single')->info('Edit invoice sukses oleh user ['.auth()->user()->name.'].', $validator->validated());
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::channel('single')->error('Edit invoice gagal oleh user ['.auth()->user()->name.'].', $th->getTrace());
        }

        $tiket = TiketOrdered::join('jadwal_jenispenumpang as jjp', 'tiket_ordered.id_jjp', 'jjp.id')
                    ->join('jadwal_tiket as jt', 'jt.id', 'jjp.id_jadwal')
                    ->join('rute as r', 'r.id', 'jt.id_rute')
                    ->leftJoin('manifest as m', 'm.kode_booking', 'tiket_ordered.kode_booking')
                    ->leftJoin('users as u', 'u.id', 'tiket_ordered.id_user_input')
                    ->leftJoin('roles as rl', 'u.id_role', 'rl.id')
                    ->leftJoin('agen as a', 'tiket_ordered.id_agen', 'a.id')
                    ->select(
                        'tiket_ordered.kode_booking','no_invoice', 'nama_penumpang', 'tiket_ordered.email', 'jenis_kelamin', 'no_identitas',
                        'tanggal', 'tiket_ordered.waktu_berangkat', DB::raw('IF(m.id IS NULL, "Belum Datang", IF(m.status_checker=1, "Sudah Datang", "Sudah Masuk")) AS status_manifest'),
                        'rl.id as id_created_by', 'rl.nama_role as created_by', DB::raw('IFNULL(a.nama_agen, "-") AS nama_agen'),
                        DB::raw('IF(tiket_ordered.id_service IS NULL, 0, 1) AS service'), 'tiket_ordered.harga_service'
                    )
                    ->where('tiket_ordered.no_invoice', $request['no_invoice'])->where('flag_cancel', 0)->get();

        return response()->json([
            'message'=>'Edit invoice berhasil',
            'tiket' => $tiket,
        ], 200);
    }

    private function updateRute($id_jjps, $tikets, $ket) 
    {
        $temp = array_map(function($e) { return $e['id']; }, $id_jjps);
        $temp = array_unique($temp);
        $pelengkaps = $this->createDataPelengkapOrder($temp);
        //UPDATE TIKET DENGAN JJP BARU
        $x = [];
        foreach ($tikets as $tiket) {
            if ($tiket->keterangan == $ket) {
                array_push($x, $tiket);
            }
        }
        $tikets = $x;
        foreach ($tikets as $k => $t) {
            $t = (array) $t;
            $pelengkap = array_filter($pelengkaps, function($e) use ($id_jjps, $k) { return $e['id'] == $id_jjps[$k]['id']; });
            $pelengkap = array_shift($pelengkap);
            $updates = [];
            foreach ($pelengkap as $key => $v) {
                if ($key != 'id') {
                    $updates[$key] = $v;
                } else {
                    $updates['id_jjp'] = $v;
                }
                $updates['updated_at'] = date('Y-m-d H:i:s');
            }
            DB::table('tiket_ordered')->where('kode_booking', $t['kode_booking'])
                ->where('keterangan', $ket)
                ->update($updates);
        }
    }

    private function createPenumpangPulang($request, $tikets)
    {
        $temp = array_map(function($e) { return $e['id']; }, $request['id_jjp_pulang']);
        $temp = array_unique($temp);
        $pelengkaps = $this->createDataPelengkapOrder($temp);
        $inserts = [];
        $ts = explode("-", $request['no_invoice'])[1];
        $nomor = (int) $tikets[count($tikets)-1]->urutan;
        foreach ($tikets as $k => $t) {
            $pelengkap = array_filter($pelengkaps, function($e) use ($request, $k) { return $e['id'] == $request['id_jjp_pulang'][$k]['id']; });
            $pelengkap = array_shift($pelengkap);
            $insert = [];
            $t = (array) $t;
            $insert['no_invoice'] = $t['no_invoice'];
            $insert['nama_penumpang'] = $t['nama_penumpang'];
            $insert['no_identitas'] = $t['no_identitas'];
            $insert['jenis_kelamin'] = $t['jenis_kelamin'];
            $insert['email'] = $t['email'];
            $insert['keterangan'] = 'RT';
            foreach ($pelengkap as $key => $v) {
                if ($key == 'id') {
                    $insert['id_jjp'] = $v;
                } else {
                    $insert[$key] = $v;
                }
            }
            $nomor++;
            $insert['kode_booking'] = $ts.$nomor;
            $insert['created_at'] = date('Y-m-d H:i:s');
            $insert['updated_at'] = date('Y-m-d H:i:s');
            array_push($inserts, $insert);
        }
        TiketOrdered::insert($inserts);
        DB::table('tiket_ordered')->where('no_invoice', $request['no_invoice'])->update(['is_pp' => 1]);
    }

    private function createDataPelengkapOrder($arrIdJenisTiket)
    {
        $pelengkaps = JadwalJenispenumpang::join('jadwal_tiket as jt', 'jadwal_jenispenumpang.id_jadwal', 'jt.id')
                        ->join('kapal as kp', 'kp.id', 'jt.id_kapal')
                        ->join('nahkoda as n', 'n.id', 'jt.id_nahkoda')
                        ->join('rute as r', 'r.id', 'jt.id_rute')
                        ->join('dermaga as d1', 'd1.id', 'r.id_dermaga_awal')
                        ->join('dermaga as d2', 'd2.id', 'r.id_dermaga_tujuan')
                        ->join('jenis_penumpang as jp', 'jp.id', 'jadwal_jenispenumpang.id_jenis_penumpang')
                        ->select(
                            'jadwal_jenispenumpang.id',
                            'kp.nama_kapal', 'n.nama_nahkoda', 'd1.nama_dermaga as dermaga_awal',
                            'd2.nama_dermaga as dermaga_tujuan', DB::raw('CONCAT(jp.jenis, " - ", jp.tipe) as jenis_penumpang'),
                            'jadwal_jenispenumpang.harga as harga_tiket', 'jt.waktu_berangkat', DB::raw('"'.auth()->user()->id.'" AS id_user_input')
                        )
                        ->whereIn('jadwal_jenispenumpang.id', $arrIdJenisTiket)->get()->toArray();

        return $pelengkaps;
    }

    private function validatePulang($request) {
        $messages = [];
        if ($request['set_pp'] == 1 and !isset($request['id_jadwal_pulang'])) {
            array_push($messages, 'Id jadwal pulang wajib diisi jika PP.');
            if ($request['set_pp'] == 1 and !isset($request['tanggal_pulang'])) {
                array_push($messages, 'Tanggal pulang wajib diisi jika PP.');
            }
            return response()->json(['message'=>$messages], 400);
        }

    }

    private function checkStatusBayar($no_invoice)
    {
        $cnt_bayar = DB::table('pembayaran_invoice')->where('no_invoice', $no_invoice)->whereNull('deleted_at')->count();
        if ($cnt_bayar > 0) {
            return true;
        }else{
            $response = Http::withHeaders(['Content-Type' => 'application/json'])->send('POST', 'http://maiharta.ddns.net:8888/api/logs/delete', [
                'body' => json_encode([
                    'id_invoice' => $no_invoice,
                ])
            ]);
            $callBPD = $response->json();
            if ($callBPD > 0) {
                return true;
            }else{
                return false;
            }
        }
    }

    public function findByPhone(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'no_telepon' => 'required|string|max:100',
        ]);
        if($validator->fails()) {
            return response()->json(['error'=>$validator->errors()], 400);
        }
        $penumpang = Penumpang::where('no_telepon', $request['no_telepon'])->get();
        return response()->json([$penumpang[0]], 200);

    }
}

