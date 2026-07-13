<?php

namespace App\Utils;

use App\Restaurant\Booking;
use App\Transaction;
use App\TransactionSellLine;
use App\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class RestaurantUtil extends Util
{
    /**
     * Retrieves all orders/sales
     *
     * @param  int  $business_uid
     * @param  array  $filter
     * *For new orders order_status is 'received'
     * @return obj $orders
     */
    public function getAllOrders($business_uid, $filter = [])
    {
        $query = Transaction::leftJoin('contacts', 'transactions.contact_id', '=', 'contacts.uid')
                ->leftjoin(
                    'business_locations AS bl',
                    'transactions.location_uid',
                    '=',
                    'bl.uid'
                )
                ->leftjoin(
                    'res_tables AS rt',
                    'transactions.res_table_id',
                    '=',
                    'rt.uid'
                )
                ->where('transactions.business_uid', $business_uid)
                ->where('transactions.type', 'sell')
                ->where('transactions.status', 'final');
        // ->where('transactions.res_order_status', '!=' ,'served');

        if (empty($filter['order_status'])) {
            $query->where(function ($q) {
                $q->where('res_order_status', '!=', 'served')
                ->orWhereNull('res_order_status');
            });
        }

        //For new orders order_status is 'received'
        if (! empty($filter['order_status']) && $filter['order_status'] == 'received') {
            $query->whereNull('res_order_status');
        }

        if (! empty($filter['line_order_status'])) {
            if ($filter['line_order_status'] == 'received') {
                $query->whereHas('sell_lines', function ($q) {
                    $q->whereNull('res_line_order_status')
                      ->orWhere('res_line_order_status', 'received');
                }, '>=', 1);
            }

            if ($filter['line_order_status'] == 'cooked') {
                $query->whereHas('sell_lines', function ($q) {
                    $q->where('res_line_order_status', '!=', 'cooked');
                }, '=', 0);
            }

            if ($filter['line_order_status'] == 'served') {
                $query->whereHas('sell_lines', function ($q) {
                    $q->where('res_line_order_status', '!=', 'served');
                }, '=', 0);
            }
        }

        if (! empty($filter['waiter_id'])) {
            $query->where('transactions.res_waiter_id', $filter['waiter_id']);
        }

        //  for kitchen order
        if (! empty($filter['is_kitchen_order']) && $filter['is_kitchen_order'] == 1) {
            $query->where('is_kitchen_order', 1);
        }

        $permitted_locations = auth()->user()->permitted_locations();
        if ($permitted_locations != 'all') {
            $query->whereIn('transactions.location_uid', $permitted_locations);
        }

        $orders = $query->select(
            'transactions.*',
            'contacts.name as customer_name',
            'bl.name as business_location',
            'rt.name as table_name'
        )->with(['sell_lines'])
                ->orderBy('created_at', 'desc')
                ->get();

        return $orders;
    }

    public function service_staff_dropdown($business_uid)
    {
        //Get all service staff roles
        $service_staff_roles = Role::where('business_uid', $business_uid)
                                ->where('is_service_staff', 1)
                                ->get()
                                ->pluck('name')
                                ->toArray();

        $service_staff = [];

        //Get all users of service staff roles
        if (! empty($service_staff_roles)) {
            $service_staff = User::where('business_uid', $business_uid)->role($service_staff_roles)->get()->pluck('first_name', 'id');
        }

        return $service_staff;
    }

    public function is_service_staff($user_uid)
    {
        $is_service_staff = false;
        $user = User::find($user_uid);
        if ($user->roles->first()->is_service_staff == 1) {
            $is_service_staff = true;
        }

        return $is_service_staff;
    }

    /**
     * Retrieves line orders/sales
     *
     * @param  int  $business_uid
     * @param  array  $filter
     * *For new orders order_status is 'received'
     * @return obj $orders
     */
    public function getLineOrders($business_uid, $filter = [])
    {
        $query = TransactionSellLine::with(['modifiers', 'modifiers.product', 'modifiers.variations'])
                ->leftJoin('transactions as t', 't.uid', '=', 'transaction_sell_lines.transaction_uid')
                ->leftJoin('contacts as c', 't.contact_id', '=', 'c.uid')
                ->leftJoin('variations as v', 'transaction_sell_lines.variation_uid', '=', 'v.uid')
                ->leftJoin('products as p', 'v.product_uid', '=', 'p.uid')
                ->leftJoin('units as u', 'p.unit_uid', '=', 'u.uid')
                ->leftJoin('product_variations as pv', 'v.product_variation_id', '=', 'pv.uid')
                ->leftJoin('users as line_service_staff', 'transaction_sell_lines.res_service_staff_id', '=', 'line_service_staff.uid')
                ->leftjoin(
                    'business_locations AS bl',
                    't.location_uid',
                    '=',
                    'bl.uid'
                )
                ->leftjoin(
                    'res_tables AS rt',
                    't.res_table_id',
                    '=',
                    'rt.uid'
                )
                ->where('t.business_uid', $business_uid)
                ->where('t.type', 'sell')
                ->where('t.status', 'final');

        if (empty($filter['order_status'])) {
            $query->where(function ($q) {
                $q->where('res_line_order_status', '!=', 'served')
                ->orWhereNull('res_line_order_status');
            });
        }

        if (! empty($filter['waiter_id'])) {
            $query->where('transaction_sell_lines.res_service_staff_id', $filter['waiter_id']);
        }

        if (! empty($filter['line_id'])) {
            $query->where('transaction_sell_lines.uid', $filter['line_id']);
        }

        $permitted_locations = auth()->user()->permitted_locations();
        if ($permitted_locations != 'all') {
            $query->whereIn('t.location_uid', $permitted_locations);
        }

        $orders = $query->select(
            'p.name as product_name',
            'p.type as product_type',
            'v.name as variation_name',
            'pv.name as product_variation_name',
            't.uid as transaction_uid',
            'c.name as customer_name',
            'bl.name as business_location',
            'rt.name as table_name',
            't.created_at',
            't.invoice_no',
            'transaction_sell_lines.quantity',
            'transaction_sell_lines.sell_line_note',
            'transaction_sell_lines.res_line_order_status',
            'u.short_name as unit',
            'transaction_sell_lines.uid',
            DB::raw("CONCAT(COALESCE(line_service_staff.surname, ''),' ',COALESCE(line_service_staff.first_name, ''),' ',COALESCE(line_service_staff.last_name,'')) as service_staff_name")
        )
                ->orderBy('created_at', 'desc')
                ->get();

        return $orders;
    }

    /**
     * Function to show booking events on a calendar
     *
     * @param  array  $filters
     * @return array
     */
    public function getBookingsForCalendar($filters)
    {
        $start_date = request()->start;
        $end_date = request()->end;
        $query = Booking::where('business_uid', $filters['business_uid'])
                        ->whereBetween(DB::raw('date(booking_start)'), [$filters['start_date'], $filters['end_date']])
                        ->with(['customer', 'table']);

        if (! empty($filters['user_uid'])) {
            $query->where('created_by_uid', $filters['user_uid']);

            $query->where(function ($q) use ($filters) {
                $q->where('created_by_uid', $filters['user_uid'])
                    ->orWhere('correspondent_id', $filters['user_uid'])
                    ->orWhere('waiter_id', $filters['user_uid']);
            });
        }

        if (! empty($filters['location_uid'])) {
            $query->where('bookings.location_uid', $filters['location_uid']);
        }
        $bookings = $query->get();

        $events = [];

        foreach ($bookings as $booking) {

            //Skip event if customer not found
            if (empty($booking->customer)) {
                continue;
            }

            $customer_name = $booking->customer->name;
            $table_name = $booking->table?->name;

            $backgroundColor = '#3c8dbc';
            $borderColor = '#3c8dbc';
            if ($booking->booking_status == 'completed') {
                $backgroundColor = '#00a65a';
                $borderColor = '#00a65a';
            } elseif ($booking->booking_status == 'cancelled') {
                $backgroundColor = '#f56954';
                $borderColor = '#f56954';
            } elseif ($booking->booking_status == 'waiting') {
                $backgroundColor = '#FFAD46';
                $borderColor = '#FFAD46';
            }
            if (! empty($filters['color'])) {
                $backgroundColor = $filters['color'];
                $borderColor = $filters['color'];
            }
            $title = $customer_name;
            if (! empty($table_name)) {
                $title .= ' - '.$table_name;
            }
            $events[] = [
                'title' => $title,
                'title_html' => $customer_name.'<br>'.$table_name,
                'start' => $booking->booking_start,
                'end' => $booking->booking_end,
                'customer_name' => $customer_name,
                'table' => $table_name,
                'url' => action([\App\Http\Controllers\Restaurant\BookingController::class, 'show'], [$booking->id]),
                'event_url' => action([\App\Http\Controllers\Restaurant\BookingController::class, 'index']),
                // 'start_time' => $start_time,
                // 'end_time' =>  $end_time,
                'backgroundColor' => $backgroundColor,
                'borderColor' => $borderColor,
                'allDay' => false,
                'event_type' => 'bookings',
            ];
        }

        return $events;
    }
}
