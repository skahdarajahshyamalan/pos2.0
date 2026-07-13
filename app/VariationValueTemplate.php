<?php

namespace App;

use App\Traits\HasUids;

use Illuminate\Database\Eloquent\Model;

class VariationValueTemplate extends Model
{
    use HasUids;
    protected $primaryKey = 'uid';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Get the variation that owns the attribute.
     */
    public function variationTemplate()
    {
        return $this->belongsTo(\App\VariationTemplate::class);
    }
}
