<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelHasRole extends Model
{
    protected $table = 'model_has_roles';

    public $timestamps = false;

    public $incrementing = false;

    protected $guarded = [];
}

