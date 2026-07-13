<?php

namespace App\Listeners;

use App\AccountTransaction;
use App\Utils\ModuleUtil;
use App\Utils\TransactionUtil;

class DeleteAccountTransaction
{
    protected $transactionUtil;

    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param  TransactionUtil  $transactionUtil
     * @return void
     */
    public function __construct(TransactionUtil $transactionUtil, ModuleUtil $moduleUtil)
    {
        $this->transactionUtil = $transactionUtil;
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        //Add contact advance if exists
        if ($event->transactionPayment->method == 'advance') {
            $this->transactionUtil->updateContactBalance($event->transactionPayment->payment_for, $event->transactionPayment->amount);
        }

        if (! $this->moduleUtil->isModuleEnabled('account')) {
            return true;
        }

        AccountTransaction::where('account_uid', $event->transactionPayment->account_uid)
                        ->where('transaction_payment_uid', $event->transactionPayment->id)
                        ->delete();
    }
}
