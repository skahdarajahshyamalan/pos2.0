<?php

namespace App\Http\Controllers\Restaurant;

use App\Restaurant\ResTable;
use App\Transaction;
use App\User;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DataController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $commonUtil;

    public function __construct(Util $commonUtil)
    {
        $this->commonUtil = $commonUtil;
    }

    /**
     * Show the restaurant module related details in pos screen.
     *
     * @return \Illuminate\Http\Response
     */
    public function getPosDetails(Request $request)
    {
        if (request()->ajax()) {
            $business_uid = $request->session()->get('user.business_uid');
            $location_uid = $request->get('location_uid');
            if (! empty($location_uid)) {
                $transaction_uid = $request->get('transaction_uid', null);
                if (! empty($transaction_uid)) {
                    $transaction = Transaction::find($transaction_uid);
                    $view_data = ['res_table_id' => $transaction->res_table_id,
                        'res_waiter_id' => $transaction->res_waiter_id,
                    ];
                } else {
                    $view_data = ['res_table_id' => null, 'res_waiter_id' => null];
                }

                $waiters_enabled = false;
                $tables_enabled = false;
                $waiters = null;
                $tables = null;
                if ($this->commonUtil->isModuleEnabled('service_staff')) {
                    $waiters_enabled = true;
                    $waiters = $this->commonUtil->getServiceStaff($business_uid, $location_uid, false);
                }
                if ($this->commonUtil->isModuleEnabled('tables')) {
                    $tables_enabled = true;
                    $tables = ResTable::where('business_uid', $business_uid)
                            ->where('location_uid', $location_uid)
                            ->pluck('name', 'id');
                }
            } else {
                $tables = [];
                $waiters = [];
                $waiters_enabled = $this->commonUtil->isModuleEnabled('service_staff') ? true : false;
                $tables_enabled = $this->commonUtil->isModuleEnabled('tables') ? true : false;
                $view_data = ['res_table_id' => null, 'res_waiter_id' => null];
            }

            $pos_settings = json_decode($request->session()->get('business.pos_settings'), true);

            $is_service_staff_required = (! empty($pos_settings['is_service_staff_required']) && $pos_settings['is_service_staff_required'] == 1) ? true : false;

            return view('restaurant.partials.pos_table_dropdown')
                    ->with(compact('tables', 'waiters', 'view_data', 'waiters_enabled', 'tables_enabled', 'is_service_staff_required'));
        }
    }

    /**
     * Save the pos screen details.
     *
     * @return null
     */
    public function sellPosStore($input)
    {
        $table_id = request()->get('res_table_id');
        $res_waiter_id = request()->get('res_waiter_id');

        Transaction::where('uid', $input['transaction_uid'])
            ->where('type', 'sell')
            ->where('business_uid', $input['business_uid'])
            ->update(['res_table_id' => $table_id,
                'res_waiter_id' => $res_waiter_id, ]);
    }

    public function checkStaffPin(Request $request){
        $service_staff_pin = $request->get('service_staff_pin');
        $user_uid = $request->get('user_uid');

        $business_uid = $request->session()->get('user.business_uid');
        $query = User::where('service_staff_pin', $service_staff_pin)->where('uid', $user_uid)->where('business_uid', $business_uid);

        $exists = $query->exists();
        if ($exists) {
            echo 'true';
            exit;
        } else {
            echo 'false';
            exit;
        }
    }
}
