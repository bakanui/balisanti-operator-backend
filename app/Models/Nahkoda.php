<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Nahkoda extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'nahkoda';
    protected $guarded = [];
}
