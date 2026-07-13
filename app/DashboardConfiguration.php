<?php

namespace App;

use App\Traits\HasUids;

use Illuminate\Database\Eloquent\Model;

class DashboardConfiguration extends Model
{
    use HasUids;
    protected $primaryKey = 'uid';

}
