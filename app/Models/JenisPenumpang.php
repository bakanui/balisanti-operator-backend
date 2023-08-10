<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JenisPenumpang extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'jenis_penumpang';
    protected $guarded = [];
}
