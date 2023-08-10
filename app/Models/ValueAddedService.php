<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ValueAddedService extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'value_added_service';
    protected $guarded = [];
}
