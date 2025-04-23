<?php
namespace zohomail\craftzohomail\Helper;

use Craft;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use zohomail\craftzohomail\services\ZohoMailConfigService;

class ZohoMailApi {
	private $config;
	public $accountId;
	private $domain;
	private $zmailConfigService;
	
	private function getZohoMailUrl() {
		return "https://mail.".$this->domainMapping[$this->domain];
	}
	private function getAccountsUrl() {
		return "https://accounts.".$this->domainMapping[$this->domain];
	}
	public function __construct() {
		$this->zmailConfigService = new ZohoMailConfigService();
		$this->config = $this->zmailConfigService->getConfig();
		$this->domain = $this->config['domain'];
	}
	public function sendZohoMail($mail_data) {
		$client = HttpClient::create();
		$accessToken = $this->getAccessToken();
		
		try {
			if(empty($accessToken)){
				throw new HttpTransportException(sprintf('Unable to send an email (code %d).', $statusCode),"Account not configured");
			}
			$response = $client->request('POST', $this->getZohoMailUrl().'/api/accounts/'.$this->accountId.'/messages', [
				'json' => $mail_data,
				'headers' => [
					"authorization: Bearer ".$accessToken,
					'Accept' => 'application/json',
					'user-agent' => 'Craft CMS'
				]
			]);
            $statusCode = $response->getStatusCode();
            $result = $response->toArray(false);
        } catch (DecodingExceptionInterface $e) {
            throw new HttpTransportException('Unable to send an email: ' . $response->getContent(false) . sprintf(' (code %d).', $statusCode), $response);
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the Zoho Mail server.', $response, 0, $e);
        }

		if (200 !== $statusCode && 201 !== $statusCode) {
            if (isset($result['error'])) {
				Craft::$app->getSession()->setError('Email transport not configured properly.'.$result['error']);
                throw new HttpTransportException('Unable to send an email: ' .  $result['error'] , $response);
            }
			throw new HttpTransportException(sprintf('Unable to send an email (code %d).', $statusCode), $response);
        }
		return $response;
	}

	public function getZohoMailAccounts() {
		$responseObj = json_decode('{}',true);
		$urlToSend = $this->getZohoMailUrl()."/api/accounts";	
		$curl = curl_init();
		$accessToken = $this->getAccessToken();
		if(empty($accessToken)){
			$responseObj["result"] ="error";
			$responseObj["detail"]="Account not configured";
		}
		curl_setopt_array($curl, array(
				CURLOPT_URL => $urlToSend,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => "",
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 30,
				CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => "GET",
				CURLOPT_HTTPHEADER => array(
				"accept: application/json",
				"authorization: Bearer ".$accessToken,
				"cache-control: no-cache",
				"content-type: application/json",
				"User-Agent: Craft CMS"
			),
		));
		$response = curl_exec($curl);
		$err = curl_error($curl);
		curl_close($curl);
		$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		if($httpcode == '200' || $httpcode == '201') {
			$responseObj["result"] ="success";
			$responseObj["detail"]=json_decode($response);
		}else{
			$responseObj["result"] ="error";
			$responseObj["detail"]=json_decode($response);
		}
		
		return $responseObj;
	}

	private function getAccessToken() {
		if( !empty($this->config['updated_at']) && time() - $this->config['updated_at'] > 3000) {
			$url = $this->getAccountsUrl()."/oauth/v2/token?refresh_token=".base64_decode($this->config['refresh_token'])."&client_id=".$this->config['client_id']."&client_secret=".base64_decode($this->config['client_secret'])."&redirect_uri=".$this->config['redirect_url']."&grant_type=refresh_token";
			$curl = curl_init();
            curl_setopt_array($curl, array(
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => "",
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 30,
				CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => "POST"
		    ));
            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
           
            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
			
			if($httpcode == '200'){
				$body = json_decode($response);
				if(!empty($body->error)){
					return null;
				}
				else {
					$this->config = $this->zmailConfigService->updateConfig($body->access_token,$this->config['id']);
					return $body->access_token;
				}
			}
			else {
				return null;
			}
			
		}
		else{
			return base64_decode($this->config['access_token']);
		}
	}
	public function getZohoMailAccountDetails() {
		
        $accountId = '';
		$emailDetail = array();
		if(isset($this->config)) {
			$response = $this->getZohoMailAccounts();
			if($response["result"] === "success") {
				$jsonbodyAccounts = $response["detail"];
				
				for($i=0;$i<count($jsonbodyAccounts->data);$i++)
				{
					$emailData = $jsonbodyAccounts->data[$i];
					if(!empty($emailData->sendMailDetails))
					{
						$accountId = $jsonbodyAccounts->data[$i]->accountId;
						
						$emailArr = array();
						for($j=0;$j<count($jsonbodyAccounts->data[$i]->sendMailDetails);$j++) {
							array_push($emailArr,$jsonbodyAccounts->data[$i]->sendMailDetails[$j]->fromAddress);
						}
						$emailDetail[$accountId] = $emailArr;
					}
				}
			}
			

		}
        return $emailDetail;
    }
	public function uploadAttachment($attachments) {
		$accountId = $this->accountId;
		
		$boundary = uniqid();
		$eol = "\r\n"; 
		$body = '';
		foreach ($attachments as $attachment) {
			$headers = $attachment->getPreparedHeaders();
            $disposition = $headers->getHeaderBody('Content-Disposition');
            $filename = $headers->getHeaderParameter('Content-Disposition', 'filename');

            $file = [
                'content' => $attachment->getBody(),
                'name' => $filename,
                'mime_type' => $headers->get('Content-Type')->getBody()
              ];

            if ($name = $headers->getHeaderParameter('Content-Disposition', 'name')) {
                $file['name'] = $name;
            }
			$body .= '--' . $boundary . $eol;
			$body .= 'Content-Disposition: form-data; name="attach"; filename="' . $file['name'] . '"' . $eol;
			$body .= 'Content-Type: ' . $file['mime_type'] . $eol . $eol;
			$body .= $file['content'] . $eol;
		}
		$body .= '--' . $boundary . '--' . $eol;
		$headers = [
			'Content-Type: multipart/form-data; boundary=' . $boundary,
			'Content-Length: ' . strlen($body),
			"authorization: Bearer ".$this->getAccessToken(),
			"User-Agent: Craft CMS"
		];
		$context = stream_context_create([
			'http' => [
				'method' => 'POST',
				'header' => implode("\r\n", $headers),
				'content' => $body
			]
		]);
		
		$response = file_get_contents($this->getZohoMailUrl().'/api/accounts/'.$accountId.'/messages/attachments?uploadType=multipart', false, $context);
		return $response;
	}

	public $domainMapping = [
		"zoho.com"          => "zoho.com",
		"zoho.eu"           => "zoho.eu", 
		"zoho.in"           => "zoho.in", 
		"zoho.com.cn"       => "zoho.com.cn",
		"zoho.com.au"       => "zoho.com.au",
		"zoho.jp"           => "zoho.jp",
		"zohocloud.ca"      => "zohocloud.ca",
		"zoho.sa"           => "zoho.sa"
    ];

	

	
   
}
	