<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBundleCampaignsTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('bundle_campaigns', function (Blueprint $table) {
            $table->bigIncrements('id')->index();
            $table->string('campaign_id')->nullable();
            $table->string('name')->nullable();
            $table->string('buy_type')->nullable();
            $table->longText('buy_ids')->nullable();
            $table->string('get_type')->nullable();
            $table->longText('get_ids')->nullable();
            $table->string('quantity')->nullable();
            $table->string('discount_type')->nullable();
            $table->string('discount_value')->nullable();
            $table->string('customer_eligibility')->nullable();
            $table->longText('customer_ids_eligible')->nullable();
            $table->integer('max_no_of_uses')->nullable();
            $table->boolean('limit_to_one_use_per_order')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('bundle_campaigns');
    }
}
