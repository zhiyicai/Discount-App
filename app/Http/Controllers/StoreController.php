<?php

namespace App\Http\Controllers;

use App\Jobs\Sync\Collections;
use App\Jobs\Sync\Countries;
use App\Jobs\Sync\CustomerGroups;
use App\Jobs\Sync\Customers;
use App\Jobs\Sync\Products;
use App\Models\DiscountTypes;
use App\Models\Store;
use App\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StoreController extends Controller {
    public function __construct() {
        //$this->middleware('auth:api');
    }

    public function index(Request $request) {
        if($request->expectsJson()) {
            $store_details = Store::where('id', $request->store_id)->first();
            if($store_details !== null && $store_details->count() > 0)
                return ['token' => $store_details->getUserDetails->access_token];
        }
    }

    public function syncStoreData(){
        try{
            Countries::dispatch(Auth::user()->store_id);
            //Products::dispatchNow($id);
            //Customers::dispatchNow($id);
            //Collections::dispatch(Auth::user()->store_id);
            //CustomerGroups::dispatch(Auth::user()->store_id);
            return response()->json(['status' => true, 'message' => 'Completed !'], 200);
        } catch(Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 200);
        }
    }

    public function discount_types() {
        $discount_types = DiscountTypes::select('id', 'name', 'description')->get()->toArray();
        return ['status' => true, 'discounts' => $discount_types];
    }
}
