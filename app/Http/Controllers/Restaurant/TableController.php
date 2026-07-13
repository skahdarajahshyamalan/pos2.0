<?php

namespace App\Http\Controllers\Restaurant;

use App\BusinessLocation;
use App\Restaurant\ResTable;
use Datatables;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class TableController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        if (! auth()->user()->can('access_tables')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_uid = request()->session()->get('user.business_uid');

            $tables = ResTable::where('res_tables.business_uid', $business_uid)
                        ->join('business_locations AS BL', 'res_tables.location_uid', '=', 'BL.id')
                        ->select(['res_tables.name as name', 'BL.name as location',
                            'res_tables.description', 'res_tables.id', ]);

            return Datatables::of($tables)
                ->addColumn(
                    'action',
                    '@role("Admin#'.$business_uid.'")
                    <button data-href="{{action(\'App\Http\Controllers\Restaurant\TableController@edit\', [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary edit_table_button"><i class="glyphicon glyphicon-edit"></i> @lang("messages.edit")</button>
                        &nbsp;
                    @endrole
                    @role("Admin#'.$business_uid.'")
                        <button data-href="{{action(\'App\Http\Controllers\Restaurant\TableController@destroy\', [$id])}}" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete_table_button"><i class="glyphicon glyphicon-trash"></i> @lang("messages.delete")</button>
                    @endrole'
                )
                ->removeColumn('id')
                ->escapeColumns(['action'])
                ->make(true);
        }

        return view('restaurant.table.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        if (! auth()->user()->can('access_tables')) {
            abort(403, 'Unauthorized action.');
        }

        $business_uid = request()->session()->get('user.business_uid');
        $business_locations = BusinessLocation::forDropdown($business_uid);

        return view('restaurant.table.create')
            ->with(compact('business_locations'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('access_tables')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['name', 'description', 'location_uid']);
            $business_uid = $request->session()->get('user.business_uid');
            $input['business_uid'] = $business_uid;
            $input['created_by_uid'] = $request->session()->get('user.id');

            $table = ResTable::create($input);
            $output = ['success' => true,
                'data' => $table,
                'msg' => __('lang_v1.added_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Show the specified resource.
     *
     * @return Response
     */
    public function show()
    {
        if (! auth()->user()->can('access_tables')) {
            abort(403, 'Unauthorized action.');
        }

        return view('restaurant.table.show');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit($id)
    {
        if (! auth()->user()->can('access_tables')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_uid = request()->session()->get('user.business_uid');
            $table = ResTable::where('business_uid', $business_uid)->find($id);

            return view('restaurant.table.edit')
                ->with(compact('table'));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('access_tables')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $input = $request->only(['name', 'description']);
                $business_uid = $request->session()->get('user.business_uid');

                $table = ResTable::where('business_uid', $business_uid)->findOrFail($id);
                $table->name = $input['name'];
                $table->description = $input['description'];
                $table->save();

                $output = ['success' => true,
                    'msg' => __('lang_v1.updated_success'),
                ];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return Response
     */
    public function destroy($id)
    {
        if (! auth()->user()->can('access_tables')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_uid = request()->user()->business_uid;

                $table = ResTable::where('business_uid', $business_uid)->findOrFail($id);
                $table->delete();

                $output = ['success' => true,
                    'msg' => __('lang_v1.deleted_success'),
                ];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }
}
