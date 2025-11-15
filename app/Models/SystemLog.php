<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    protected $fillable = [
        'level',
        'category',
        'message',
        'context',
        'user_id',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Log levels
     */
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';

    /**
     * Helper method to create a log entry
     */
    public static function log(string $level, string $message, ?string $category = null, ?array $context = null): self
    {
        return self::create([
            'level' => $level,
            'category' => $category,
            'message' => $message,
            'context' => $context,
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Log a model's data as JSON
     */
    public static function logModel(string $level, string $message, Model $model, ?string $category = null, ?array $additionalContext = []): self
    {
        $context = array_merge([
            'model' => get_class($model),
            'model_id' => $model->getKey(),
            'model_data' => $model->toArray(),
        ], $additionalContext);

        return self::log($level, $message, $category, $context);
    }
}
