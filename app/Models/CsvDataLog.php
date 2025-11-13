<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CsvDataLog extends Model
{
    //
    protected $table = 'csv_data_log';
    
    protected $fillable = [
        'filename',
        'data',
        'created_by',
    ];

    protected $casts = [
        'data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
