<?php

namespace App\Http\Controllers\Restaurant;

use App\BusinessLocation;
use App\Contact;
use App\CustomerGroup;
use App\Restaurant\Booking;
use App\User;
use App\Utils\RestaurantUtil;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Yajra\DataTables\Facades\DataTables;

class BookingController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $commonUtil;

    protected $restUtil;

    public function __construct(Util $commonUtil, RestaurantUtil $restUtil)
    {
        $this->commonUtil = $commonUtil;
        $this->restUtil = $restUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! auth()->user()->can('crud_all_bookings') && ! auth()->user()->can('crud_own_bookings')) {
            abort(403, 'Unauthorized action.');
        }
        $business_uid = request()->session()->get('user.business_uid');

        $user_uid = request()->has('user_uid') ? request()->user_uid : null;
        if (! auth()->user()->hasPermissionTo('crud_all_bookings') && ! $this->restUtil->is_admin(auth()->user(), $business_uid)) {
            $user_uid = request()->session()->get('user.id');
        }
        if (request()->ajax()) {
            $filters = [
                'start_date' => request()->start,
                'end_date' => request()->end,
                'user_uid' => $user_uid,
                'location_uid' => ! empty(request()->location_uid) ? request()->location_uid : null,
                'business_uid' => $business_uid,
            ];

            $events = $this->restUtil->getBookingsForCalendar($filters);

            return $events;
        }

        $business_locations = BusinessLocation::forDropdown($business_uid);

        $customers = Contact::customersDropdown($business_uid, false);

        $correspondents = User::forDropdown($business_uid, false);

        $types = Contact::getContactTypes();
        $customer_groups = CustomerGroup::forDropdown($business_uid);

        return view('restaurant.booking.index', compact('business_locations', 'customers', 'correspondents', 'types', 'customer_groups'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('crud_all_bookings') && ! auth()->user()->can('crud_own_bookings')) {
            abort(403, 'Unauthorized action.');
        }
        try {
            if ($request->ajax()) {
                $business_uid = request()->session()->get('user.business_uid');
                $user_uid = request()->session()->get('user.id');

                $input = $request->input();
                $booking_start = $this->commonUtil->uf_date($input['booking_start'], true);
                $booking_end = $this->commonUtil->uf_date($input['booking_end'], true);
                $date_range = [$booking_start, $booking_end];

                //Check if booking is available for the required input
                $query = Booking::where('business_uid', $business_uid)
                                ->where('location_uid', $input['location_uid'])
                                ->where('contact_id', $input['contact_id'])
                                ->where(function ($q) use ($date_range) {
                                    $q->whereBetween('booking_start', $date_range)
                                    ->orWhereBetween('booking_end', $date_range);
                                });

                if (isset($input['res_table_id'])) {
                    $query->where('table_id', $input['res_table_id']);
                }

                $existing_booking = $query->first();
                if (empty($existing_booking)) {
                    $input['business_uid'] = $business_uid;
                    $input['created_by_uid'] = $user_uid;
                    $input['booking_start'] = $booking_start;
                    $input['booking_end'] = $booking_end;
                    $booking = Booking::createBooking($input);

                    $output = ['success' => 1,
                        'msg' => trans('lang_v1.added_success'),
                    ];

                    //Send notification to customer
                    if (isset($input['send_notification']) && $input['send_notification'] == 1) {
                        $output['send_notification'] = 1;
                        $output['notification_url'] = action([\App\Http\Controllers\NotificationController::class, 'getTemplate'], ['transaction_uid' => $booking->id, 'template_for' => 'new_booking']);
                    }
                } else {
                    $time_range = $this->commonUtil->format_date($existing_booking->booking_start, true).' ~ '.
                                    $this->commonUtil->format_date($existing_booking->booking_end, true);

                    $output = ['success' => 0,
                        'msg' => trans(
                            'restaurant.booking_not_available',
                            ['customer_name' => $existing_booking->customer->name,
                                'booking_time_range' => $time_range, ]
                        ),
                    ];
                }
            } else {
                exit(__('messages.something_went_wrong'));
            }
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Display the specified resource.
     *
     * @param  \int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if (request()->ajax()) {
            $business_uid = request()->session()->get('user.business_uid');
            $booking = Booking::where('business_uid', $business_uid)
                                ->where('id', $id)
                                ->with(['table', 'customer', 'correspondent', 'waiter', 'location'])
                                ->first();
            if (! empty($booking)) {
                $booking_start = $this->commonUtil->format_date($booking->booking_start, true);
                $booking_end = $this->commonUtil->format_date($booking->booking_end, true);

                $booking_statuses = [
                    'waiting' => __('lang_v1.waiting'),
                    'booked' => __('restaurant.booked'),
                    'completed' => __('restaurant.completed'),
                    'cancelled' => __('restaurant.cancelled'),
                ];

                return view('restaurant.booking.show', compact('booking', 'booking_start', 'booking_end', 'booking_statuses'));
            }
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Booking  $booking
     * @return \Illuminate\Http\Response
     */
    public function edit(Booking $booking)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Booking  $booking
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('crud_all_bookings') && ! auth()->user()->can('crud_own_bookings')) {
            abort(403, 'Unauthorized action.');
        }
        try {
            $business_uid = $request->session()->get('user.business_uid');
            $booking = Booking::where('business_uid', $business_uid)
                                ->find($id);
            if (! empty($booking)) {
                $booking->booking_status = $request->booking_status;
                $booking->save();
            }

            $output = ['success' => 1,
                'msg' => trans('lang_v1.updated_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Booking  $booking
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (! auth()->user()->can('crud_all_bookings') && ! auth()->user()->can('crud_own_bookings')) {
            abort(403, 'Unauthorized action.');
        }
        try {
            $business_uid = request()->session()->get('user.business_uid');
            $booking = Booking::where('business_uid', $business_uid)
                                ->where('id', $id)
                                ->delete();
            $output = ['success' => 1,
                'msg' => trans('lang_v1.deleted_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Retrieves todays bookings
     *
     * @param  \App\Booking  $booking
     * @return \Illuminate\Http\Response
     */
    public function getTodaysBookings()
    {
        if (! auth()->user()->can('crud_all_bookings') && ! auth()->user()->can('crud_own_bookings')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_uid = request()->session()->get('user.business_uid');
            $user_uid = request()->session()->get('user.id');
            $today = \Carbon::now()->format('Y-m-d');
            $query = Booking::where('business_uid', $business_uid)
                        ->where('booking_status', 'booked')
                        ->whereDate('booking_start', $today)
                        ->with(['table', 'customer', 'correspondent', 'waiter', 'location']);

            if (! empty(request()->location_uid)) {
                $query->where('location_uid', request()->location_uid);
            }

            if (! auth()->user()->hasPermissionTo('crud_all_bookings') && ! $this->commonUtil->is_admin(auth()->user(), $business_uid)) {
                $query->where(function ($query) use ($user_uid) {
                    $query->where('created_by_uid', $user_uid)
                        ->orWhere('correspondent_id', $user_uid)
                        ->orWhere('waiter_id', $user_uid);
                });

                //$query->where('created_by_uid', $user_uid);
            }

            return Datatables::of($query)
                ->editColumn('table', function ($row) {
                    return ! empty($row->table->name) ? $row->table->name : '--';
                })
                ->editColumn('customer', function ($row) {
                    return ! empty($row->customer->name) ? $row->customer->name : '--';
                })
                ->editColumn('correspondent', function ($row) {
                    return ! empty($row->correspondent->user_full_name) ? $row->correspondent->user_full_name : '--';
                })
                ->editColumn('waiter', function ($row) {
                    return ! empty($row->waiter->user_full_name) ? $row->waiter->user_full_name : '--';
                })
                ->editColumn('location', function ($row) {
                    return ! empty($row->location->name) ? $row->location->name : '--';
                })
                ->editColumn('booking_start', function ($row) {
                    return $this->commonUtil->format_date($row->booking_start, true);
                })
                ->editColumn('booking_end', function ($row) {
                    return $this->commonUtil->format_date($row->booking_end, true);
                })
               ->removeColumn('id')
                ->make(true);
        }
    }
}
