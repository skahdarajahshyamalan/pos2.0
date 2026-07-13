<?php

namespace App;

use App\Traits\HasUids;

use Illuminate\Database\Eloquent\Model;

class InvoiceScheme extends Model
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
     * Returns list of invoice schemes in array format
     */
    public static function forDropdown($business_uid)
    {
        $dropdown = InvoiceScheme::where('business_uid', $business_uid)
                                ->pluck('name', 'uid');

        return $dropdown;
    }

    /**
     * Retrieves the default invoice scheme
     */
    public static function getDefault($business_uid)
    {
        $default = InvoiceScheme::where('business_uid', $business_uid)
                                ->where('is_default', 1)
                                ->first();

        return $default;
    }
}
