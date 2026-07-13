<?php

namespace App\Http\Controllers;

class PesaPalController extends Controller
{
    //Used for pesapal payment confirmation for superadmin module.
    public function pesaPalPaymentConfirmation($transaction_uid, $status, $payment_method, $merchant_reference)
    {
        $class = new \Modules\Superadmin\Http\Controllers\PesaPalController();
        \Log::emergency('transaction_uid:'.$transaction_uid.'status:'.$status.'payment_method:'.$payment_method);

        $class->pesaPalPaymentConfirmation($transaction_uid, $status, $payment_method, $merchant_reference);
    }
}
