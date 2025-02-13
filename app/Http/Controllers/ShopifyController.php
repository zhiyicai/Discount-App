<?php

namespace App\Http\Controllers;

use App\Jobs\Sync\Collections;
use App\Jobs\Sync\Customers;
use App\Jobs\Sync\Products;
use App\Models\Store;
use App\Models\StoreInstallations;
use App\Models\StorePlans;
use App\Models\Plans;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Traits\RequestTrait;
use App\Traits\FunctionTrait;
use App\User;
use Illuminate\Support\Facades\Hash;

class ShopifyController extends Controller {
    use RequestTrait, FunctionTrait;
    private $apiKey;
    private $apiSecret;
    private $scopes;
    private $states;
    private $hmac;
    private $apiVersion;
    private $shopify_token;
    private $forwardingAddress;
    private $redirectUri;
    private $storeName;
    private $base_plan;
    private $app_name;
    private $base_price;
    private $trial_days;
    private $password;

    public function __construct(){
        $this->apiKey               = config('custom.shopify_api_key');
        $this->apiSecret            = config('custom.shopify_api_secret');
        $this->apiVersion           = config('custom.shopify_rest_api_version');
        $this->app_name             = config('app.name');
        $this->forwardingAddress    = config('app.url');
        $this->scopes               = 'write_products,write_customers,write_orders,write_draft_orders,read_locations,write_fulfillments,write_checkouts';
        $this->state                = Str::random(12);
        $this->redirectUri          = $this->forwardingAddress.'/shopify/callback';
        $this->base_plan            = Plans::where('status', 'Active')->orderBy('price', 'asc')->first();
        $this->base_price           = $this->base_plan->price;   
        $this->trial_days           = 7;
        $this->password             = '123456';
    }
    
    public function welcome(Request $request){
        if(isset($request->shop)){
            $check = Store::where('permanent_domain', $request->shop)->first();
            if($check !== NULL && $check->count() > 0 && $check->status == 'Active'){
                $current_plan = StorePlans::where('store_id', $check->id)->where('status', 'Active')->first();
                if($current_plan !== NULL && $current_plan->count() > 0){
                    return redirect()->to('home?store_id='.$check->id);
                } else {
                    $rac_check = StorePlans::where('store_id', $check->id)->latest()->first();
                    if($rac_check !== NULL && $rac_check->count() > 0 && $rac_check->status !== 'Active'){
                        $store = Store::where('id', $rac_check->store_id)->first();
                        return Redirect::to($this->createAnotherRACAndRedirect($rac_check->plan_id, $store->permanent_domain));
                    }
                }                  
            } else return Redirect::to('shopify?shop='.$request->shop);
        } else return response()->json(['status' => false, 'message' => 'Missing \'?shop=\' parameter in request !']);
    }

    public function onBoard(Request $request){
        if(isset($request->shop) && !is_null($request->shop)){
            $installUrl = 'https://' . $request->shop .
            '/admin/oauth/authorize?client_id=' .$this->apiKey. 
            '&scope=' .$this->scopes.
            '&state=' .$this->state.
            '&redirect_uri='.urlencode($this->redirectUri);
            //cookie()->forever('state', $this->state);
            return Redirect::to($installUrl);
        } else return response()->json(['status' => false, 'message' => 'Please verify that your request contains "shop" parameter']);
    }

    public function activate(Request $request){
        $checkIfRacActivated = $this->checkIfRACIsAccepted($request->charge_id, $request->store_id);
        if($checkIfRacActivated === true){
            return $this->activateRAC($request->all());
        } else return Redirect::to($checkIfRacActivated);
    }

