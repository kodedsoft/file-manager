<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    //

    protected $fillable = [
        'unique_key',
        'title',
        'description',
        'piece_price',
        'size',
        'style',
        'color_name',
        'sanmar_mainframe_color',
    ];

    protected $casts = [
        'piece_price' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
