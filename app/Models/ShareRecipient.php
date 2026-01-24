<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShareRecipient extends Model
{
    protected $fillable = [
        'share_id',
        'email',
        'last_emailed_at',
    ];

    protected $casts = [
        'last_emailed_at' => 'datetime',
    ];

    public function share()
    {
        return $this->belongsTo(Share::class);
    }
}
