<?php

namespace App\Http\Controllers;

use App\Http\Requests\CampaignCreate;
use App\Http\Requests\MarkCampaign;
use App\Models\BOGOCampaign;
use App\Models\BulkCampaigns;
use App\Models\BundleCampaign;
use App\Models\Campaign;
use App\Models\DiscountCampaigns;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CampaignController extends Controller {
    private $pagination_count;
    
    public function __construct() {
        $this->pagination_count = config('custom.default_pagination_count');
        $this->middleware('auth:api');
    }

    public function index(Request $request) {
        $id = Auth::user()->store_id;
        if(isset($id) && $id !== null) {
            $campaigns = Campaign::where('store_id', $id)->where('valid', 'Active');
            $campaigns = $this->filterCampaigns($campaigns, $request);
            if($campaigns !== null && $campaigns->count() > 0) {
                $payload = [];
                foreach($campaigns as $campaign) {
                    $temp = [
                        'campaign_id' => $campaign->id, 
                        'name' => $campaign->name, 
                        'status' => $campaign->status, 
                        'start_date' => $campaign->start_date, 
                        'end_date' => $campaign->end_date,
                        'discount_type' => $campaign->discount_type,
                        'times_used' => $campaign->times_used,
                        'created_at' => date('Y-m-d h:i:s', strtotime($campaign->created_at)),
                        'favourite' => $campaign->favorite
                    ];
                    $temp['bogo'] = BOGOCampaign::where('campaign_id', $campaign->id)->get()->pluck('name');
                    $temp['discount'] = DiscountCampaigns::where('campaign_id', $campaign->id)->get()->pluck('name');
                    $temp['bulk'] = BulkCampaigns::where('campaign_id', $campaign->id)->get()->pluck('name');
                    $payload[] = $temp;
                }
                return response()->json(['status' => true, 'campaigns' => $payload], 200);
            } else return response()->json(['status' => true, 'campaigns' => null], 200);
        } 
        return response()->json(['status' => false, 'message' => 'Invalid / Missing store_id in request headers !'], 200);
    }

    public function show($id, Request $request) {
        if(isset($id)) {
            $campaign = Campaign::where('id', $id)->first();
            if($campaign !== null && $campaign->count() > 0)
                return response()->json(['status' => true, 'campaign' => $campaign->getInformation()], 200);
            return response()->json(['status' => false, 'message' => 'Campaign Not Found !']);
        }
        return response()->json(['status' => false, 'message' => 'ID should be passed.']);
    }

    private function filterCampaigns($campaigns, $request) {
        if(isset($request->tab)) {
            if($request->tab !== 'all')
                $campaigns = $request->tab == 'favourite' ? $campaigns->where($request->tab, 'true') : $campaigns->where('status', $request->tab);
            if(isset($request->searchTerm))
                $campaigns = $campaigns->where('name', 'LIKE', '%'.$request->searchTerm.'%');
            if(isset($request->start_date)) 
                $campaigns = $campaigns->where('start_date', 'LIKE', '%'.date('Y-m-d', strtotime($request->start_date)).'%');
            if(isset($request->end_date)) 
                $campaigns = $campaigns->where('end_date', 'LIKE', '%'.date('Y-m-d', strtotime($request->end_date)).'%');
            if(isset($request->times_used)) 
                $campaigns = $campaigns->where('times_used', $request->times_used);
            if(isset($request->discount_type)) 
                $campaigns = $campaigns->where('discount_type', 'LIKE', '%'.$request->discount_type.'%');
            if(isset($request->sortBy) && isset($request->sortOrder))
                $campaigns = $campaigns->orderBy($request->sortBy, $request->sortOrder);
            if(isset($request->limit))
                $campaigns = $campaigns->limit($request->limit);
        } else $campaigns = $campaigns->orderBy('created_at', 'desc');
        return $campaigns->paginate($this->pagination_count);
    }

    public function store(CampaignCreate $request) {
        try{
        $request = $request->all();
        DB::beginTransaction();
        $message = 'Created';
        $campaign_row = Campaign::create([
            'name' => $request['campaign_name'],
            'store_id' => Auth::user()->store_id,
            'start_date' => date('Y-m-d h:i:s', strtotime($request['start_date'])),
            'end_date' => date('Y-m-d h:i:s', strtotime($request['end_date'])),
            'discount_type' => $request['discount_type'],
            'valid' => 'Active',
            'status' => 'Active',
            'times_used' => 0
        ]);
        if(isset($request['Bundle'])) {
            $bundle_payload = [];
            foreach($request['Bundle'] as $bundle_item) {
                $bundle_item['campaign_id'] = $campaign_row->id;
                if(is_array($bundle_item['get_ids'])) $bundle_item['get_ids'] = implode(',', $bundle_item['get_ids']);
                if(is_array($bundle_item['buy_ids'])) $bundle_item['buy_ids'] = implode(',', $bundle_item['buy_ids']);
                if(is_array($bundle_item['customer_ids_eligible'])) $bundle_item['customer_ids_eligible'] = implode(',', $bundle_item['customer_ids_eligible']);
                $bundle_payload[] = $bundle_item;
            }
            if(count($bundle_payload) > 0) 
                BundleCampaign::insert($bundle_payload);
        }
        if(isset($request['BOGO'])) {
            $bogo_payload = [];
            foreach($request['BOGO'] as $bogo_item) {
                $bogo_item['campaign_id'] = $campaign_row->id;
                if(is_array($bogo_item['get_ids'])) $bogo_item['get_ids'] = implode(',', $bogo_item['get_ids']);
                if(is_array($bogo_item['buy_ids'])) $bogo_item['buy_ids'] = implode(',', $bogo_item['buy_ids']);
                if(is_array($bogo_item['customer_ids_eligible'])) $bogo_item['customer_ids_eligible'] = implode(',', $bogo_item['customer_ids_eligible']);
                $bogo_payload[] = $bogo_item;
            }
            if(count($bogo_payload) > 0)
                BOGOCampaign::insert($bogo_payload);
        }
        if(isset($request['Discount'])) {
            $discount_payload = [];
            foreach($request['Discount'] as $discount_item) {
                $discount_item['campaign_id'] = $campaign_row->id;
                if(is_array($discount_item['applied_ids'])) $discount_item['applied_ids'] = implode(',', $discount_item['applied_ids']);
                if(is_array($discount_item['eligible_customers'])) $discount_item['eligible_customers'] = implode(',', $discount_item['eligible_customers']);
                $discount_payload[] = $discount_item;
            }
            if(count($discount_payload) > 0) 
                DiscountCampaigns::insert($discount_payload);
        }
        if(isset($request['Bulk'])) {
            $bulk_payload = [];
            foreach($request['Bulk'] as $bulk_item) {
                $bulk_item['campaign_id'] = $campaign_row->id;
                if(is_array($bulk_item['buy_ids'])) $bulk_item['buy_ids'] = implode(',', $bulk_item['buy_ids']);
                if(is_array($bulk_item['eligible_customers'])) $bulk_item['eligible_customers'] = implode(',', $bulk_item['eligible_customers']);
                if(is_array($bulk_item['discount_levels'])) $bulk_item['discount_levels'] = json_encode($bulk_item['discount_levels']);
                $bulk_payload[] = $bulk_item;
            }
            if(count($bulk_payload) > 0)
                BulkCampaigns::insert($bulk_payload);
        }
        if(isset($request['Bundle'])) {
            $bundle_payload = [];
            foreach($request['Bundle'] as $bundle_item) {
                $bundle_item['campaign_id'] = $campaign_row->id;
                if(is_array($bundle_item['buy_ids'])) $bundle_item['buy_ids'] = implode(',', $bundle_item['buy_ids']);
                if(is_array($bundle_item['get_ids'])) $bundle_item['get_ids'] = implode(',', $bundle_item['get_ids']);
                if(is_array($bundle_item['customer_ids_eligible'])) $bundle_item['customer_ids_eligible'] = implode(',', $bundle_item['customer_ids_eligible']);
                $bundle_payload[] = $bundle_item;
            }
            if(count($bundle_item) > 0)
                BundleCampaign::insert($bundle_payload);
        }
        DB::commit();
        return response()->json(['status' => true, 'message' => 'Campaign '.$message.' Successfully !'], 200);
        } catch(Exception $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => $e->getMessage(), 'trace' => $e->getTrace()], 501);
        }
    }

    public function markCampaigns(MarkCampaign $request) {
        $campaigns = Campaign::whereIn('id', $request->campaign_ids);
        $message = '';
        if(isset($request->status)) {
            $campaigns->update(['status' => $request->status]);
            $message = 'Marked '.$request->status;
        }    
        if(isset($request->favourite)){    
            $campaigns->update(['favorite' => 'true']);
            $message = 'Marked Favourite';
        }
        if(isset($request->delete)) {
            $campaigns->update(['valid' => 'Inactive']);
            $message = 'Deleted';
        }
        return response()->json(['status' => true, 'message' => 'Campaigns '.$message.' Successfully !'], 200);
    }
}