    private function activateRAC($request){
        $store_details = Store::where('id', $request['store_id'])->first();
        //$activateURL = 'https://'.$store_details->permanent_domain.'/admin/api/'.$this->apiVersion.'/recurring_application_charges/'.$request['charge_id'].'/activate.json';
        $activateURL = getShopifyURLForStore($store_details->permanent_domain, 'recurring_application_charges/'.$request['charge_id'].'/activate.json', null, $store_details->permanent_domain);
        $activateURLHeaders = ['Content-Type:application/json', 'X-Shopify-Access-Token:'. $store_details->access_token, 'X-Frame-Options: allow'];
        $redirectUrl = 'https://'.config('app.url').'/login?login='.$store_details->email;
        $activateURLBody = $this->getRACActivationPayload($request);    
        $response = $this->makeAPOSTCallToShopify($activateURLBody, $activateURL, $activateURLHeaders);
        if($response['httpCode'] === 200){
            $this->markStorePlanActive($store_details->permanent_domain, 'Active', $request['charge_id']);
            return Redirect::to($redirectUrl);
        } else dd($response);
    }

    private function getRACActivationPayload($request){
        $store_details = Store::where('id', $request['store_id'])->first();
        $checkForTrialDays = $this->getTrialDaysCountForStore($store_details->id);
        $activateURLBody = [];
        $activateURLBody['recurring_application_charge'] = [
            'id' => $request['charge_id'],
            'name' => $this->app_name,
            'price' => $this->base_price,
            'status' => 'accepted',
            'return_url' => 'https://'.$this->forwardingAddress.'/welcome?shop='.urlencode($store_details->permanent_domain),
            'test' => true
        ];

        if($checkForTrialDays !== false)
            $activateURLBody['recurring_application_charge']["trial_days"] = $checkForTrialDays;
        return json_encode($activateURLBody);
    }

    private function checkIfRACIsAccepted($charge_id, $store_id){
        $store_details = Store::where('id', $store_id)->first();
        $endpoint = getShopifyURLForStore('recurring_application_charges/'.$charge_id.'.json', null, $store_details->permanent_domain);
        $headers = ['Content-Type'=>'application/json', 'X-Shopify-Access-Token' => $store_details->access_token, 'X-Frame-Options' => 'allow'];
        $response = json_decode($this->makeAGETCallToShopify($endpoint, [], $headers), true);
        $plan_details = StorePlans::where('rac_id', $charge_id)->first();    
        if($response['recurring_application_charge']['status'] == 'accepted') return true;
        else return Redirect::to($this->createAnotherRACAndRedirect($plan_details->plan_id, $store_details->permanent_domain)); 
    }

    public function callBack(Request $request){
        try{
            if($this->verifyHMAC($request->all())){
                if(isset($request->code) && isset($request->timestamp) && isset($request->shop) && isset($request->hmac)){
                    $response = $this->requestForAccessToken($request->all());
                    if($response['httpCode'] === 200){
                        $this->shopify_token = $access_token = json_decode($response['sBody'], true)['access_token'];
                        $store_details = $this->requestForShopDetails($request->all());
                        if(isset($store_details['shop'])){
                            $store_details = $store_details['shop'];
                            $payload = [
                                'store_id' => $store_details['id'],
                                'name' => $store_details['name'],
                                'access_token' => $access_token,
                                'permanent_domain' => $store_details['myshopify_domain'],
                                'phone' => $store_details['phone'],
                                'currency' => $store_details['currency'],
                                'support_email' => $store_details['customer_email'],
                                'email' => $store_details['email'],
                                'status' => 'Active'
                            ];
                            $exec = $this->storeShopifyStoreDetailsAndActivateBilling($payload); 
                            return $exec === true ? Redirect::to('https://'.$store_details['myshopify_domain'].'/admin/apps/'.config('custom.app_name')) : Redirect::to($exec);
                        }
                    } else return response()->json(['status' => false, 'message' => 'Shopify Gave Error Code - '.$response['httpCode']], 400);
                } else return response()->json(['status' => false, 'message' => 'Malformed request'], 400);
            } else return response()->json(['status' => false, 'message' => 'Invalid Request'], 400);
        } catch(Exception $e){
            Log::info(['code' => $e->getCode(), 'message' => $e->getMessage()]);
            print_r(['code' => $e->getCode(), 'message' => $e->getMessage(), 'trace' => json_encode($e->getTrace())]);
        }
    }

