<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    protected $fillable = [
        'name',
        'size',
        'type',
        'share_id',
        'temp_path'
    ];

    public function share()
    {
        return $this->belongsTo(Share::class);
    }

    public function user()
    {
        return $this->share->user();
    }

}
