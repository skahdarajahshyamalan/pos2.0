<?php

namespace App;

use App\Traits\HasUids;

use Illuminate\Database\Eloquent\Model;

class ProductVariation extends Model
{
    use HasUids;
    protected $primaryKey = 'uid';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    public function variations()
    {
        return $this->hasMany(\App\Variation::class);
    }

    public function variation_template()
    {
        return $this->belongsTo(\App\VariationTemplate::class);
    }
}
