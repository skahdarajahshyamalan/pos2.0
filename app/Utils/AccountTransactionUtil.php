<?php

namespace App\Utils;

use App\TaxRate;

class AccountTransactionUtil extends Util
{
    /**
     * Updates tax amount of a tax group
     *
     * @param  int  $group_tax_uid
     * @return void
     */
    public function updateGroupTaxAmount($group_tax_uid)
    {
        $amount = 0;
        $tax_rate = TaxRate::where('uid', $group_tax_uid)->with(['sub_taxes'])->first();
        foreach ($tax_rate->sub_taxes as $sub_tax) {
            $amount += $sub_tax->amount;
        }
        $tax_rate->amount = $amount;
        $tax_rate->save();
    }
}
