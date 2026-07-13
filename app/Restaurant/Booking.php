<?php

namespace App\Restaurant;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    //Allowed booking statuses ('waiting', 'booked', 'completed', 'cancelled')

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    public function customer()
    {
        return $this->belongsTo(\App\Contact::class, 'contact_uid');
    }

    public function table()
    {
        return $this->belongsTo(\App\Restaurant\ResTable::class, 'table_uid');
    }

    public function correspondent()
    {
        return $this->belongsTo(\App\User::class, 'correspondent_uid');
    }

    public function waiter()
    {
        return $this->belongsTo(\App\User::class, 'waiter_uid');
    }

    public function location()
    {
        return $this->belongsTo(\App\BusinessLocation::class, 'location_uid');
    }

    public function business()
    {
        return $this->belongsTo(\App\Business::class, 'business_uid');
    }

    public static function createBooking($input)
    {
        $data = [
            'contact_uid' => $input['contact_uid'],
            'waiter_uid' => isset($input['res_waiter_uid']) ? $input['res_waiter_uid'] : null,
            'table_uid' => isset($input['res_table_uid']) ? $input['res_table_uid'] : null,
            'business_uid' => $input['business_uid'],
            'location_uid' => $input['location_uid'],
            'correspondent_uid' => isset($input['correspondent']) ? $input['correspondent'] : null,
            'booking_start' => $input['booking_start'],
            'booking_end' => $input['booking_end'],
            'created_by_uid' => $input['created_by_uid'],
            'booking_status' => isset($input['booking_status']) ? $input['booking_status'] : 'booked',
            'booking_note' => $input['booking_note'],
        ];
        $booking = Booking::create($data);

        return $booking;
    }
}
