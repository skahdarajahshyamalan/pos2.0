<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;
use App\Traits\HasUids;

class Permission extends SpatiePermission
{
    use HasUids;

    protected $primaryKey = 'uid';
    
    public $incrementing = false;
    
    protected $keyType = 'string';
}
