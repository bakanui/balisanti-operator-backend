<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TiketOrdered extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'tiket_ordered';
    protected $guarded = [];
}
