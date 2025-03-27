<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Psr7\Response;

class GpayController extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function processPayment() {
    	/*$respose = Http::post('https://connect.squareupsandbox.com/v2/payments', [
    		'amount_money' => [
    			'amount' => 100,
    			'currency' => 'INR'
    		],
    		'source_id' => 'sandbox-sq0idb-Ru2VYHx6RxdpmEVGoCWkxQ'

    	])->withHeaders([
    		'Authorization' => 'Bearer ' .config('EAAAl8MNP94fiBFa9GCRIlH9l3PRumBakAEsLltlTo5gGQ3o81M51NtRi_eh6A6P'),
    		'Content-Type' => 'application/json'
    	]);

        print"<pre>";
        print_r($respose);
        exit;*/


       /* $response = new Response(200, ['Content-Type' => 'application/json']);
        $respose = Http::post('https://connect.squareupsandbox.com/v2/payments', [
            'amount_money' => [
                'amount' => 100,
                'currency' => 'INR'
            ],
            'source_id' => 'sandbox-sq0idb-Ru2VYHx6RxdpmEVGoCWkxQ'

        ]);
        $newResponse = $response->withAddedHeader('X-Custom-Header','Bearer ' .config('EAAAl8MNP94fiBFa9GCRIlH9l3PRumBakAEsLltlTo5gGQ3o81M51NtRi_eh6A6P'));*/

        /*$newResponse = $response->withAddedHeader('X-Custom-Header','Bearer ' .config('EAAAl8MNP94fiBFa9GCRIlH9l3PRumBakAEsLltlTo5gGQ3o81M51NtRi_eh6A6P'));*/

       /* $newResponse = $response->withAddedHeader([
            'X-Custom-Header' => 'Bearer ' .config('EAAAl8MNP94fiBFa9GCRIlH9l3PRumBakAEsLltlTo5gGQ3o81M51NtRi_eh6A6P'),
            'X-Another-Header' => 'application/json'
        ]);*/


/*        $post = array(
'name'=>$_POST['name'],
'amount'=>$_POST['amount'],
'email'=>$_POST['email'],
'message'=>$message);*/

$post = array('amount_money' => [
                'amount' => 100,
                'currency' => 'INR'
            ],
            'source_id' => 'sandbox-sq0idb-Ru2VYHx6RxdpmEVGoCWkxQ');

/*print"<pre>";
print_r($post);
exit;
*/
$ch = curl_init('https://connect.squareupsandbox.com/v2/payments');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_ENCODING,"");

header('Content-Type: application/json');
$data = curl_exec($ch);
echo $data;

/*        print"<pre>";
        print_r($newResponse);
        exit;*/

    }
}
