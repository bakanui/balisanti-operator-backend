<?php

namespace App\Http\Controllers\Flow;

use App\Http\Controllers\Controller;
use App\Models\Agen;
use App\Models\Penumpang;
use App\Models\HargaService;
use App\Models\JadwalJenispenumpang;
use App\Models\TiketOrdered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\JadwalTiket;
use Illuminate\Support\Facades\DB;
use Mail;
use App\Mail\CreateOrderMail;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Log;

class PenjualanTiketController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['cariJadwalTanpaToken', 'emailCreateOrder']]);
    }
    public function cariTiket(Request $request)
    {
        $data = JadwalTiket::join('kapal as kp', 'kp.id', 'jadwal_tiket.id_kapal')
                    ->join('nahkoda as n', 'n.id', 'jadwal_tiket.id_nahkoda')
                    ->join('rute as r', 'r.id', 'jadwal_tiket.id_rute')
                    ->join('dermaga as d1', 'd1.id', 'r.id_dermaga_awal')
                    ->join('dermaga as d2', 'd2.id', 'r.id_dermaga_tujuan')
                    ->join('jadwal_jenispenumpang as jjp', 'jjp.id_jadwal', 'jadwal_tiket.id')
                    ->join('jenis_penumpang as jp', 'jp.id', 'jjp.id_jenis_penumpang')
                    ->select(
                        'jadwal_tiket.*', 'n.nama_nahkoda', 'r.nama_rute',
                        'kp.nama_kapal', 'd1.nama_dermaga as dermaga_awal', 'd2.nama_dermaga as dermaga_tujuan', 
                        'jp.tipe as tipe_penumpang', 'jp.jenis as jenis_penumpang',
                        'jjp.id as id_jenis_tiket', 'jjp.harga'
                    )
                    ->whereNull('jadwal_tiket.deleted_at')->where('jadwal_tiket.status_jadwal', 1)
                    ->whereNull('kp.deleted_at')->where('kp.status_kapal', 1)
                    ->whereNull('n.deleted_at')->where('n.status_nahkoda', 1)
                    ->whereNull('r.deleted_at')->where('r.status_rute', 1)
                    ->whereNull('d1.deleted_at')->where('d1.status_dermaga', 1)
                    ->whereNull('d2.deleted_at')->where('d2.status_dermaga', 1);
        
        if (isset($request['dermaga_asal'])) {
            $data = $data->where('d1.id', $request['dermaga_asal']);
        }
        if (isset($request['dermaga_tujuan'])) {
            $data = $data->where('d2.id', $request['dermaga_tujuan']);
        }
        if (isset($request['jam'])) {
            $data = $data->where('jadwal_tiket.waktu_berangkat', $request['jam']);
        }

        $cnt = $data->count();
        if (isset($request->sortBy)) {
            if (isset($request->order)) {
                $data = $data->orderBy("jadwal_tiket.".$request->sortBy, $request->order);
            } else {
                $data = $data->orderBy("jadwal_tiket.".$request->sortBy, 'asc');
            }
        } else {
            if (isset($request->order)) {
                $data = $data->orderBy('jadwal_tiket.id', $request->order);
            } else {
                $data = $data->orderBy('jadwal_tiket.id', 'asc');
            }
        }
        if (isset($request->limit)) {
            $data = $data->limit($request->limit);
            if (isset($request->pagenumber)) {
                $data = $data->offset(($request->pagenumber - 1) * $request->limit);
            }
            $totalpage = $request->limit > 0 ? ceil($cnt/$request->limit) : 0;
        } else {
            $totalpage = 1;
        }
        $data = $data->get();

        return response()->json([
            'data' => $data,
            'cnt' => $cnt,
            'totalPage' => $totalpage,
        ], 200);
    }

    public function cariJadwal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tanggal' => 'date',
        ]);
        if($validator->fails()) {
            return response()->json(['error'=>$validator->errors()], 400);
        }
        if (!isset($request['tanggal'])) {
            $request['tanggal'] = date('Y-m-d');
        }
        $data = JadwalTiket::join('kapal as kp', 'kp.id', 'jadwal_tiket.id_kapal')
                    ->join('nahkoda as n', 'n.id', 'jadwal_tiket.id_nahkoda')
                    ->join('rute as r', 'r.id', 'jadwal_tiket.id_rute')
                    ->join('dermaga as d1', 'd1.id', 'r.id_dermaga_awal')
                    ->join('dermaga as d2', 'd2.id', 'r.id_dermaga_tujuan')
                    ->leftJoin(DB::raw(
                            '(SELECT jt.id AS id_jadwal, COUNT(tor.id) AS dibooking, kp.`kapasitas_penumpang`-COUNT(tor.id) AS sisa_kursi, kp.kapasitas_penumpang FROM tiket_ordered tor
                            INNER JOIN jadwal_jenispenumpang jjp ON jjp.id=tor.`id_jjp`
                            INNER JOIN jadwal_tiket jt ON jt.id=jjp.`id_jadwal`
                            INNER JOIN kapal kp ON kp.id=jt.`id_kapal`
                            WHERE tor.tanggal = "'.$request['tanggal'].'" AND tor.flag_cancel = 0 AND tor.deleted_at IS NULL
                            GROUP BY jt.id) as tor'
                        ), 'tor.id_jadwal', 'jadwal_tiket.id'
                    )
                    ->select(
                        'jadwal_tiket.*', 'n.nama_nahkoda', 'r.nama_rute',
                        'kp.nama_kapal', 'r.id_dermaga_awal', 'd1.nama_dermaga as dermaga_awal', 'r.id_dermaga_tujuan', 'd2.nama_dermaga as dermaga_tujuan',
                        DB::raw('IFNULL(tor.sisa_kursi, kp.kapasitas_penumpang) as sisa_kursi'), 
                        'kp.kapasitas_penumpang'
                    )
                    ->whereNull('jadwal_tiket.deleted_at')->where('jadwal_tiket.status_jadwal', 1)
                    ->whereNull('kp.deleted_at')->where('kp.status_kapal', 1)
                    ->whereNull('n.deleted_at')->where('n.status_nahkoda', 1)
                    ->whereNull('r.deleted_at')->where('r.status_rute', 1)
                    ->whereNull('d1.deleted_at')->where('d1.status_dermaga', 1)
                    ->whereNull('d2.deleted_at')->where('d2.status_dermaga', 1)
                    ->where('jadwal_tiket.jenis_jadwal', '!=', 'Extra Trip');
        
        if (isset($request['dermaga_asal'])) {
            $data = $data->where('d1.id', $request['dermaga_asal']);
        }
        if (isset($request['dermaga_tujuan'])) {
            $data = $data->where('d2.id', $request['dermaga_tujuan']);
        }
        if (isset($request['jam'])) {
            $data = $data->where('jadwal_tiket.waktu_berangkat', $request['jam']);
        }
        // dd($request['tanggal']);
        if (isset($request['tanggal'])) {
            if (isset($request['tanggal_akhir'])) {
                $data = $data->orWhere(function($query) use ($request) {
                            $query->where('jadwal_tiket.jenis_jadwal', 'Extra Trip')
                                ->where(DB::raw('DATE(jadwal_tiket.created_at)'), '>=', $request['tanggal'])
                                ->where(DB::raw('DATE(jadwal_tiket.created_at)'), '<=', $request['tanggal_akhir']);
                        });
            } else {
                $data = $data->orWhere(function($query) use ($request) {
                            $query->where('jadwal_tiket.jenis_jadwal', 'Extra Trip')->where(DB::raw('DATE(jadwal_tiket.created_at)'), $request['tanggal']);
                        });
            }
        } else {
            $data = $data->orWhere(function($query) use ($request) {
                $query->where('jadwal_tiket.jenis_jadwal', 'Extra Trip')->where(DB::raw('DATE(jadwal_tiket.created_at)'), date('Y-m-d'));
            });
        }

        $cnt = $data->count();
        if (isset($request->sortBy)) {
            if (isset($request->order)) {
                $data = $data->orderBy("jadwal_tiket.".$request->sortBy, $request->order);
            } else {
                $data = $data->orderBy("jadwal_tiket.".$request->sortBy, 'asc');
            }
        } else {
            if (isset($request->order)) {
                $data = $data->orderBy('jadwal_tiket.id', $request->order);
            } else {
                $data = $data->orderBy('jadwal_tiket.id', 'asc');
            }
        }
        if (isset($request->limit)) {
            $data = $data->limit($request->limit);
            if (isset($request->pagenumber)) {
                $data = $data->offset(($request->pagenumber - 1) * $request->limit);
            }
            $totalpage = $request->limit > 0 ? ceil($cnt/$request->limit) : 0;
        } else {
            $totalpage = 1;
        }
        $data = $data->get();

        return response()->json([
            'data' => $data,
            'cnt' => $cnt,
            'totalPage' => $totalpage,
        ], 200);
    }

    public function cariJadwalTanpaToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tanggal' => 'date',
        ]);
        if($validator->fails()) {
            return response()->json(['error'=>$validator->errors()], 400);
        }
        if (!isset($request['tanggal'])) {
            $request['tanggal'] = date('Y-m-d');
        }
        $data = JadwalTiket::join('kapal as kp', 'kp.id', 'jadwal_tiket.id_kapal')
                    ->join('nahkoda as n', 'n.id', 'jadwal_tiket.id_nahkoda')
                    ->join('rute as r', 'r.id', 'jadwal_tiket.id_rute')
                    ->join('dermaga as d1', 'd1.id', 'r.id_dermaga_awal')
                    ->join('dermaga as d2', 'd2.id', 'r.id_dermaga_tujuan')
                    ->leftJoin(DB::raw(
                            '(SELECT jt.id AS id_jadwal, COUNT(tor.id) AS dibooking, kp.`kapasitas_penumpang`-COUNT(tor.id) AS sisa_kursi, kp.kapasitas_penumpang FROM tiket_ordered tor
                            INNER JOIN jadwal_jenispenumpang jjp ON jjp.id=tor.`id_jjp`
                            INNER JOIN jadwal_tiket jt ON jt.id=jjp.`id_jadwal`
                            INNER JOIN kapal kp ON kp.id=jt.`id_kapal`
                            WHERE tor.tanggal = "'.$request['tanggal'].'" AND tor.flag_cancel = 0 AND tor.deleted_at IS NULL
                            GROUP BY jt.id) as tor'
                        ), 'tor.id_jadwal', 'jadwal_tiket.id'
                    )
                    ->select(
                        'jadwal_tiket.*', 'n.nama_nahkoda', 'r.nama_rute', 'r.id_dermaga_awal', 'r.id_dermaga_tujuan',
                        'kp.nama_kapal', 'd1.nama_dermaga as dermaga_awal', 'd2.nama_dermaga as dermaga_tujuan',
                        DB::raw('IFNULL(tor.sisa_kursi, kp.kapasitas_penumpang) as sisa_kursi'), 'kp.kapasitas_penumpang',
                        'jadwal_tiket.image'
                    )
                    ->whereNull('jadwal_tiket.deleted_at')->where('jadwal_tiket.status_jadwal', 1)
                    ->whereNull('kp.deleted_at')->where('kp.status_kapal', 1)
                    ->whereNull('n.deleted_at')->where('n.status_nahkoda', 1)
                    ->whereNull('r.deleted_at')->where('r.status_rute', 1)
                    ->whereNull('d1.deleted_at')->where('d1.status_dermaga', 1)
                    ->whereNull('d2.deleted_at')->where('d2.status_dermaga', 1)
                    ->where('jadwal_tiket.jenis_jadwal', '!=', 'Extra Trip');
        
        if (isset($request['dermaga_asal'])) {
            $data = $data->where('d1.id', $request['dermaga_asal']);
        }
        if (isset($request['dermaga_tujuan'])) {
            $data = $data->where('d2.id', $request['dermaga_tujuan']);
        }
        if (isset($request['jam'])) {
            $data = $data->where('jadwal_tiket.waktu_berangkat', $request['jam']);
        }
        // dd($request['tanggal']);
        if (isset($request['tanggal'])) {
            $data = $data->orWhere(function($query) use ($request) {
                            $query->where('jadwal_tiket.jenis_jadwal', 'Extra Trip')->where(DB::raw('DATE(jadwal_tiket.created_at)'), $request['tanggal']);
                        });
        } else {
            $data = $data->orWhere(function($query) use ($request) {
                $query->where('jadwal_tiket.jenis_jadwal', 'Extra Trip')->where(DB::raw('DATE(jadwal_tiket.created_at)'), date('Y-m-d'));
            });
        }

        $cnt = $data->count();
        if (isset($request->sortBy)) {
            if (isset($request->order)) {
                $data = $data->orderBy("jadwal_tiket.".$request->sortBy, $request->order);
            } else {
                $data = $data->orderBy("jadwal_tiket.".$request->sortBy, 'asc');
            }
        } else {
            if (isset($request->order)) {
                $data = $data->orderBy('jadwal_tiket.id', $request->order);
            } else {
                $data = $data->orderBy('jadwal_tiket.id', 'asc');
            }
        }
        if (isset($request->limit)) {
            $data = $data->limit($request->limit);
            if (isset($request->pagenumber)) {
                $data = $data->offset(($request->pagenumber - 1) * $request->limit);
            }
            $totalpage = $request->limit > 0 ? ceil($cnt/$request->limit) : 0;
        } else {
            $totalpage = 1;
        }
        $data = $data->get();

        return response()->json([
            'data' => $data,
            'cnt' => $cnt,
            'totalPage' => $totalpage,
        ], 200);
    }

    public function cariJenisPenumpangJadwal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_jadwal' => 'required|string',
        ]);
        if($validator->fails()) {
            return response()->json(['error'=>$validator->errors()], 400);
        }
        $data = JadwalJenispenumpang::join('jenis_penumpang as jp', 'jadwal_jenispenumpang.id_jenis_penumpang', 'jp.id')
                    ->select(
                        'jadwal_jenispenumpang.id as id_jenis_tiket', 'jp.id as id_jenis_penumpang', DB::raw('CONCAT(jp.tipe, " - ", jp.jenis) AS jenis_penumpang'), 
                        'jadwal_jenispenumpang.harga as harga_tiket'
                    )
                    ->whereNull('jp.deleted_at')
                    ->where('jp.status_jenis_penumpang', 1)
                    ->where('jadwal_jenispenumpang.id_jadwal', $request->id_jadwal);

        $cnt = $data->count();
        if (isset($request->sortBy)) {
            if (isset($request->order)) {
                $data = $data->orderBy("jadwal_jenispenumpang.".$request->sortBy, $request->order);
            } else {
                $data = $data->orderBy("jadwal_jenispenumpang.".$request->sortBy, 'asc');
            }
        } else {
            if (isset($request->order)) {
                $data = $data->orderBy('jadwal_jenispenumpang.id', $request->order);
            } else {
                $data = $data->orderBy('jadwal_jenispenumpang.id', 'asc');
            }
        }
        if (isset($request->limit)) {
            $data = $data->limit($request->limit);
            if (isset($request->pagenumber)) {
                $data = $data->offset(($request->pagenumber - 1) * $request->limit);
            }
            $totalpage = $request->limit > 0 ? ceil($cnt/$request->limit) : 0;
        } else {
            $totalpage = 1;
        }
        $data = $data->get();

        return response()->json([
            'data' => $data,
            'cnt' => $cnt,
            'totalPage' => $totalpage,
        ], 200);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tanggal' => 'required|date',
            'penumpangs' => 'required|array',
            'collect' => 'decimal:0,11'
        ]);
        if($validator->fails()) {
            return response()->json(['error'=>$validator->errors()], 400);
        }
        $arrIdJenisTiket = array();
        foreach ($request->input('penumpangs') as $p) {
            $validator2 = Validator::make($p, [
                'id_jenis_tiket' => 'required',
                'nama_penumpang' => 'required|string',
                'no_telepon' => 'required|string',
                'no_identitas' => 'required|string',
                'jenis_kelamin' => 'required|string|max:1',
                'email' => 'required|email',
            ]);
            if($validator2->fails()) {
                return response()->json(['error'=>$validator2->errors()], 400);
            }
            // if(Penumpang::where('no_telepon', $p['no_telepon'])->exists()){
            //     if(Penumpang::where('nama', $p['nama_penumpang'])->exists()){
            //         $message = "Success!";
            //     }else{
            //         $message = "No. Telepon " . $p['no_telepon'] . " sudah digunakan oleh orang lain.";
            //         return response()->json(['error'=>$message], 400);
            //     }
            // }else{
                $penumpang = new Penumpang;
 
                $penumpang->nama = $p['nama_penumpang'];
                $penumpang->no_identitas = $p['no_identitas'];
                $penumpang->jenis_kelamin = $p['jenis_kelamin'];
                $penumpang->email = $p['email'];
                $penumpang->no_telepon = $p['no_telepon'];
        
                $penumpang->save();
            // }

            array_push($arrIdJenisTiket, $p['id_jenis_tiket']);
        }
        $valid = $validator->validated();
        if (!$this->checkIfAgenOrPerantara($valid['penumpangs'][0])) {
            return response()->json(['error' => 'Tidak dapat menggunakan jasa agen dan perantara bersamaan'], 400);
        }
        $is_pp = 0;

        if (isset($request['id_jadwal_pulang']) && isset($request['tanggal_pulang'])) {
            $penumpangs_pulang = $this->createArrayPenumpangsPulang($valid['penumpangs'], $request['id_jadwal_pulang'], $arrIdJenisTiket);
            if (is_null($penumpangs_pulang)) {
                return response()->json(['error' => 'Jadwal untuk tiket pulang belum ada, harap ditambahkan terlebih dahulu.'], 404);
            }
            $arrIdJenisTiketPulang = array_map(function($e) { return $e['id_jenis_tiket']; }, $penumpangs_pulang);
            $pelengkaps_pulang = $this->createDataPelengkapOrder($arrIdJenisTiketPulang);
            $is_pp = 1;
            if (is_null($pelengkaps_pulang) or count($pelengkaps_pulang) != count(array_unique($arrIdJenisTiket))) {
                return response()->json(['error' => 'Id jenis tiket untuk kepulangan tidak valid.'], 400);
            }
        }

        $data = array();
        $pelengkaps = $this->createDataPelengkapOrder($arrIdJenisTiket);
        if (is_null($pelengkaps) or count($pelengkaps) != count(array_unique($arrIdJenisTiket))) {
            return response()->json(['error' => 'Id jenis tiket tidak valid.'], 400);
        }
        if (isset($request['id_agen'])) {
            $agen = Agen::find($request['id_agen']);
        }
        if (isset($request['id_service'])) {
            $service = HargaService::find($request['id_service']);
        }
        if (isset($request['flag_lunas'])) {
            $flag_lunas = $request['flag_lunas'];
        }

        // $last_kode_booking = TiketOrdered::whereDate('created_at', date('Y-m-d'))
        //                 ->select('kode_booking')
        //                 ->orderBy('kode_booking', 'desc')
        //                 ->first();
        // $no_booking = (!is_null($last_kode_booking)) ? (int) substr($last_kode_booking, -4, 4) : 0;
        $no_booking = time();
        $no_invoice = 'INV-'.$no_booking;
        $no = 1;
        $mail_responses = array();
        $no_penumpang = 1;
        foreach ($valid['penumpangs'] as $v) {
            $v['id_jjp'] = $v['id_jenis_tiket'];
            unset($v['id_jenis_tiket']);
            $v['tanggal'] = $valid['tanggal'];
            $v['kode_booking'] = $no_booking.$no;
            $v['no_invoice'] = $no_invoice;
            $v['is_pp'] = $is_pp;
            $v['no_penumpang'] = $no_penumpang;
            $v['keterangan'] = 'GO';
            $no++;
            $no_penumpang++;
            $pelengkap = array_filter($pelengkaps, function($e) use ($v) {
                return $e['id'] == $v['id_jjp'];
            });
            $pelengkap = array_shift($pelengkap);
            foreach ($pelengkap as $key => $p) {
                if ($key != 'id') {
                    $v[$key] = $p;
                }
            }
            $v['total'] = $v['harga_tiket'];
            if (isset($agen) and !is_null($agen)) {
                $v['id_agen'] = $agen->id;
                // if ($agen->jenis_diskon == 'persen') {
                    // $v['diskon_agen'] = $v['harga_tiket'] * $agen->nominal_diskon / 100;
                // } else {
                    $v['diskon_agen'] = $request['diskon_agen'];
                // }
                $v['total'] -= $v['diskon_agen'];
            }
            if (isset($service) and !is_null($service)) {
                $v['id_service'] = $service->id;
                $v['harga_service'] = $service->harga;
                $v['total'] += $v['harga_service'];
            }
            if (isset($request['nama_perantara'])) {
                $v['nama_perantara'] = $request['nama_perantara'];
                $v['tambahan_harga'] = $request['tambahan_harga'];
                $v['total'] += $v['tambahan_harga'];
            }
            if (isset($flag_lunas) and !is_null($flag_lunas)) {
                $v['flag_lunas'] = $flag_lunas;
            }
            $v['created_at'] = date('Y-m-d H:i:s');
            $v['updated_at'] = date('Y-m-d H:i:s');
            array_push($data, $v);
            array_push($mail_responses, $this->sendEmailOrder($v));
        }
        if (isset($penumpangs_pulang)) {
            $no_penumpang = 1;
            foreach ($penumpangs_pulang as $v) {
                $v['id_jjp'] = $v['id_jenis_tiket'];
                unset($v['id_jenis_tiket']);
                $v['tanggal'] = $request['tanggal_pulang'];
                $v['kode_booking'] = $no_booking.$no;
                $v['no_invoice'] = $no_invoice;
                $v['is_pp'] = $is_pp;
                $v['no_penumpang'] = $no_penumpang;
                $v['keterangan'] = 'RT';
                $no++;
                $no_penumpang++;
                $pelengkap = array_filter($pelengkaps_pulang, function($e) use ($v) {
                    return $e['id'] == $v['id_jjp'];
                });
                $pelengkap = array_shift($pelengkap);
                foreach ($pelengkap as $key => $p) {
                    if ($key != 'id') {
                        $v[$key] = $p;
                    }
                }
                $v['total'] = $v['harga_tiket'];
                if (isset($agen) and !is_null($agen)) {
                    $v['id_agen'] = $agen->id;
                    // if ($agen->jenis_diskon == 'persen') {
                        // $v['diskon_agen'] = $v['harga_tiket'] * $agen->nominal_diskon / 100;
                    // } else {
                        $v['diskon_agen'] = $request['diskon_agen'];
                    // }
                    $v['total'] -= $v['diskon_agen'];
                }
                if (isset($service) and !is_null($service)) {
                    $v['id_service'] = null;
                    $v['harga_service'] = 0;
                    // $v['total'] += $v['harga_service'];
                }
                if (isset($request['nama_perantara'])) {
                    $v['nama_perantara'] = $request['nama_perantara'];
                    $v['tambahan_harga'] = $request['tambahan_harga'];
                    $v['total'] += $v['tambahan_harga'];
                }
                if (isset($flag_lunas) and !is_null($flag_lunas)) {
                    $v['flag_lunas'] = $flag_lunas;
                }
                $v['created_at'] = date('Y-m-d H:i:s');
                $v['updated_at'] = date('Y-m-d H:i:s');
                array_push($data, $v);
                // array_push($mail_responses, $this->sendEmailOrder($v));
            }
        }
        DB::transaction(function() use ($data, $request, $no_invoice) {
            TiketOrdered::insert($data);
            if (isset($request['collect']) and $request['collect'] > 0) {
                DB::table('collect')->insert([
                    'no_invoice' => $no_invoice,
                    'jumlah' => $request['collect'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
        });
        
        Log::channel('single')->info('Penjualan tiket sukses oleh user ['.auth()->user()->name.'].', $validator->validated());
        $data['mail_responses'] = $mail_responses;

        return response()->json($data, 201);
    }

    public function logTiketOrdered(Request $request)
    {
        $data = TiketOrdered::whereNull('deleted_at');
        if (isset($request->nama)) {
            $data = $data->where('nama_penumpang', 'like', '%'.$request->nama.'%');
        }
        if (isset($request->lunas)) {
            $data = $data->where('flag_lunas', $request->lunas);
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

    public function emailCreateOrder()
    {
        $data = DB::table('tiket_ordered as to')
                    ->select('id', 'kode_booking', 'nama_penumpang', 'tanggal', 'waktu_berangkat', 'dermaga_awal', 'dermaga_tujuan')
                    ->where('kode_booking', '16798051152')
                    ->first();

        return view('mail/create-order', ['data' => $data]);
    }

    private function sendEmailOrder($data)
    {

        try {
            if (str_contains($data['jenis_penumpang'], 'Mancanegara')) {
                $data['bahasa'] = 'eng';
            } else {
                $data['bahasa'] = 'idn';
            }
            // $appUrl = is_null(env('APP_URL')) ? 'http://maiharta.ddns.net:3333' : env('APP_URL');
            $qr = QrCode::format('png')->size(200)->generate($data['kode_booking']);
            Storage::disk('public')->put('/img/qrcodes/'.$data['kode_booking'].'.png', $qr);
            Mail::to($data['email'])->send(new CreateOrderMail(collect($data)));
            // Storage::disk('public')->delete('/img/qrcodes/'.$data['kode_booking'].'.png');
            return 'success';
        } catch (\Throwable $th) {
            return $th->getMessage();
        }
    }

    private function createArrayPenumpangsPulang($penumpangs, $id_jadwal_pulang, $arrIdJenisTiket)
    {
        $idJJP = JadwalJenispenumpang::whereIn('id', $arrIdJenisTiket)->select('id', 'id_jenis_penumpang')->get()->toArray();
        $arrIdJenisPenum = array_map(function($e) { return $e['id_jenis_penumpang']; }, $idJJP);
        $idJJPPulang = JadwalJenispenumpang::where('id_jadwal', $id_jadwal_pulang)
                        ->whereIn('id_jenis_penumpang', $arrIdJenisPenum)
                        ->select('id', 'id_jenis_penumpang')
                        ->get()->toArray();
        $penumpangs_pulang = array();
        foreach ($penumpangs as $value) {
            $temp = null;
            $tempIdJenisPenum = null;
            foreach ($idJJP as $i) {
                if ($i['id'] == $value['id_jenis_tiket']) {
                    $tempIdJenisPenum = $i['id_jenis_penumpang'];
                    break;
                }
            }
            foreach ($idJJPPulang as $i) {
                if ($i['id_jenis_penumpang'] == $tempIdJenisPenum) {
                    $temp = $i;
                    break;
                }
            }
            if (is_null($temp)) {
                return null;
            }
            $temp2 = $value;
            $temp2['id_jenis_tiket'] = $temp['id'];
            array_push($penumpangs_pulang, $temp2);
        }

        return $penumpangs_pulang;
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

    private function checkIfAgenOrPerantara($penumpang) {
        if (isset($penumpang['id_agen']) and isset($penumpang['nama_perantara'])) {
            return false;
        }
        return true;
    }
}

