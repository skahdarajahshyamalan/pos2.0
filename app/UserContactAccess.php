<?php

namespace App;

use App\Traits\HasUids;

use Illuminate\Database\Eloquent\Model;

class UserContactAccess extends Model
{
    use HasUids;
    protected $primaryKey = 'uid';

    //
}