    // private function createAnotherRACAndRedirect($id, $storeName){
    //     $store_details = $this->getStoreDetailsByDomain($storeName);
    //     if(isset($id) && isset($storeName)){
    //         $shopify_payload = $this->getRACPayload($id, $storeName);
    //         $shopify_headers = [ 'X-Shopify-Access-Token:'.$store_details->access_token, 'Content-Type:application/json' ];
    //         $response = $this->makeAPOSTCallToShopify($shopify_payload, getShopifyURLForStore('recurring_application_charges.json', null, $storeName), $shopify_headers);
    //         $body = json_decode($response['sBody'], true);     
    //         $body = $body['recurring_application_charge'];
    //         $this->assignStorePlan($store_details->id, 'Inactive', $body['id'], $id, $body['confirmation_url']);
    //         return $body['confirmation_url'];
    //     } else return response()->json(['status' => false, 'message' => 'Malformed Request'], 400);
    // }

    private function getRACPayload($plan_id, $store_domain){
        $store_details = $this->getStoreDetailsByDomain($store_domain);
        $plan_details = $this->getPlanDetailsById($plan_id);
        $checkForTrialDays = $this->getTrialDaysCountForStore($store_details->id);
        $shopify_payload = [];
        $shopify_payload['recurring_application_charge'] = [
            'name' => $this->app_name,
            "price" => $plan_details->price,
            "return_url" => $this->forwardingAddress . "/shopify/activate?store_id=".$store_details->id,
            "test" => true
        ];
        if($checkForTrialDays !== false)
            $shopify_payload['recurring_application_charge']['trial_days'] = $checkForTrialDays;
        return json_encode($shopify_payload);
    }

    private function createUserAndAssignToken($store_id) {
        $store_details = Store::where('id', $store_id)->first();
        $user = User::updateOrCreate(['store_id' => $store_details->id], [
            'store_id' => $store_details->id,
            'name' => $store_details->name,
            'email' => $store_details->permanent_domain,
            'password' => Hash::make($this->password),
        ]);
        $url = config('app.url').'/oauth/token';
        $payload = [
            'grant_type' => 'password',
            'client_id' => config('custom.client_id'),
            'client_secret' => config('custom.client_secret'),
            'username' => $store_details->permanent_domain,
            'password' => $this->password,
            'scopes' => '*'
        ];
        $response = $this->makeAPOSTCallToShopify($payload, $url, []);
        if($response !== null && isset($response['httpCode']) && $response['httpCode'] == '200') {
            $response = json_decode($response['sBody'], true);
            User::where('id', $user->id)->update(['access_token' => $response['access_token']]);
        } else Log::info('Something went wrong during local access token - '.json_encode($response));
    }

    private function storeShopifyStoreDetailsAndActivateBilling($payload){
        $check = Store::where('store_id', $payload['store_id'])->first();
        if($check !== NULL && $check->count() > 0){
            Store::where('permanent_domain', $payload['permanent_domain'])->update($payload);
            StorePlans::where('id', $check->id)->update(['status' => 'Inactive']); //Mark all previous plans inactive
            $id = $check->id;
            //$this->updateUser($id, $payload['permanent_domain']);
        } else {
            $id = Store::insertGetId($payload);
            $this->insertStoreInstallationData($id);
            //$check = $this->getStoreDetailsByDomain($payload['permanent_domain']);
        }
        $this->createUserAndAssignToken($id);
        $this->syncStoreCustomers($id);
        $this->syncStoreCollections($id);
        $this->syncStoreProducts($id);
        $webhook_events = [
            'products/create' => '/newProduct',
            'products/updated' => '/updateProduct',
            'products/delete' => '/deleteProduct',
            'customers/create' => '/newCustomer',
            'customers/update' => '/updateCustomer',
            'customers/delete' => '/deleteCustomer',
            'collections/create' => '/newCollection',
            'collections/update' => '/updateCollection',
            'collections/delete' => '/deleteCollection' 
        ];
        $this->registerForWebhooks($payload, $webhook_events);
        //$this->syncCountriesAndStates($id);
        $this->registerForAppDeletionWebhook($payload);
        //Comment this line when paid subscription is required
        $this->giveFreePlanToStore($id);
        return true;
        //Un-comment this line when paid subscriptions needed.
        //return $this->checkForStoreRecurringApplicationCharge($id);
    }

