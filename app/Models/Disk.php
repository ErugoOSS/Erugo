<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Disk extends Model
{
    protected $casts = [
        'use_for_shares' => 'boolean',
        'use_path_style_endpoint' => 'boolean',
    ];
}
