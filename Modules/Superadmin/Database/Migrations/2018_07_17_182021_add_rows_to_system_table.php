<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddRowsToSystemTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('system')->insert(
            [
                ['uid' => uniqid('', true), 'key' => 'superadmin_version', 'value' => config('superadmin.module_version')],
                ['uid' => uniqid('', true), 'key' => 'app_currency_id', 'value' => 2],
                ['uid' => uniqid('', true), 'key' => 'invoice_business_name', 'value' => env('APP_NAME')],
                ['uid' => uniqid('', true), 'key' => 'invoice_business_landmark', 'value' => 'Landmark'],
                ['uid' => uniqid('', true), 'key' => 'invoice_business_zip', 'value' => 'Zip'],
                ['uid' => uniqid('', true), 'key' => 'invoice_business_state', 'value' => 'State'],
                ['uid' => uniqid('', true), 'key' => 'invoice_business_city', 'value' => 'City'],
                ['uid' => uniqid('', true), 'key' => 'invoice_business_country', 'value' => 'Country'],
                ['uid' => uniqid('', true), 'key' => 'email', 'value' => 'superadmin@example.com'],
            ]
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('system', function (Blueprint $table) {
        });
    }
}