    private function syncStoreCollections($id) {
        Collections::dispatch($id);
    }

    private function syncStoreProducts($id) {
        Products::dispatch($id);
    }

    private function syncStoreCustomers($id) {
        Customers::dispatch($id);
    }

    //Comment This Function when paid subscriptions are required
    private function giveFreePlanToStore($store_id){
        $free_plan = Plans::where('name', 'Free')->first();
        StorePlans::create([
            'store_id' => $store_id,
            'plan_id' => $free_plan->id,
            'start_date' => date('Y-m-d h:i:s'),
            'end_date' => null,
            'status' => 'Active',
            'rac_id' => null,
            'confirmation_url' => null
        ]);
        return true;
    }

    private function checkForStoreRecurringApplicationCharge($store_id){
        $store_details = Store::where('id', $store_id)->first();
        $response = json_decode($this->makeAGETCallToShopify('https://'.$store_details->permanent_domain.'/admin/api/'.$this->apiVersion.'/recurring_application_charges.json', [], ['Content-Type' => 'application/json','X-Shopify-Access-Token' => $store_details->access_token]), true);
        $status = 'pending';
        foreach($response['recurring_application_charges'] as $recurring_charge){
            if($recurring_charge['name'] == $this->app_name){
                if($recurring_charge['status'] === 'active'){
                    $status = 'active'; break;
                }
            }
        }
        return $status == 'active' ? true : $this->pendingStoreDetails($store_id);  
    }

    private function pendingStoreDetails($store_id){
        $store_details = Store::where('id', $store_id)->first();
        $plan_id = $this->base_plan->id;
        $shopify_payload = $this->getRACPayload($plan_id, $store_details->permanent_domain);   
        $shopify_headers = [ 'X-Shopify-Access-Token: '.$store_details->access_token, 'Content-Type: application/json', 'X-Frame-Options: Allow' ];
        $response = $this->makeAPOSTCallToShopify($shopify_payload ,'https://'.$store_details->permanent_domain.'/admin/api/'.$this->apiVersion.'/recurring_application_charges.json', $shopify_headers);
        $body = json_decode($response['sBody'], true)['recurring_application_charge'];     
        $this->assignStorePlan($plan_id, 'Inactive', $body['id'], $this->base_plan->id, $body['confirmation_url']);
        return $body['confirmation_url'];    
    }

    private function registerForAppDeletionWebhook($payload){
        $shopify_payload = json_encode(["webhook" => ["topic" => "app/uninstalled", "address" => $this->forwardingAddress.'/deleteShopData', "format" => "json"]]);
        $endpoint = getShopifyURLForStore('webhooks.json', null, $payload["permanent_domain"]);
        $headers = ['Content-Type:application/json', 'X-Shopify-Access-Token:'.$payload['access_token']];
        $response = $this->makeAPOSTCallToShopify($shopify_payload, $endpoint, $headers);
        Log::info(['message' => 'Registered For App Deletion Webhook', 'payload' => $shopify_payload, 'response' => $response]);
        return true;
    }

