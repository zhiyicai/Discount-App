<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('authenticateAPI')->group(function() {
    Route::get('home', 'StoreController@index');
    Route::middleware('auth:api')->group(function () {
        Route::post('campaign/mark', 'CampaignController@markCampaigns');
        Route::resource('campaign', 'CampaignController');
        Route::get('customer/groups', 'CustomerController@getCustomerGroups');
        Route::resource('customer', 'CustomerController');
        Route::resource('product', 'ProductsController');
        Route::resource('countries', 'CountriesController');
        Route::resource('collection', 'CollectionController');
        Route::get('syncStoreData', 'StoreController@syncStoreData');
        Route::get('discount_types', 'StoreController@discount_types');
    });
});
