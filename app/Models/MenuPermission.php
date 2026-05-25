<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuPermission extends Model
{
    protected $table = 'menu_permission';

    public $timestamps = false;

    public $incrementing = false;

    protected $guarded = [];
}

