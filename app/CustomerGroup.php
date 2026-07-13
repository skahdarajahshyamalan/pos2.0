<?php

namespace App;

use App\Traits\HasUids;

use Illuminate\Database\Eloquent\Model;

class CustomerGroup extends Model
{
    use HasUids;
    protected $primaryKey = 'uid';

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Return list of customer group for a business
     *
     * @param $business_uid int
     * @param $prepend_none = true (boolean)
     * @param $prepend_all = false (boolean)
     * @return array
     */
    public static function forDropdown($business_uid, $prepend_none = true, $prepend_all = false)
    {
        $all_cg = CustomerGroup::where('business_uid', $business_uid);
        $all_cg = $all_cg->pluck('name', 'uid');

        //Prepend none
        if ($prepend_none) {
            $all_cg = $all_cg->prepend(__('lang_v1.none'), '');
        }

        //Prepend none
        if ($prepend_all) {
            $all_cg = $all_cg->prepend(__('report.all'), '');
        }

        return $all_cg;
    }
}
