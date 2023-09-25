<?php

namespace App\Models\Siwalatri;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Penumpang extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $connection = 'mysql2';
    protected $table = 'penumpangs';
    protected $guarded = [];
}
