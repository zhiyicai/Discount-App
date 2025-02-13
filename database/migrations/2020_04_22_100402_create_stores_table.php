<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStoresTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create('stores', function (Blueprint $table) {
            $table->id()->index();
            $table->string('store_id')->nullable();
            $table->string('name')->nullable();
            $table->string('access_token')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('permanent_domain')->nullable();
            $table->string('support_email')->nullable();
            $table->string('location_id')->nullable();
            $table->string('currency')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists('stores');
    }
}
