<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\Galery;

class GaleryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['index']]);
    }

    public function index(Request $request)
    {
        $data = Galery::whereNotNull('id');
        if (isset($request->status)) {
            $data = $data->where('status_galery', $request->status);
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

    public function view($id)
    {
        $data = Galery::find($id);
        if (!is_null($data)) {
            return response()->json([
                'data' => $data,
            ], 200);
        }
        return response()->json([
            'message' => 'Galery tidak ditemukan.',
        ], 404);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|max:2048|mimes:jpg,png,svg',
            'status_galery' => 'required|integer|min:0|max:1',
        ]);
        if($validator->fails()) {          
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        $data = $validator->validated();
        $appUrl = is_null(env('APP_URL')) ? getenv('APP_URL') : env('APP_URL');
        if ($file = $request->file('image')) {
            $file->store('public/galery');
            $data['image'] = $appUrl."/storage/galery/".$file->hashName();
        }
        Galery::create($data);
        Log::channel('single')->info('Sukses menambahkan Galery baru oleh '.auth()->user()->name.'.', $data);

        return response()->json($data, 201);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
            'image' => 'max:2048|mimes:jpg,png,svg',
            'status_galery' => 'required|integer|min:0|max:1',
        ]);
        if($validator->fails()) {          
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        $data = $validator->validated();
        $id = $data['id'];
        unset($data['id']);
        $appUrl = is_null(env('APP_URL')) ? getenv('APP_URL') : env('APP_URL');
        if ($file = $request->file('image')) {
            $file->store('public/galery');
            $data['image'] = $appUrl."/storage/galery/".$file->hashName();
        }
        // DELETE OLD IMAGE FROM STORAGE
        $galery = Galery::find($id);
        if (!is_null($galery->image) and isset($request['image'])) {
            $temp = explode("/", $galery->image);
            $galeryImageOld = $temp[count($temp)-1];
            if (Storage::disk('public')->exists('galery/'.$galeryImageOld)) {
                Storage::disk('public')->delete('galery/'.$galeryImageOld);
            }
        }
        Galery::where('id', $id)->update($data);
        Log::channel('single')->info('Sukses mengupdate galery id '.$request->id.' oleh '.auth()->user()->name.'.', $data);

        return response()->json(Galery::find($id), 201);
    }

    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ]);
        if($validator->fails()) {          
            return response()->json(['error'=>$validator->errors()], 400);                        
        }
        $galery = Galery::find($request['id']);
        // DELETE IMAGE FROM STORAGE
        if (!is_null($galery->image)) {
            $temp = explode("/", $galery->image);
            $galeryImageOld = $temp[count($temp)-1];
            if (Storage::disk('public')->exists('galery/'.$galeryImageOld)) {
                Storage::disk('public')->delete('galery/'.$galeryImageOld);
            }
        }
        Galery::where('id', $request->id)->delete();
        Log::channel('single')->info('Sukses menghapus galery id '.$request->id.' oleh '.auth()->user()->name.'.');

        return response()->json(["message" => "Galery berhasil dihapus."], 201);
    }
}
