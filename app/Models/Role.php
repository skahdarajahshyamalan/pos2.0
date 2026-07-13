<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;
use App\Traits\HasUids;

class Role extends SpatieRole
{
    use HasUids;

    protected $primaryKey = 'uid';
    
    public $incrementing = false;
    
    protected $keyType = 'string';
}
