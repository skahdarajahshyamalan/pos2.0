<?php

namespace App;

use App\Traits\HasUids;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasUids;
    protected $primaryKey = 'uid';

    //
}
