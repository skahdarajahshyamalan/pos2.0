<?php

namespace App;

use App\Traits\HasUids;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SellingPriceGroup extends Model
{
    use HasUids;
    protected $primaryKey = 'uid';

    use SoftDeletes;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    public function scopeActive($query)
    {
        return $query->where('selling_price_groups.is_active', 1);
    }

    /**
     * Return list of selling price groups
     *
     * @param  int  $business_uid
     * @return array
     */
    public static function forDropdown($business_uid, $with_default = true)
    {
        $price_groups = SellingPriceGroup::where('business_uid', $business_uid)
                                    ->active()
                                    ->get();

        $dropdown = [];

        if ($with_default && auth()->user()->can('access_default_selling_price')) {
            $dropdown[0] = __('lang_v1.default_selling_price');
        }

        foreach ($price_groups as $price_group) {
            if (auth()->user()->can('selling_price_group.'.$price_group->id)) {
                $dropdown[$price_group->id] = $price_group->name;
            }
        }

        return $dropdown;
    }

    /**
     * Counts total number of selling price groups
     *
     * @param  int  $business_uid
     * @return array
     */
    public static function countSellingPriceGroups($business_uid)
    {
        $count = SellingPriceGroup::where('business_uid', $business_uid)
                                ->active()
                                ->count();

        return $count;
    }
}
