<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Providers\BniEnc;
use App\RequestLog;
use App\CallbackLog;
use Exception;

class EcollController extends Controller
{
	private $clientId;
	private $secretKey;
	private $ecollUrl;

	public function __construct(){
		$this->clientId = env("ECOLL_CLIENT_ID","0001");
		$this->secretKey = env("ECOLL_SECRET_KEY","d69600520a5cad357e9c50948b1f9f39");
		$this->ecollUrl = env("ECOLL_URL","https://apibeta.bni-ecollection.com");

	}
	public function testEncrypt(Request $request){
		$data = $request->all();

		$encrypted = $this->encrypt($data);
		$decrypted = $this->decrypt($encrypted);
		
		return response()->json(["encrypted"=>$encrypted,"decrypted"=>$decrypted,"real"=>$data]);
	}
    //

    public function newRequest(Request $request){

    	// prepare the request identity
    	$clientId = $this->clientId;
    	$timeout = env("ECOLL_TIMEOUT",30);
    	$url = $this->ecollUrl;
    	$prefix = env("ECOLL_PREFIX",8);

    	// get data from request created by Sahara
    	$data = $request->all();

    	// encrypt data using eColl encrypt library
    	$encrypted = $this->encrypt($data);

    	// Send request to eCollection
        $requestBody = [
            "client_id" => $clientId,
            "prefix" => $prefix,
            "data" => $encrypted
        ];

    	$response = $this->makeRequest($url,json_encode($requestBody));

    	// Only process the succcessfull request
    	if($response){
    		
    		$body = json_decode($response,true);
            if($body["status"] == "000"){
                // decrypt body
    		    $decryptedResponse = $this->decrypt($body["data"]);
    		    $encryptedResponse = $body["data"];
    		    $response = ["status" => $body["status"],"data" =>$decryptedResponse];
            }else{
                $decryptedResponse = $body;
                $encryptedResponse = null;
    		    $response = $body;
            }
    		
    	}else{
	    	$response = ["status"=>"991", "message" => "Internal sahara server error"];
	    	$decryptedResponse = $response;
            $encryptedResponse = null;
    	}

    

    	// Log request even it's failed
		$this->log(
			["raw"=>$data, "encrypted"=>$encrypted], // for request data
			["raw"=>$decryptedResponse, "encrypted"=>$encryptedResponse], // for response data
			$response['status'] // response status
		);

    	return response()->json($response);
    }

    public function notif(Request $request){
    	
    	$clientId = $request->client_id;
    	$encryptedData = $request->data;

    	if($clientId == $this->clientId){
    		// Decrypt data
	    	$rawData = $this->decrypt($encryptedData);
	    	if (!$rawData) {
				// handling jika waktu server salah/tdk sesuai atau secret key salah
				$response = ["status"=>"999","message"=>"waktu server tidak sesuai NTP atau secret key salah."];
			}else{
				
				$response = $this->toSahara($rawData);
				
				$save = $rawData;
				$save["encrypted_data"] = $encryptedData;
				$save["status"] = $response["status"];
				$save["status_message"] = $response["message"];

				$log = CallbackLog::insert($save);
				
				if($response["status"] == "200"){
                    $response["status"] = "000";
                }
			}

			return response()->json($response);
    	}
    	
    	return response()->json(["status"=>"992","message"=>"client id tidak sesuai"]);
    }

    private function makeFakeResponse($url){
        if($url == "https://apibeta.bni-ecollection.com/"){
            $data = [
                "trx_id" => "123124",
                "virtual_account" => "12345678902123123"
            ];

            $encrypted = $this->encrypt($data);

            $response = [
                "status" => "000",
                "data" => $encrypted
            ];

        }else{
            $response = [
                "status"=>"200",
                "message" => "notif received"
            ];
        }

        return json_encode($response);
    }

    private function toSahara($data){
    	if($data["payment_amount"] > 0){
    		$urlNotif = env("SAHARA_TOPUP_URL");
    	}else{
    		$urlNotif = env("SAHARA_DEBIT_URL");
    	}

    	$response = $this->makeRequest($urlNotif,json_encode($data));
    	if($response){
    		$body = json_decode($response,true);
    	
    	    return $body;
    		
    	}

    	return ["status" => "993", "message"=>"Error when sending notification to sahara"];
    }

    private function log($request, $response, $status){
    	$rawRequest = $request["raw"];

    	$log = new RequestLog;
    	$log->type = $rawRequest["type"];
    	$log->trx_id = $rawRequest["trx_id"];
    	$log->status = $status;
    	$log->raw_request = json_encode($rawRequest);
    	$log->encrypted_request = $request["encrypted"];
    	$log->raw_response = json_encode($response["raw"]);
    	$log->encrypted_response = $response["encrypted"];
    	$log->save();

    }

    private function encrypt($raw){
    	$client_id = $this->clientId; //client id from BNI
		$secret_key = $this->secretKey; // secret key from BNI
		
		$hashdata = BniEnc::encrypt($raw, $client_id, $secret_key);

		return $hashdata;
    }

    private function decrypt($encrypted){
    	$client_id = $this->clientId; //client id from BNI
		$secret_key = $this->secretKey; // secret key from BNI
		
		$parsedata = BniEnc::decrypt($encrypted, $client_id, $secret_key);

		return $parsedata;
    }

    private function makeRequest($url, $post = '') {

        if(env('APP_ENV')=='local' && $url == env("ECOLL_URL")){
            return $this->makeFakeResponse($url);
        }
        
        $header[] = 'Content-Type: application/json';
        $header[] = "Accept-Encoding: gzip, deflate";
        $header[] = "Cache-Control: max-age=0";
        $header[] = "Connection: keep-alive";
        $header[] = "Accept-Language: en-US,en;q=0.8,id;q=0.6";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        // curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_ENCODING, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2062.120 Safari/537.36");

        if ($post)
        {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $rs = curl_exec($ch);

        if(empty($rs)){
            var_dump($rs, curl_error($ch));
            curl_close($ch);
            return false;
        }

        curl_close($ch);
        return $rs;
    }
}
