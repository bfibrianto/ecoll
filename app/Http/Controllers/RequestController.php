<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Exception;
use Session;

class RequestController extends Controller
{
    //
	private $client;
	private $clientId;
	private $clientSecret;
	private $headers;

	private $publicKeyPath;
	private $privaeKeyPath;
	private $signatureKeyPath;

	private $token;

	public function __construct()
	{
		$this->publicKeyPath = storage_path('keys\public.key');
		$this->privaeKeyPath = storage_path('keys\private.key');
		$this->signatureKeyPath = storage_path('keys/signature.key');
		$this->clientId = env('CLIENT_ID','BTNPayWeb');
		$this->clientSecret = env('CLIENT_SECRET','BTNPayWebClientSecret');

		$this->client = new Client([
			'base_uri'=>env('API_URI','192.168.173.16:8001')
		]);
	}

	public function testDecrypt(Request $request){
		$encrypt = $this->encrypt('a');
		$decrypt = $this->decrypt(str_replace(" ","",$encrypt));
		dd($encrypt,$decrypt);
	}

    public function send($data,$endpoint)
    {
    	# code...
    	$headers = [
    		'Authorization' => 'Bearer '.$this->getToken(),
    		'Content-Type' => 'application/json'
    	];

    	$body = $this->encrypt(json_encode($data));

    	dd($body,$this->decrypt($body));
    	$request = $this->client->post('/wiro/'.$endpoint,[
    		'headers' => $headers,
    		'json'=>[
    			"data"=>[$body]
    		]
    	]);

    	$response = json_decode($request->getBody()->getContents());
    	return $response;
    }

    private function isTokenExpired($token){
    	if($token){
    		$generatedTime = strtotime($token['expiredTime']);
    		$now = time();
    		if($generatedTime>$now){
    			return $token['token'];
    		}else{
    			return null;
    		}
    	}
    	return null;
    }

    public function getToken(){
    	
    	$token = $this->isTokenExpired(Session::get("token"));

    	if($token){
    		return $token;
    	}else{
    		$clientSecret = md5($this->clientSecret);
    		$request = $this->client->post('/oauth/token',[
    					'headers' => [
    						'Authorization' => 'Basic '.base64_encode($this->clientId.':'.$clientSecret),
    					],
    					'form_params' => [
    						'grant_type' => 'client_credentials',
    						'client_id' => 'BTNPayWeb',
    						'client_secret' => $clientSecret
    					]
    				]);

    		$response = json_decode($request->getBody()->getContents()); 
    		Session::put('token',['expiredTime'=>$response->data->expires_in,'token'=>$response->data->access_token]);
    		
    		return $response->data->access_token;
    	}
    }

    private function encrypt($request){
    	$fp=fopen($this->publicKeyPath,"r"); 
		$pubKey=fread($fp,8192); 
		$key = openssl_get_publickey($pubKey);
		fclose($fp); 

		$encryptProcess = openssl_public_encrypt($request,$crypttext, $pubKey); 
		
		return(base64_encode($crypttext)); 
    }

    private function decrypt($crypttext){
    	$fp=fopen($this->privaeKeyPath,"r"); 
		$privKey=fread($fp,8192); 

		$key = openssl_get_privatekey($privKey);
		fclose($fp); 

		// dd($crypttext,$privKey);
		$decryptProcess = openssl_private_decrypt($crypttext,$decrypted, $privKey ); 

		return $decrypted; 
    }

    private function sign($request){
    	$fp = fopen($this->signatureKeyPath,"r");
    	$privKey = fread($fp,8192);
    	fclos($fp);

    	$pkId = openssl_get_privatekey($privKey);

    	openssl_sign($data, $signature, $pkeyid);

    	openssl_free_key($pkId);

    	return $signature;
    }
}
