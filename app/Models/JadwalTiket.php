<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JadwalTiket extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'jadwal_tiket';
    protected $guarded = [];

    public function harga_tiket()
    {
        return $this->hasMany('App\Models\JadwalJenispenumpang', 'id_jadwal', 'id');
    }

    public function rute()
    {
        return $this->belongsTo('App\Models\Rute', 'id_rute', 'id');
    }
}