    private function requestForAccessToken($request){
        try {
            $accessTokenRequestUrl = 'https://'. $request['shop'] .'/admin/oauth/access_token';
            $body = json_encode([ "client_id" => $this->apiKey, "client_secret" => $this->apiSecret, "code" => $request['code']]);
            return $this->makeAPOSTCallToShopify($body, $accessTokenRequestUrl, ['Content-Type:application/json', 'X-Frame-Options: SAMEORIGIN']);
        } catch(Exception $exception){ 
            Log::info('Error encountered during access token - '.$exception->getMessage());
            throw $exception;
        }
    }

    private function requestForShopDetails($request){
        $shopRequestUrl = 'https://'.$request['shop'].'/admin/api/'.$this->apiVersion.'/shop.json';
        $shopRequestHeaders = [ 'Content-Type' => 'application/json', 'X-Shopify-Access-Token' => $this->shopify_token ];     
        return json_decode($this->makeAGETCallToShopify($shopRequestUrl, [], $shopRequestHeaders), true);            
    }

    private function verifyHMAC($request){
        $arr = [];
        $hmac = $request['hmac'];
        unset($request['hmac']);
        foreach($request as $key => $value){
            $key    = str_replace("%","%25",$key);
            $key    = str_replace("&","%26",$key);
            $key    = str_replace("=","%3D",$key);
            $value  = str_replace("%","%25",$value);
            $value  = str_replace("&","%26",$value);
            $arr[]  = $key."=".$value;
        }
        $verify_hmac = hash_hmac('sha256', join('&',$arr), $this->apiSecret, false);
        return $verify_hmac == $hmac ? true : false;
    }

    public function returnCustomerData(Request $request) {
        // $request = $request->all();
        // Log::info("Redact customer data request received ".json_encode($request));
        // $payload = [];
        // $payload['shopDetails'] = $this->getStoreDetailsByDomain($request->shop_domain);
        // $payload['orders'] = Order::whereIn('id', $request['orders_requested'])->get();
        // if(checkNotNullAndCountGreaterThanZero($payload) && checkNotNullAndCountGreaterThanZero($payload['shopDetails']) && checkNotNullAndCountGreaterThanZero($payload['orders']))
        //     return response()->json(['response' => $payload], 200);
        //else 
        return response()->json(['success' => true, 'message' => 'Customer Data Not Found.'], 200);   
    }

    public function deleteCustomerData(Request $request) {
        //$request = $request->all();
        // dd($request);
        // Log::info('Customer delete request received '.json_encode($request));
        // $payload = [];
        // $payload['shopDetails'] = $this->getStoreDetailsByDomain($request['shop_domain']);
        // //Order::where('store_id', $payload['shopDetails']->id)->delete();
        // //APILogs::where('store_id', $payload['shopDetails']->id)->delete();
        // if(checkNotNullAndCountGreaterThanZero($payload) && checkNotNullAndCountGreaterThanZero($payload['shopDetails']))
        //     return response()->json(['message' => $payload], 200);
        //else 
        return response()->json(['success' => true, 'message' => 'No Customer Data Found'], 200);    
    }

    // public function deleteShopData(Request $request){
    //     //Log::info(['request recieved' => $request->all()]);
    //     $store_details = $this->getStoreDetailsByDomain($request->shop_domain);
    //     if(checkNotNullAndCountGreaterThanZero($store_details)){
    //         Store::where('id', $store_details->id)->update(['status' => 'Inactive']); //Deactivate that store
    //         StorePlans::where('store_id', $store_details->id)->update(['status' => 'Inactive']);
    //         $temp = StoreInstallations::where('store_id', $store_details->id)->orderBy('id', 'DESC')->first();
    //         if(checkNotNullAndCountGreaterThanZero($temp)) StoreInstallations::where('id', $temp->id)->update(['uninstallation_date' => date('Y-m-d h:i:s')]);
    //         Order::where('store_id', $store_details->id)->delete();
    //         //SendReviewJob::dispatchNow($store_details);
    //     }
    //     return response()->json(['success' => true, 'message' => 'Successfully deleted Shop Data'], 200);
    // }
}
