<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Shop;
use App\Models\User;
use App\Models\BusinessSetting;
use Auth;
use Hash;
use App\Notifications\EmailVerificationNotification;
use Illuminate\Support\Facades\DB;

class ShipRocketController extends Controller
{
    public function __construct()
    {
        //$this->middleware('user', ['only' => ['index']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public static function generateToken() {

        $generateTokenUrl = $_ENV['SHIP_ROCKET_BASE_API_URL'].$_ENV['SHIP_ROCKET_GENERATE_TOKEN_URL'];

        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => $generateTokenUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS =>'{
            "email": "'.$_ENV['SHIP_ROCKET_USER_NAME'].'",
            "password": "'.$_ENV['SHIP_ROCKET_PASSWORD'].'"
          }',
          CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json'
          ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        return json_decode($response);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public static function serviceability($ship_info, $tokenResp) {

        $url = $_ENV['SHIP_ROCKET_BASE_API_URL'].$_ENV['SHIP_ROCKET_SERVICABILITY_URL'];

        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => $url,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'GET',
          CURLOPT_POSTFIELDS =>'{
            "pickup_postcode":"560076",
            "delivery_postcode":"'.$ship_info->postal_code.'",
            "weight":"0.5",
            "cod":true
          }',
          CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Authorization: Bearer '.$tokenResp->token
          ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        return json_decode($response);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public static function createOrder($requestJson, $order_id) {

        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => $_ENV['SHIP_ROCKET_BASE_API_URL'].$_ENV['SHIP_ROCKET_EXT_ORDERS_CREATE_URL'],
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS => $requestJson,
          CURLOPT_HTTPHEADER => array (
              'Content-Type: application/json',
              'Authorization: Bearer '.$_ENV['SHIP_ROCKET_BEARER_TOKEN']
          ),
      ));

      $response = curl_exec($curl);

      curl_close($curl);

      $trackingCurl = curl_init();

      curl_setopt_array($trackingCurl, array(
        CURLOPT_URL => $_ENV['SHIP_ROCKET_BASE_API_URL'].$_ENV['SHIP_ROCKET_EXT_COURIER_TRACKING_URL'].'?order_id='.$order_id.'&channel_id='.$_ENV['SHIP_ROCKET_CHANNEL_ID'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
          'Content-Type: application/json',
          'Authorization: Bearer '.$_ENV['SHIP_ROCKET_BEARER_TOKEN']
        ),
      ));

      $trackingResponse = curl_exec($trackingCurl);

      curl_close($trackingCurl);
      
      //insert tracking info
      $trackingData = array(
          "order_id"=>$order_id,
          "tracking_response"=>$trackingResponse
      );

      $trackingInsertId = DB::table('order_tracking')->insert($trackingData);        
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (Auth::check()) {
			if((Auth::user()->user_type == 'admin' || Auth::user()->user_type == 'customer')) {
				flash(translate('Admin or Customer can not be a seller'))->error();
				return back();
			} if(Auth::user()->user_type == 'seller'){
				flash(translate('This user already a seller'))->error();
				return back();
			}
            
        } else {
            return view('frontend.seller_form');
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $user = null;
        if (!Auth::check()) {
            if (User::where('email', $request->email)->first() != null) {
                flash(translate('Email already exists!'))->error();
                return back();
            }
            if ($request->password == $request->password_confirmation) {
                $user = new User;
                $user->name = $request->name;
                $user->email = $request->email;
                $user->user_type = "seller";
                $user->password = Hash::make($request->password);
                $user->save();
            } else {
                flash(translate('Sorry! Password did not match.'))->error();
                return back();
            }
        } else {
            $user = Auth::user();
            if ($user->customer != null) {
                $user->customer->delete();
            }
            $user->user_type = "seller";
            $user->save();
        }

        if (Shop::where('user_id', $user->id)->first() == null) {
            $shop = new Shop;
            $shop->user_id = $user->id;
            $shop->name = $request->name;
            $shop->address = $request->address;
            $shop->slug = preg_replace('/\s+/', '-', $request->name);

            if ($shop->save()) {
                auth()->login($user, false);
                if (BusinessSetting::where('type', 'email_verification')->first()->value != 1) {
                    $user->email_verified_at = date('Y-m-d H:m:s');
                    $user->save();
                } else {
                    $user->notify(new EmailVerificationNotification());
                }

                flash(translate('Your Shop has been created successfully!'))->success();
                return redirect()->route('shops.index');
            } else {
                $user->user_type == 'customer';
                $user->save();
            }
        }

        flash(translate('Sorry! Something went wrong.'))->error();
        return back();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    public function destroy($id)
    {
        //
    }
}
