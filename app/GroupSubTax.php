<?php

namespace App;

use App\Traits\HasUids;

use Illuminate\Database\Eloquent\Model;

class GroupSubTax extends Model
{
    use HasUids;
    protected $primaryKey = 'uid';

    public function tax_rate()
    {
        return $this->belongsTo(\App\TaxRate::class, 'group_tax_uid');
    }
}
