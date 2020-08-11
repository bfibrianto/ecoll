<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCallbackLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('callback_logs', function (Blueprint $table) {
            $table->id();
            $table->string('trx_id');
            $table->string('virtual_account');
            $table->string('customer_name');
            $table->string('payment_amount');
            $table->string('cummulative_payment_amount');
            $table->string('payment_ntb');
            $table->datetime('datetime_payment');
            $table->string('datetime_payment_iso');
            $table->text('encrypted_data');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('callback_logs');
    }
}
