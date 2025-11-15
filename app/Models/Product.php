<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\\Models\\Product
 *
 * @property int $id
 * @property string $unique_key
 * @property string|null $title
 * @property string|null $description
 * @property float|null $piece_price
 * @property string|null $size
 * @property string|null $style
 * @property string|null $color_name
 * @property string|null $sanmar_mainframe_color
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
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
