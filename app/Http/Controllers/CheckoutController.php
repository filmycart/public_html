<?php

namespace App\Http\Controllers;

use App\Utility\PayfastUtility;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Address;
use App\Models\CombinedOrder;
use App\Models\Product;
use App\Utility\PayhereUtility;
use App\Utility\NotificationUtility;
use App\Http\Controllers\ShipRocketController;
use Session;
use Auth;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function __construct()
    {
        //
    }

    //check the selected payment gateway and redirect to that controller accordingly
    public function checkout(Request $request)
    {
        // Minumum order amount check
        if(get_setting('minimum_order_amount_check') == 1){
            $subtotal = 0;
            foreach (Cart::where('user_id', Auth::user()->id)->get() as $key => $cartItem){ 
                $product = Product::find($cartItem['product_id']);
                $subtotal += cart_product_price($cartItem, $product, false, false) * $cartItem['quantity'];
            }
            if ($subtotal < get_setting('minimum_order_amount')) {
                flash(translate('You order amount is less then the minimum order amount'))->warning();
                return redirect()->route('home');
            }
        }
        // Minumum order amount check end
        if ($request->payment_option != null) {
            (new OrderController)->store($request);

            $request->session()->put('payment_type', 'cart_payment');
            
            $data['combined_order_id'] = $request->session()->get('combined_order_id');
            $request->session()->put('payment_data', $data);

            if ($request->session()->get('combined_order_id') != null) {
                // If block for Online payment, wallet and cash on delivery. Else block for Offline payment
                $decorator = __NAMESPACE__ . '\\Payment\\' . str_replace(' ', '', ucwords(str_replace('_', ' ', $request->payment_option))) . "Controller";
                if (class_exists($decorator)) {
                    return (new $decorator)->pay($request);
                } else {
                    $combined_order = CombinedOrder::findOrFail($request->session()->get('combined_order_id'));
                    foreach ($combined_order->orders as $order) {
                        $order->manual_payment = 1;
                        $order->save();
                    }
                    flash(translate('Your order has been placed successfully. Please submit payment information from purchase history'))->success();
                    return redirect()->route('order_confirmed');
                }
            }
        } else {
            flash(translate('Select Payment Option.'))->warning();
            return back();
        }
    }

    //redirects to this method after a successfull checkout
    public function checkout_done($combined_order_id, $payment)
    {
        $combined_order = CombinedOrder::findOrFail($combined_order_id);

        foreach ($combined_order->orders as $key => $order) {
            $order = Order::findOrFail($order->id);
            $order->payment_status = 'paid';
            $order->payment_details = $payment;
            $order->save();

            calculateCommissionAffilationClubPoint($order);
        }
        Session::put('combined_order_id', $combined_order_id);
        return redirect()->route('order_confirmed');
    }

    public function get_shipping_info(Request $request)
    {
        $carts = Cart::where('user_id', Auth::user()->id)->get();
        if ($carts && count($carts) > 0) {
            $categories = Category::all();
            return view('frontend.shipping_info', compact('categories', 'carts'));
        }
        flash(translate('Your cart is empty'))->success();
        return back();
    }

    public function store_shipping_info(Request $request)
    {
        if ($request->address_id == null) {
            flash(translate("Please add shipping address"))->warning();
            return back();
        }

        $carts = Cart::where('user_id', Auth::user()->id)->get();

        foreach ($carts as $key => $cartItem) {
            $cartItem->address_id = $request->address_id;
            $cartItem->save();
        }

        return view('frontend.delivery_info', compact('carts'));
    }

    public function store_delivery_info(Request $request)
    {
        $carts = Cart::where('user_id', Auth::user()->id)
                ->get();

        if($carts->isEmpty()) {
            flash(translate('Your cart is empty'))->warning();
            return redirect()->route('home');
        }

        $shipping_info = Address::where('id', $carts[0]['address_id'])->first();
        $total = 0;
        $tax = 0;
        $shipping = 0;
        $subtotal = 0;

        /*$test = ShipRocketController::generateToken();
        dd($test);*/

        $generateTokenResponseArray = array();
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

        $generateTokenResponse = curl_exec($curl);
        $generateTokenResponseArray = json_decode($generateTokenResponse);
        curl_close($curl);
        
        $shippingResponseArray = array();
        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => $_ENV['SHIP_ROCKET_BASE_API_URL'].$_ENV['SHIP_ROCKET_SERVICABILITY_URL'],
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'GET',
          CURLOPT_POSTFIELDS =>'{
            "pickup_postcode":"560076",
            "delivery_postcode":"'.$shipping_info->postal_code.'",
            "weight":"0.5",
            "cod":true
        }',
          CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Authorization: Bearer '.$generateTokenResponseArray->token
          ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        $shippingResponseArray = json_decode($response);

        $codChargesArray = array();
        if((isset($shippingResponseArray->data->available_courier_companies)) && (!empty($shippingResponseArray->data->available_courier_companies))) {
            foreach ($shippingResponseArray->data->available_courier_companies as $key => $shipValue) {
                $codChargesArray[] = $shipValue->cod_charges;
            }    
        }

        rsort($codChargesArray);

        $finalShippingCharge = 0;
        if((isset($codChargesArray['0'])) && (!empty($codChargesArray['0']))) {
            $finalShippingCharge = $codChargesArray['0'];
        }

        if ($carts && count($carts) > 0) {
            foreach ($carts as $key => $cartItem) {
                $product = Product::find($cartItem['product_id']);
                $tax += cart_product_tax($cartItem, $product,false) * $cartItem['quantity'];
                $subtotal += cart_product_price($cartItem, $product, false, false) * $cartItem['quantity'];

                if ($request['shipping_type_' . $product->user_id] == 'pickup_point') {
                    $cartItem['shipping_type'] = 'pickup_point';
                    $cartItem['pickup_point'] = $request['pickup_point_id_' . $product->user_id];
                } else {
                    $cartItem['shipping_type'] = 'home_delivery';
                }
                $cartItem['shipping_cost'] = 0;
                if ($cartItem['shipping_type'] == 'home_delivery') {
                    $cartItem['shipping_cost'] = getShippingCost($carts, $key);
                }

                if(isset($cartItem['shipping_cost']) && is_array(json_decode($cartItem['shipping_cost'], true))) {

                    foreach(json_decode($cartItem['shipping_cost'], true) as $shipping_region => $val) {
                        if($shipping_info['city'] == $shipping_region) {
                            $cartItem['shipping_cost'] = (double)($val);
                            break;
                        } else {
                            $cartItem['shipping_cost'] = 0;
                        }
                    }
                } else {
                    if (!$cartItem['shipping_cost'] ||
                            $cartItem['shipping_cost'] == null ||
                            $cartItem['shipping_cost'] == 'null') {

                        $cartItem['shipping_cost'] = 0;
                    }
                }
                
                //$shipping += $cartItem['shipping_cost'];
                if($finalShippingCharge > $cartItem['shipping_cost']) {
                    $shipping += $finalShippingCharge;
                } else if($finalShippingCharge <= $cartItem['shipping_cost']) {
                    $shipping += $cartItem['shipping_cost'];
                }

                $cartItem->save();

            }
            $total = $subtotal + $tax + $shipping;

            return view('frontend.payment_select', compact('carts', 'shipping_info', 'total'));

        } else {
            flash(translate('Your Cart was empty'))->warning();
            return redirect()->route('home');
        }
    }

    public function generate_qr_code(Request $request) {

        //$key = "ec63454c-2f4a-42ae-840d-8bd8bf6c12e0";  // Your Api Token https://merchant.upigateway.com/user/api_credentials
        
        $key = "ec63454c-2f4a-42ae-840d-8bd8bf6c12e0";  // Your Api Token https://merchant.upigateway.com/user/api_credentials
        // $key = "acdc11de-f848-4547-902a-e968dbb564e3";

        $post_data = new \stdClass();
        $post_data->key = $key;
        $post_data->client_txn_id = (string) rand(100000, 999999); // you can use this field to store order id;
  
        /*$post_data->amount = $_POST['txnAmount'];
        $post_data->p_info = "product_name";
        $post_data->customer_name = $_POST['customerName'];
        $post_data->customer_email = $_POST['customerEmail'];
        $post_data->customer_mobile = $_POST['customerMobile'];*/

        $post_data->amount = '1';
        $post_data->p_info = "product_name";
        $post_data->customer_name = 'kirubakaran';
        $post_data->customer_email = 'kirubakaran.srm@gmail.com';
        $post_data->customer_mobile = '9944063620';

        $post_data->redirect_url = "https://dev.nachiyaartraders.in/checkout/payment_select"; // automatically ?client_txn_id=xxxxxx&txn_id=xxxxx will be added on redirect_url
        $post_data->udf1 = "extradata";
        $post_data->udf2 = "extradata";
        $post_data->udf3 = "extradata";

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://merchant.upigateway.com/api/create_order',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($post_data),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);

        $result = json_decode($response, true);

        /*if ($result['status'] == true) {
            echo '<script>location.href="' . $result['data']['payment_url'] . '"</script>';
            exit();
        }*/

        $upiLink = "";
        if((isset($result['data']['upi_intent'])) && (!empty($result['data']['upi_intent']))) {
            foreach($result['data']['upi_intent'] as $key=>$dataVal) {
                $upiLink .= '<div><a href="'.$dataVal.'">'.$key.'</a></div>';
            }
        }

        return $upiLink;
    }

    public function apply_coupon_code(Request $request)
    {
        $coupon = Coupon::where('code', $request->code)->first();
        $response_message = array();

        if ($coupon != null) {
            if (strtotime(date('d-m-Y')) >= $coupon->start_date && strtotime(date('d-m-Y')) <= $coupon->end_date) {
                if (CouponUsage::where('user_id', Auth::user()->id)->where('coupon_id', $coupon->id)->first() == null) {
                    $coupon_details = json_decode($coupon->details);

                    $carts = Cart::where('user_id', Auth::user()->id)
                                    ->where('owner_id', $coupon->user_id)
                                    ->get();

                    $coupon_discount = 0;
                    
                    if ($coupon->type == 'cart_base') {
                        $subtotal = 0;
                        $tax = 0;
                        $shipping = 0;
                        foreach ($carts as $key => $cartItem) { 
                            $product = Product::find($cartItem['product_id']);
                            $subtotal += cart_product_price($cartItem, $product, false, false) * $cartItem['quantity'];
                            $tax += cart_product_tax($cartItem, $product,false) * $cartItem['quantity'];
                            $shipping += $cartItem['shipping_cost'];
                        }
                        $sum = $subtotal + $tax + $shipping;
                        if ($sum >= $coupon_details->min_buy) {
                            if ($coupon->discount_type == 'percent') {
                                $coupon_discount = ($sum * $coupon->discount) / 100;
                                if ($coupon_discount > $coupon_details->max_discount) {
                                    $coupon_discount = $coupon_details->max_discount;
                                }
                            } elseif ($coupon->discount_type == 'amount') {
                                $coupon_discount = $coupon->discount;
                            }

                        }
                    } elseif ($coupon->type == 'product_base') {
                        foreach ($carts as $key => $cartItem) { 
                            $product = Product::find($cartItem['product_id']);
                            foreach ($coupon_details as $key => $coupon_detail) {
                                if ($coupon_detail->product_id == $cartItem['product_id']) {
                                    if ($coupon->discount_type == 'percent') {
                                        $coupon_discount += (cart_product_price($cartItem, $product, false, false) * $coupon->discount / 100) * $cartItem['quantity'];
                                    } elseif ($coupon->discount_type == 'amount') {
                                        $coupon_discount += $coupon->discount * $cartItem['quantity'];
                                    }
                                }
                            }
                        }
                    }

                    if($coupon_discount > 0){
                        Cart::where('user_id', Auth::user()->id)
                            ->where('owner_id', $coupon->user_id)
                            ->update(
                                [
                                    'discount' => $coupon_discount / count($carts),
                                    'coupon_code' => $request->code,
                                    'coupon_applied' => 1
                                ]
                            );
                        $response_message['response'] = 'success';
                        $response_message['message'] = translate('Coupon has been applied');
                    }
                    else{
                        $response_message['response'] = 'warning';
                        $response_message['message'] = translate('This coupon is not applicable to your cart products!');
                    }
                    
                } else {
                    $response_message['response'] = 'warning';
                    $response_message['message'] = translate('You already used this coupon!');
                }
            } else {
                $response_message['response'] = 'warning';
                $response_message['message'] = translate('Coupon expired!');
            }
        } else {
            $response_message['response'] = 'danger';
            $response_message['message'] = translate('Invalid coupon!');
        }

        $carts = Cart::where('user_id', Auth::user()->id)
                ->get();
        $shipping_info = Address::where('id', $carts[0]['address_id'])->first();

        $returnHTML = view('frontend.partials.cart_summary', compact('coupon', 'carts', 'shipping_info'))->render();
        return response()->json(array('response_message' => $response_message, 'html'=>$returnHTML));
    }

    public function remove_coupon_code(Request $request)
    {
        Cart::where('user_id', Auth::user()->id)
                ->update(
                        [
                            'discount' => 0.00,
                            'coupon_code' => '',
                            'coupon_applied' => 0
                        ]
        );

        $coupon = Coupon::where('code', $request->code)->first();
        $carts = Cart::where('user_id', Auth::user()->id)
                ->get();

        $shipping_info = Address::where('id', $carts[0]['address_id'])->first();

        return view('frontend.partials.cart_summary', compact('coupon', 'carts', 'shipping_info'));
    }

    public function apply_club_point(Request $request) {
        if (addon_is_activated('club_point')){

            $point = $request->point;

            if(Auth::user()->point_balance >= $point) {
                $request->session()->put('club_point', $point);
                flash(translate('Point has been redeemed'))->success();
            }
            else {
                flash(translate('Invalid point!'))->warning();
            }
        }
        return back();
    }

    public function remove_club_point(Request $request) {
        $request->session()->forget('club_point');
        return back();
    }

    public function order_confirmed()
    {
        $combined_order = CombinedOrder::findOrFail(Session::get('combined_order_id'));

        if($_ENV['SHIP_ROCKET_AUTOMATE_SHIPPING'] == 1) {
            $orderAttrib = array();
            $order_id = $user_id = $grand_total = $created_at = $updated_at = "";
            $ship_name = $ship_email = $ship_address = $ship_country = "";
            $ship_state = $ship_city = $ship_postal_code = $ship_phone = "";

            $ship_channel_id = $_ENV['SHIP_ROCKET_CHANNEL_ID'];
            if(!empty($combined_order)) {
                $orderAttrib = $combined_order->getAttributes();

                if((isset($orderAttrib['user_id'])) && (!empty($orderAttrib['user_id']))) {
                    $user_id = $orderAttrib['user_id'];
                }

                if((isset($orderAttrib['id'])) && (!empty($orderAttrib['id']))) {
                    $order_id = $orderAttrib['id'];
                }

                if((isset($orderAttrib['shipping_address'])) && (!empty($orderAttrib['shipping_address']))) {
                    $shipping_address = json_decode($orderAttrib['shipping_address']);
                }

                if((isset($orderAttrib['grand_total'])) && (!empty($orderAttrib['grand_total']))) {
                    $grand_total = $orderAttrib['grand_total'];
                }

                if((isset($orderAttrib['created_at'])) && (!empty($orderAttrib['created_at']))) {
                    $created_at = $orderAttrib['created_at'];
                }

                if((isset($orderAttrib['updated_at'])) && (!empty($orderAttrib['updated_at']))) {
                    $updated_at = $orderAttrib['updated_at'];
                }

                if($shipping_address->name) {
                     $ship_name = $shipping_address->name;
                }

                if($shipping_address->email) {
                     $ship_email = $shipping_address->email;
                }

                if($shipping_address->address) {
                     $ship_address = $shipping_address->address;
                }

                if($shipping_address->country) {
                     $ship_country = $shipping_address->country;
                }

                if($shipping_address->state) {
                     $ship_state = $shipping_address->state;
                }

                if($shipping_address->city) {
                     $ship_city = $shipping_address->city;
                }

                if($shipping_address->postal_code) {
                     $ship_postal_code = $shipping_address->postal_code;
                }

                if($shipping_address->phone) {
                     $ship_phone = $shipping_address->phone;
                }      
            }

            $curl = curl_init();

            $order_detail = DB::table('orders')
                            ->join('order_details', 'orders.id', '=', 'order_details.order_id')
                            ->join('products', 'order_details.product_id', '=', 'products.id')
                            ->where('orders.combined_order_id', $order_id)
                            ->where('orders.user_id', $user_id)
                            ->select('*')
                            ->get();

            $ordersArray = array();
            $order_unit_price = $order_weight = 0;
            $orderItemsJson = "";
            foreach($order_detail as $key=>$order) {
                $ordersArray[$key]['name'] = $order->name;
                $randNum = rand(100000, 999999);
                $skuCode = strtolower(str_replace(" ","-",$order->name)."-".$order->variation."-".$randNum);
                $ordersArray[$key]['sku'] = $skuCode;
                $ordersArray[$key]['units'] = $order->quantity;
                $ordersArray[$key]['selling_price'] = $order->price?$order->price:"";
                $ordersArray[$key]['discount'] = $order->discount?$order->discount:"";
                $ordersArray[$key]['tax'] = $order->tax?$order->tax:"";
                $ordersArray[$key]['hsn'] = 441122;
                $order_weight += $order->weight;
            }

            if(!empty($ordersArray)) {
                $orderItemsJson = json_encode($ordersArray);
            }

            /*$test ='{
              "order_id": "111",
              "order_date": "2025-02-05 11:11",
              "pickup_location": "Jammu",
              "channel_id": "6177358",
              "comment": "Reseller: M/s Goku",
              "billing_customer_name": "Naruto",
              "billing_last_name": "Uzumaki",
              "billing_address": "House 221B, Leaf Village",
              "billing_address_2": "Near Hokage House",
              "billing_city": "New Delhi",
              "billing_pincode": "110002",
              "billing_state": "Delhi",
              "billing_country": "India",
              "billing_email": "naruto@uzumaki.com",
              "billing_phone": "9876543210",
              "shipping_is_billing": true,
              "shipping_customer_name": "",
              "shipping_last_name": "",
              "shipping_address": "",
              "shipping_address_2": "",
              "shipping_city": "",
              "shipping_pincode": "",
              "shipping_country": "",
              "shipping_state": "",
              "shipping_email": "",
              "shipping_phone": "",
              "order_items": [
                {
                  "name": "Kunai",
                  "sku": "chakra123",
                  "units": 10,
                  "selling_price": "900",
                  "discount": "",
                  "tax": "",
                  "hsn": 441122
                }
              ],
              "payment_method": "Prepaid",
              "shipping_charges": 0,
              "giftwrap_charges": 0,
              "transaction_charges": 0,
              "total_discount": 0,
              "sub_total": 9000,
              "length": 10,
              "breadth": 15,
              "height": 20,
              "weight": 2.5
            }';
            var_dump($test);
            exit;*/

            $address_length = 0;
            $address1 = $address2 = "";
            $address_length = strlen($ship_address);

            if($address_length > 80) {
                $address_equal_length = $address_length/2;
                $address1 = substr($ship_address,0,$address_equal_length);
                $address2 = substr($ship_address,$address_equal_length,$address_length); 
            }

            $country_code = "+91";
            $ship_phone = preg_replace("/^\+?{$country_code}/", '',$ship_phone);

            $requestJson = '{
                "order_id": "'.$order_id.'",
                "order_date": "'.$created_at.'",
                "pickup_location": "'.$_ENV['SHIP_ROCKET_PICKUP_LOCATION'].'",
                "channel_id": "'.$_ENV['SHIP_ROCKET_CHANNEL_ID'].'",
                "comment": " ",
                "billing_customer_name": "'.$ship_name.'",
                "billing_last_name": "'.$ship_name.'",
                "billing_address": "'.$address1.'",
                "billing_address_2": "",
                "billing_city": "'.$ship_city.'",
                "billing_pincode": "'.$ship_postal_code.'",
                "billing_state": "'.$ship_state.'",
                "billing_country": "'.$ship_country.'",
                "billing_email": "'.$ship_email.'",
                "billing_phone": "'.$ship_phone.'",
                "shipping_is_billing": true,
                "shipping_customer_name": "",
                "shipping_last_name": "",
                "shipping_address": "",
                "shipping_address_2": "",
                "shipping_city": "",
                "shipping_pincode": "",
                "shipping_country": "",
                "shipping_state": "",
                "shipping_email": "",
                "shipping_phone": "",
                "order_items": '.$orderItemsJson.',   
                "payment_method": "Prepaid",
                "shipping_charges": 0,
                "giftwrap_charges": 0,
                "transaction_charges": 0,
                "total_discount": 0,
                "sub_total": '.$grand_total.',
                "length": 10,
                "breadth": 15,
                "height": 20,
                "weight": '.$order_weight.'
            }';

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
              CURLOPT_URL => $_ENV['SHIP_ROCKET_BASE_API_URL'].$_ENV['SHIP_ROCKET_EXT_COURIER_TRACKING_URL'].'?order_id='.$order_id.'&channel_id='.$ship_channel_id,
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

            $trackingInsertId=DB::table('order_tracking')->insert($trackingData);
        }

        Cart::where('user_id', $combined_order->user_id)->delete();

        //Session::forget('club_point');
        //Session::forget('combined_order_id');
        
        foreach($combined_order->orders as $order){
            NotificationUtility::sendOrderPlacedNotification($order);
        }

        return view('frontend.order_confirmed', compact('combined_order'));
    }
}
