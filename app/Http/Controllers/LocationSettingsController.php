<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\InvoiceLayout;
use App\InvoiceScheme;
use App\Printer;
use Illuminate\Http\Request;

class LocationSettingsController extends Controller
{
    /**
     * All class instance.
     */
    protected $printReceiptOnInvoice;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->printReceiptOnInvoice = ['1' => __('messages.yes'), '0' => __('messages.no')];
        $this->receiptPrinterType = ['browser' => __('lang_v1.browser_based_printing'), 'printer' => __('lang_v1.configured_printer')];
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($location_uid)
    {
        //Check for locations access permission
        if (! auth()->user()->can('business_settings.access') ||
            ! auth()->user()->can_access_this_location($location_uid)
        ) {
            abort(403, 'Unauthorized action.');
        }

        $business_uid = request()->session()->get('user.business_uid');

        $location = BusinessLocation::where('business_uid', $business_uid)
                        ->findorfail($location_uid);

        $printers = Printer::forDropdown($business_uid);

        $printReceiptOnInvoice = $this->printReceiptOnInvoice;
        $receiptPrinterType = $this->receiptPrinterType;

        $invoice_layouts = InvoiceLayout::where('business_uid', $business_uid)
                            ->get()
                            ->pluck('name', 'id');
        $invoice_schemes = InvoiceScheme::where('business_uid', $business_uid)
                            ->get()
                            ->pluck('name', 'id');

        return view('location_settings.index')
            ->with(compact('location', 'printReceiptOnInvoice', 'receiptPrinterType', 'printers', 'invoice_layouts', 'invoice_schemes'));
    }

    /**
     * Update the settings
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function updateSettings($location_uid, Request $request)
    {
        try {
            //Check for locations access permission
            if (! auth()->user()->can('business_settings.access') ||
                ! auth()->user()->can_access_this_location($location_uid)
            ) {
                abort(403, 'Unauthorized action.');
            }

            $input = $request->only(['print_receipt_on_invoice', 'receipt_printer_type', 'printer_uid', 'invoice_layout_uid', 'invoice_scheme_uid']);

            //Auto set to browser in demo.
            if (config('app.env') == 'demo') {
                $input['receipt_printer_type'] = 'browser';
            }

            $business_uid = request()->session()->get('user.business_uid');

            $location = BusinessLocation::where('business_uid', $business_uid)
                            ->findorfail($location_uid);

            $location->fill($input);
            $location->update();

            $output = ['success' => 1,
                'msg' => __('receipt.receipt_settings_updated'),
            ];
        } catch (\Exception $e) {
            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return back()->with('status', $output);
    }
}
