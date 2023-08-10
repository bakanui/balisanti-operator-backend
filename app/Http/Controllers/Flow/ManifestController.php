<?php

namespace App\Http\Controllers\Flow;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Manifest;

class ManifestController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['index']]);
    }
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'kode_booking' => 'required|string',
        ]);
        if($validator->fails()) {
            return response()->json(['error'=>$validator->errors()], 400);
        }
        if (auth()->check()) {
            if (auth()->user()->id_role == 3) {
                $check = Manifest::where('kode_booking', $request['kode_booking'])->first();
                if ($check) {
                    $check->user_checker = auth()->user()->name;
                    $check->status_checker = 2;
                    $check->save();
                    Log::channel('single')->info('Manifest naik kapal berhasil oleh operator ['.auth()->user()->name.'].', $validator->validated());
                    return response()->json(['message' => 'Status manifest diupdate.', 'status_manifest' => 2], 200);
                }
                Manifest::firstOrCreate([
                    'kode_booking' => $request['kode_booking'],
                ], [
                    'user_checker' => auth()->user()->name
                ]);
                Log::channel('single')->info('Manifest kedatangan berhasil oleh operator ['.auth()->user()->name.'].', $validator->validated());
                return response()->json(['message' => 'Manifest berhasil.', 'status_manifest' => 1], 200);
            }
        }
        $data = DB::table('tiket_ordered')
                    ->select('id', 'nama_penumpang', 'tanggal', 'waktu_berangkat', 'dermaga_awal', 'dermaga_tujuan')
                    ->where('kode_booking', $request['kode_booking'])
                    ->first();
        
        return response()->json(['data' => $data], 200);
    }

    public function manifestBulk(Request $request) 
    {
        $validator = Validator::make($request->all(), [
            'kode_booking' => 'required|array',
            'status_manifest' => 'required|int|min:1|max:2',
        ]);
        if($validator->fails()) {
            return response()->json(['error'=>$validator->errors()], 400);
        }
        if (auth()->user()->id_role != 3) {
            return response()->json(['message' => 'Manifest gagal. Anda bukan operator checker'], 401);
        } else if (auth()->user()->id_role == 3) {
            DB::transaction(function() use ($request) {
                foreach ($request['kode_booking'] as $k) {
                    Manifest::updateOrCreate([
                        'kode_booking' => $k,
                    ], [
                        'status_checker' => $request['status_manifest'],
                        'user_checker' => auth()->user()->name
                    ]);
                }
            });
        }
        $ket = $request['status_manifest'] == 1 ? 'kedatangan' : 'naik kapal';
        Log::channel('single')->info('Manifest '.$ket.' berhasil oleh operator ['.auth()->user()->name.'].', $validator->validated());
        
        return response()->json([
            'message' => 'Manifest bulk berhasil, keterangan manifest '.$ket.'.', 
            'status_manifest' => $request['status_manifest']
        ], 200);
    }
}
