<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\JadwalTiket;
use App\Models\JadwalJenispenumpang;
use GuzzleHttp\Client;
use Storage;

class JadwalTiketController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    private function getSingleWithChild($id)
    {
        $data = JadwalTiket::with(['rute' => function($query) {
            $query->join('dermaga as d1', 'rute.id_dermaga_awal', 'd1.id')
                ->join('dermaga as d2', 'rute.id_dermaga_tujuan', 'd2.id')
                ->select('rute.id', 'rute.nama_rute', 'rute.id_dermaga_awal', 'rute.id_dermaga_tujuan', 'd1.nama_dermaga as nama_dermaga_awal', 'd2.nama_dermaga as nama_dermaga_tujuan');
            }])->with(['harga_tiket' => function($query) {
                $query->join('jenis_penumpang as jp', 'jadwal_jenispenumpang.id_jenis_penumpang', 'jp.id')
                    ->select('jadwal_jenispenumpang.*', 'jp.tipe as tipe_penumpang', 'jp.jenis as jenis_penumpang');
            }])
            ->join('kapal as k', 'jadwal_tiket.id_kapal', 'k.id')
            ->join('nahkoda as n', 'jadwal_tiket.id_nahkoda', 'n.id')
            ->join('rute as r', 'jadwal_tiket.id_rute', 'r.id')
            ->select('jadwal_tiket.*', 'k.nama_kapal', 'n.nama_nahkoda')
            ->find($id);

        return $data;
    }

    private function updateHarga($id, $arrHarga)
    {
        $newData = array();
        foreach ($arrHarga as $h) {
            $data = JadwalJenispenumpang::where('id_jadwal', $id)
                        ->where('id_jenis_penumpang', $h['id_jenis_penumpang'])
                        ->first();
            if (is_null($data)) {
                array_push($newData, $h);
            } else {
                $data->harga = $h['harga'];
                $data->save();
            }
        }
        $newData = array_map(function ($e) use ($id) {
            $e['id_jadwal'] = $id;
            $e['created_at'] = date('Y-m-d H:i:s');
            $e['updated_at'] = date('Y-m-d H:i:s');
            return $e;
        }, $newData);
        JadwalJenispenumpang::insert($newData);
        $deleteId = array_map(function ($e) { return $e['id_jenis_penumpang']; }, $arrHarga);
        JadwalJenispenumpang::where('id_jadwal', $id)->whereNotIn('id_jenis_penumpang', $deleteId)->delete();
    }

    public function index(Request $request)
    {
        $data = JadwalTiket::with(['rute' => function($query) {
            $query->join('dermaga as d1', 'rute.id_dermaga_awal', 'd1.id')
                ->join('dermaga as d2', 'rute.id_dermaga_tujuan', 'd2.id')
                ->select('rute.id', 'rute.nama_rute', 'rute.id_dermaga_awal', 'rute.id_dermaga_tujuan', 'd1.nama_dermaga as nama_dermaga_awal', 'd2.nama_dermaga as nama_dermaga_tujuan');
            }])->with(['harga_tiket' => function($query) {
                $query->join('jenis_penumpang as jp', 'jadwal_jenispenumpang.id_jenis_penumpang', 'jp.id')
                    ->select('jadwal_jenispenumpang.*', 'jp.tipe as tipe_penumpang', 'jp.jenis as jenis_penumpang')
                    ->get();
            }])
            ->join('kapal as k', 'jadwal_tiket.id_kapal', 'k.id')
            ->join('nahkoda as n', 'jadwal_tiket.id_nahkoda', 'n.id')
            ->join('rute as r', 'jadwal_tiket.id_rute', 'r.id')
            ->select('jadwal_tiket.*', 'k.nama_kapal', 'n.nama_nahkoda')
            ->whereNull('jadwal_tiket.deleted_at')->where('r.status_rute', 1);
        if (isset($request->nama)) {
            $data = $data->where('jadwal_tiket.jenis_jadwal', 'like', '%'.$request->nama.'%');
        }
        if (isset($request->status)) {
            $data = $data->where('jadwal_tiket.status_jadwal', $request->status);
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
        $data = $this->getSingleWithChild($id);
        if (!is_null($data)) {
            return response()->json([
                'data' => $data,
            ], 200);
        }
        return response()->json([
            'message' => 'Jadwal tiket tidak ditemukan.',
        ], 404);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'jenis_jadwal' => 'required|string|max:100',
            'id_kapal' => 'required',
            'id_nahkoda' => 'required',
            'id_rute' => 'required',
            'id_armada' => 'required',
            'waktu_berangkat' => 'required|string|max:5',
            'id_loket' => 'required|integer',
            'harga_tiket' => 'required|array',
            'status_jadwal' => 'required|integer|min:0|max:1',
            'id' => 'required'
        ]);
        if($validator->fails()) {
            return response()->json(['error'=>$validator->errors()], 400);
        }
        
        $data = $validator->validated();
        foreach ($data['harga_tiket'] as $h) {
            $validator2 = Validator::make($h, [
                'id_jenis_penumpang' => 'required|integer',
                'harga' => 'required|decimal:0,10',
            ]);
            if($validator2->fails()) {
                return response()->json(['error'=>$validator->errors()], 400);
            }
        }
        $arrHarga = $data['harga_tiket'];
        unset($data['harga_tiket']);
        $jadwal = new JadwalTiket;
        $jadwal->id = $data['id'];
        $jadwal->id_jadwal = $data['id'];
        $jadwal->jenis_jadwal = $data['jenis_jadwal'];
        $jadwal->id_kapal = $data['id_kapal'];
        $jadwal->id_nahkoda = $data['id_nahkoda'];
        $jadwal->id_rute = $data['id_rute'];
        $jadwal->id_armada = $data['id_armada'];
        $jadwal->waktu_berangkat = $data['waktu_berangkat'];
        $jadwal->id_loket = $data['id_loket'];
        $jadwal->status_jadwal = $data['status_jadwal'];
        $jadwal->save();
        $hargas = array_map(function ($e) use ($jadwal) {
            $e['id_jadwal'] = $jadwal->id_jadwal;
            $e['created_at'] = date('Y-m-d H:i:s');
            $e['updated_at'] = date('Y-m-d H:i:s');
            return $e;
        }, $arrHarga);
        JadwalJenispenumpang::insert($hargas);
        Log::channel('single')->info('Sukses menambahkan jadwal dan harga tiket oleh '.auth()->user()->name.'.', $validator->validated());
        return response()->json($this->getSingleWithChild($jadwal->id), 201);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'jenis_jadwal' => 'required|string|max:100',
            'id_kapal' => 'required',
            'id_nahkoda' => 'required',
            'id_rute' => 'required|integer',
            'waktu_berangkat' => 'required|string|max:5',
            'id_loket' => 'required|integer',
            'status_jadwal' => 'required|integer|min:0|max:1',
            'harga_tiket' => 'required|array',
        ]);
        if($validator->fails()) {          
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        $data = $validator->validated();
        foreach ($data['harga_tiket'] as $h) {
            $validator2 = Validator::make($h, [
                'id_jenis_penumpang' => 'required|integer',
                'harga' => 'required|decimal:0,10',
            ]);
            if($validator2->fails()) {
                return response()->json(['error'=>$validator->errors()], 400);
            }
        }
        $id = $data['id'];
        $arrHarga = $data['harga_tiket'];
        unset($data['id']);
        unset($data['harga_tiket']);
        JadwalTiket::where('id', $id)->update($data);
        $this->updateHarga($id, $arrHarga);
        Log::channel('single')->info('Sukses mengupdate jadwal dan harga tiket id '.$request->id.' oleh '.auth()->user()->name.'.', $validator->validated());

        return response()->json($this->getSingleWithChild($id), 201);
    }

    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ]);
        if($validator->fails()) {          
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        JadwalTiket::where('id', $request->id)->delete();
        JadwalJenispenumpang::where('id_jadwal', $request->id)->delete();
        Log::channel('single')->info('Sukses menghapus jadwal dan harga tiket id '.$request->id.' oleh '.auth()->user()->name.'.');

        return response()->json(["message" => "Jadwal tiket berhasil dihapus."], 201);
    }

    public function setImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'integer',
            'image' => 'required|max:2048|mimes:jpg,png,svg'
        ]);
        if($validator->fails()) {
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        if (!isset($request['id']) or $request['id'] == 0) {
            $jadwal = JadwalTiket::orderBy('id', 'desc')->first();
        } else {
            $jadwal = JadwalTiket::find($request['id']);
        }
        // DELETE OLD IMAGE FROM STORAGE
        if (!is_null($jadwal->image)) {
            $temp = explode("/", $jadwal->image);
            $jadwalImageOld = $temp[count($temp)-1];
            if (Storage::disk('public')->exists('image_jadwal/'.$jadwalImageOld)) {
                Storage::disk('public')->delete('image_jadwal/'.$jadwalImageOld);
            }
        }
        // SAVE NEW IMAGE TO STORAGE
        $appUrl = is_null(env('APP_URL')) ? getenv('APP_URL') : env('APP_URL');
        if ($file = $request->file('image')) {
            $file->store('public/image_jadwal');
            $jadwal->image = $appUrl."/storage/image_jadwal/".$file->hashName();
        }
        $jadwal->save();
        Log::channel('single')->info('Sukses menambahkan image pada jadwal oleh '.auth()->user()->name, $jadwal->toArray());

        return response()->json([
            'message' => 'Set image jadwal berhasil.'
        ], 201);
    }
}
