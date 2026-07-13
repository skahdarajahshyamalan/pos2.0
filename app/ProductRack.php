<?php

namespace App;

use App\Traits\HasUids;

use Illuminate\Database\Eloquent\Model;

class ProductRack extends Model
{
    use HasUids;
    protected $primaryKey = 'uid';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];
}
