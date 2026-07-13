<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\PurchaseLine;
use App\TaxRate;
use App\Transaction;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CombinedPurchaseReturnController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $productUtil;

    protected $moduleUtil;

    protected $transactionUtil;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(ProductUtil $productUtil, ModuleUtil $moduleUtil, TransactionUtil $transactionUtil)
    {
        $this->productUtil = $productUtil;
        $this->moduleUtil = $moduleUtil;
        $this->transactionUtil = $transactionUtil;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! auth()->user()->can('purchase.update')) {
            abort(403, 'Unauthorized action.');
        }

        $business_uid = request()->session()->get('user.business_uid');

        //Check if subscribed or not
        if (! $this->moduleUtil->isSubscribed($business_uid)) {
            return $this->moduleUtil->expiredResponse();
        }

        $business_locations = BusinessLocation::forDropdown($business_uid);

        $taxes = TaxRate::where('business_uid', $business_uid)
                        ->ExcludeForTaxGroup()
                        ->get();

        return view('purchase_return.create')
            ->with(compact('business_locations', 'taxes'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function save(Request $request)
    {
        if (! auth()->user()->can('purchase.update')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            DB::beginTransaction();

            $input_data = $request->only(['location_uid', 'transaction_date', 'final_total', 'ref_no',
                'tax_uid', 'tax_amount', 'contact_uid', ]);
            $business_uid = $request->session()->get('user.business_uid');

            //Check if subscribed or not
            if (! $this->moduleUtil->isSubscribed($business_uid)) {
                return $this->moduleUtil->expiredResponse();
            }

            $user_uid = $request->session()->get('user.uid');

            $input_data['type'] = 'purchase_return';
            $input_data['business_uid'] = $business_uid;
            $input_data['created_by_uid'] = $user_uid;
            $input_data['transaction_date'] = $this->productUtil->uf_date($input_data['transaction_date'], true);
            $input_data['total_before_tax'] = $input_data['final_total'] - $input_data['tax_amount'];

            //Update reference count
            $ref_count = $this->productUtil->setAndGetReferenceCount('purchase_return');
            //Generate reference number
            if (empty($input_data['ref_no'])) {
                $input_data['ref_no'] = $this->productUtil->generateReferenceNumber('purchase_return', $ref_count);
            }

            //upload document
            $input_data['document'] = $this->productUtil->uploadFile($request, 'document', 'documents');

            $products = $request->input('products');

            if (! empty($products)) {
                $product_data = [];

                foreach ($products as $product) {
                    $unit_price = $this->productUtil->num_uf($product['unit_price']);
                    $return_line = [
                        'product_uid' => $product['product_uid'],
                        'variation_uid' => $product['variation_uid'],
                        'quantity' => 0,
                        'purchase_price' => $unit_price,
                        'pp_without_discount' => $unit_price,
                        'purchase_price_inc_tax' => $unit_price,
                        'quantity_returned' => $this->productUtil->num_uf($product['quantity']),
                        'lot_number' => ! empty($product['lot_number']) ? $product['lot_number'] : null,
                        'exp_date' => ! empty($product['exp_date']) ? $this->productUtil->uf_date($product['exp_date']) : null,
                    ];

                    $product_data[] = $return_line;

                    //Decrease available quantity
                    $this->productUtil->decreaseProductQuantity(
                        $product['product_uid'],
                        $product['variation_uid'],
                        $input_data['location_uid'],
                        $this->productUtil->num_uf($product['quantity'])
                    );
                }

                $purchase_return = Transaction::create($input_data);
                $purchase_return->purchase_lines()->createMany($product_data);

                //update payment status
                $this->transactionUtil->updatePaymentStatus($purchase_return->id, $purchase_return->final_total);
            }

            $output = ['success' => 1,
                'msg' => __('lang_v1.purchase_return_added_success'),
            ];

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect('purchase-return')->with('status', $output);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! auth()->user()->can('purchase.update')) {
            abort(403, 'Unauthorized action.');
        }

        $business_uid = request()->session()->get('user.business_uid');

        $purchase_return = Transaction::where('business_uid', $business_uid)
                                    ->with(['contact'])
                                    ->find($id);
        $location_uid = $purchase_return->location_uid;
        $purchase_lines = PurchaseLine::join(
                            'products AS p',
                            'purchase_lines.product_uid',
                            '=',
                            'p.uid'
                        )
                        ->join(
                            'variations AS variations',
                            'purchase_lines.variation_uid',
                            '=',
                            'variations.uid'
                        )
                        ->join(
                            'product_variations AS pv',
                            'variations.product_variation_uid',
                            '=',
                            'pv.uid'
                        )
                        ->leftjoin('variation_location_details AS vld', function ($join) use ($location_uid) {
                            $join->on('variations.uid', '=', 'vld.variation_uid')
                                ->where('vld.location_uid', '=', $location_uid);
                        })
                        ->leftjoin('units', 'units.uid', '=', 'p.unit_uid')
                        ->where('purchase_lines.transaction_uid', $id)
                        ->select(
                            DB::raw("IF(pv.is_dummy = 0, CONCAT(p.name, 
                                    ' (', pv.name, ':',variations.name, ')'), p.name) AS product_name"),
                            'p.uid as product_uid',
                            'p.enable_stock',
                            'pv.is_dummy as is_dummy',
                            'variations.sub_sku',
                            'vld.qty_available',
                            'variations.uid as variation_uid',
                            'units.short_name as unit',
                            'units.allow_decimal as unit_allow_decimal',
                            'purchase_lines.purchase_price',
                            'purchase_lines.uid as purchase_line_uid',
                            'purchase_lines.quantity_returned as quantity_returned',
                            'purchase_lines.lot_number',
                            'purchase_lines.exp_date'
                        )
                        ->get();

        foreach ($purchase_lines as $key => $value) {
            $purchase_lines[$key]->qty_available += $value->quantity_returned;
            $purchase_lines[$key]->formatted_qty_available = $this->productUtil->num_f($purchase_lines[$key]->qty_available);
        }

        $business_locations = BusinessLocation::forDropdown($business_uid);

        $taxes = TaxRate::where('business_uid', $business_uid)
                        ->ExcludeForTaxGroup()
                        ->get();

        return view('purchase_return.edit')
            ->with(compact('business_locations', 'taxes', 'purchase_return', 'purchase_lines'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        if (! auth()->user()->can('purchase.update')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            DB::beginTransaction();

            $input_data = $request->only(['transaction_date', 'final_total',
                'tax_uid', 'tax_amount', 'contact_uid', ]);
            $business_uid = $request->session()->get('user.business_uid');

            if (! empty($request->input('ref_no'))) {
                $input_data['ref_no'] = $request->input('ref_no');
            }

            //Check if subscribed or not
            if (! $this->moduleUtil->isSubscribed($business_uid)) {
                return $this->moduleUtil->expiredResponse();
            }

            $input_data['transaction_date'] = $this->productUtil->uf_date($input_data['transaction_date'], true);
            $input_data['total_before_tax'] = $input_data['final_total'] - $input_data['tax_amount'];

            //upload document
            $doc_name = $this->productUtil->uploadFile($request, 'document', 'documents');

            if (! empty($doc_name)) {
                $input_data['document'] = $doc_name;
            }

            $products = $request->input('products');
            $purchase_return_id = $request->input('purchase_return_id');
            $purchase_return = Transaction::where('business_uid', $business_uid)
                                ->where('type', 'purchase_return')
                                ->find($purchase_return_id);

            if (! empty($products)) {
                $product_data = [];
                $updated_purchase_lines = [];

                foreach ($products as $product) {
                    $unit_price = $this->productUtil->num_uf($product['unit_price']);
                    if (! empty($product['purchase_line_uid'])) {
                        $return_line = PurchaseLine::find($product['purchase_line_uid']);
                        $updated_purchase_lines[] = $return_line->id;

                        $this->productUtil->decreaseProductQuantity(
                            $product['product_uid'],
                            $product['variation_uid'],
                            $purchase_return->location_uid,
                            $this->productUtil->num_uf($product['quantity']),
                            $return_line->quantity_returned
                        );
                    } else {
                        $return_line = new PurchaseLine([
                            'product_uid' => $product['product_uid'],
                            'variation_uid' => $product['variation_uid'],
                            'quantity' => 0,
                        ]);

                        //Decrease available quantity
                        $this->productUtil->decreaseProductQuantity(
                            $product['product_uid'],
                            $product['variation_uid'],
                            $purchase_return->location_uid,
                            $this->productUtil->num_uf($product['quantity'])
                        );
                    }
                    $return_line->purchase_price = $unit_price;
                    $return_line->pp_without_discount = $unit_price;
                    $return_line->purchase_price_inc_tax = $unit_price;
                    $return_line->quantity_returned = $this->productUtil->num_uf($product['quantity']);
                    $return_line->lot_number = ! empty($product['lot_number']) ? $product['lot_number'] : null;
                    $return_line->exp_date = ! empty($product['exp_date']) ? $this->productUtil->uf_date($product['exp_date']) : null;
                    $product_data[] = $return_line;
                }

                $purchase_return->update($input_data);

                //If purchase line deleted add return quantity to stock
                $deleted_purchase_lines = PurchaseLine::where('transaction_uid', $purchase_return_id)
                            ->whereNotIn('id', $updated_purchase_lines)
                            ->get();

                foreach ($deleted_purchase_lines as $dpl) {
                    $this->productUtil->updateProductQuantity($purchase_return->location_uid, $dpl->product_uid, $dpl->variation_uid, $dpl->quantity_returned, 0, null, false);
                }

                PurchaseLine::where('transaction_uid', $purchase_return_id)
                            ->whereNotIn('id', $updated_purchase_lines)
                            ->delete();

                $purchase_return->purchase_lines()->saveMany($product_data);

                //update payment status
                $this->transactionUtil->updatePaymentStatus($purchase_return->id, $purchase_return->final_total);
            }

            $output = ['success' => 1,
                'msg' => __('lang_v1.purchase_return_updated_success'),
            ];

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect('purchase-return')->with('status', $output);
    }

    /**
     * Return product rows
     *
     * @param  Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getProductRow(Request $request)
    {
        if (request()->ajax()) {
            $row_index = $request->input('row_index');
            $variation_uid = $request->input('variation_uid');
            $location_uid = $request->input('location_uid');

            $business_uid = $request->session()->get('user.business_uid');
            $product = $this->productUtil->getDetailsFromVariation($variation_uid, $business_uid, $location_uid);
            $product->formatted_qty_available = $this->productUtil->num_f($product->qty_available);

            return view('purchase_return.partials.product_table_row')
            ->with(compact('product', 'row_index'));
        }
    }
}
