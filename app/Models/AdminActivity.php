<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminActivity extends Model
{
    //

    protected $table = 'admin_activity';

    protected $fillable = [
        'admin_id',
        'action',
        'total_actions',
        'last_login',
    ];
}
