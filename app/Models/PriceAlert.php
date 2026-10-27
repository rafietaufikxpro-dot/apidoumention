<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceAlert extends Model
{
    protected $fillable = ['phone_number', 'target_rate', 'last_notified_at'];

    protected function casts(): array
    {
        return [
            'target_rate' => 'float',
            'last_notified_at' => 'datetime',
        ];
    }
}
