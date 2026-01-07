<?php

namespace zohomail\craftzohomail\controllers;

use Craft;
use craft\web\Controller;
use zohomail\craftzohomail\Helper\ZohoMailApi;
use zohomail\craftzohomail\Helper\ZConstants;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use zohomail\craftzohomail\assets\ZohoMailAssetBundle;
use zohomail\craftzohomail\services\ZohoMailConfigService;
use yii\web\Response;
class ZohoMailController extends Controller
{
    
    public function actionConfigureOauth()
    {
        $this->requirePostRequest();
        $this->requireAdmin();

        $respObj = json_decode("{}",true);

        $domain = Craft::$app->getRequest()->getBodyParam('domain');
        $client_id = Craft::$app->getRequest()->getBodyParam('client_id');
        $client_secret = Craft::$app->getRequest()->getBodyParam('client_secret');
        $callbackUrl = $this->getCallBackUrl();
        $state=base64_encode($client_id."::".$client_secret."::".$domain);
        $respObj["authorize_url"] = "https://accounts.".$domain."/oauth/v2/auth?client_id=".$client_id."&response_type=code&access_type=offline&prompt=consent&redirect_uri=".$callbackUrl."&state=".$state."&scope=".ZConstants::mail_scopes;
        $respObj["result"] = "success";
        return $this->asJson($respObj);
				
       
    }
    public function actionCallBack() 
    {
        
        $this->requireAdmin();
        $respObj = json_decode("{}",true);
        $authCode =  Craft::$app->getRequest()->getQueryParam('code');
        $encoded_state =  Craft::$app->getRequest()->getQueryParam('state');

        $respObj["result"] = "success";
        $state=explode("::",base64_decode($encoded_state));
        
        $clientId = $state[0];
        $clientSecret = $state[1];
        $domain = $state[2];

        $completeRedirectUrl = $this->getCallBackUrl();
        $url = "https://accounts.".ZConstants::$domains[$domain]."/oauth/v2/token?code=".$authCode."&client_id=".$clientId."&client_secret=".$clientSecret."&redirect_uri=".$completeRedirectUrl."&grant_type=authorization_code&state=".$encoded_state;
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
        if($httpcode == '200') {
            $body = json_decode($response,true);
            if(array_key_exists('error',$body)){
                $respObj["result"] = "error";
                $respObj["message"] = json_decode($response);
            }
            else
            {
                $zmailConfigService = new ZohoMailConfigService();
                $authData =[
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'refresh_token' => $body['refresh_token'],
                    'access_token' => $body['access_token'],
                    'redirect_url' => $completeRedirectUrl,
                    'domain' => $domain,
                    'updated_at' => time(),
                    'created_at' => time()
                ];
                $zmailConfigService->addConfig($authData);
                $respObj["result"] = "success";
            }
        }
        return $this->asHtml("<script>window.opener.postMessage(".json_encode($respObj).");window.close();</script>");
    }

    public function actionSaveMail() 
    {
        $this->requirePostRequest();
        $this->requireAdmin();

        $respObj = json_decode("{}",true);
        $from_address = Craft::$app->getRequest()->getBodyParam('from_address');
        $from_name = Craft::$app->getRequest()->getBodyParam('from_name');
        $zmailApi = new ZohoMailApi();
        $emailDetail = $zmailApi->getZohoMailAccountDetails();
       
        $isAccountConfigured = false;
        foreach ($emailDetail as $accountId => $emailIdList){
            if(in_array($from_address,$emailIdList))
            {
                Craft::$app->getProjectConfig()->set("zohomail.settings", [
                    'account_id' => $accountId,
                    'from_name' => $from_name,
                    'from_address' => $from_address,
                    'allowed_emails' => $emailIdList
                ]);
                $isAccountConfigured = true;
                break;
            }
        }
        if($isAccountConfigured){
            $respObj["result"] = "success";
        }
        else {
            $respObj["result"] = "failure";
            $respObj["reason"] = "from address is not available for user";
            $respObj["from_address"] = $from_address;
            $respObj["emaild_list"] = $emailIdList;
        }
        
        return $this->asJson($respObj);
    }
    
    public function actionTestMail()
    {
        $this->requirePostRequest();
        $this->requireAdmin();
        $zmailSettings = Craft::$app->getProjectConfig()->get("zohomail.settings");

        if(!isset($zmailSettings)) {
            return $this->asJson(['result' => 'failure', 'message' => 'Please configure mail settings']);
        }
        $zmailConfig = new ZohoMailConfigService();
        $configValue = $zmailConfig->getConfig();

        
        $fromEmail =  $zmailSettings['from_address'];
        $fromName =  $zmailSettings['from_name'];
        $accountId = $zmailSettings['account_id'];



        $zohoMailApi = new ZohoMailApi();
        $zohoMailApi->accountId = $accountId;
        $json = $this->getTestPayload($fromEmail,$fromName);
        
        try {
            $zohoMailApi->sendZohoMail($json);

            return $this->asJson(['result' => 'success', 'message' => 'Action completed']);
        }
        catch(HttpTransportException $httpTransportException){
            Craft::error('HttpTransportException occurred while sending email: ' . $httpTransportException->getMessage(), __METHOD__);
            return $this->asJson(['result' => 'failure', 'message' => $httpTransportException->getMessage(),'json' => $json]);
        }
       
    }
    public function actionIndex()
    {
        $this->requireAdmin();
        $zmailSettings = Craft::$app->getProjectConfig()->get("zohomail.settings");
        $data = array();
        $zmailConfig = new ZohoMailConfigService();
        $configValue = $zmailConfig->getConfig();
        $is_account_configured = false;
        $is_mail_configured = false;
        $from_address = '';
        $account_id = '';
        $from_name = '';
        $emailIdList = [];
        if(!isset($configValue)) {
            $data['clientId'] = '';
            $data['clientSecret'] = '';
            $data['domain'] = 'zoho.com';

        } 
        else {
            $data['clientId'] = $configValue['client_id'];
            $data['clientSecret'] = base64_decode($configValue['client_secret']);
            $data['domain'] = $configValue['domain'];
            $is_account_configured = true;
        }
        if($is_account_configured) {
            $zmailApi = new ZohoMailApi();
            if(isset($zmailSettings["account_id"])) {
                $account_id = $zmailSettings["account_id"];
            }
            
            $emailDetail = $zmailApi->getZohoMailAccountDetails();
            
            if(empty($emailDetail)) {
                $is_account_configured =false;
                $is_mail_configured = false;
            }
            else if(!isset($account_id) || (isset($account_id) && !array_key_exists($account_id,$emailDetail)) ){
               $is_mail_configured = false;
               foreach($emailDetail as $emailAccountId => $accountEmailList) {
                foreach ($accountEmailList as $email) {
                    $emailIdList[] = $email;
                }
                 
               }

            }
            else{
                $emailIdList = $emailDetail[$account_id];
                if(count($emailIdList) == 0) {
                    $is_mail_configured = false;
                    $is_account_configured = false;
                }else {
                    $is_account_configured = true;
                    if(isset($zmailSettings['from_address'])) {
                        $from_address = $zmailSettings['from_address'];
                    }
                    if(in_array($from_address,$emailIdList)) {
                        $is_mail_configured = true;
                        $from_name = $zmailSettings['from_name'];
                    }
                }
            }
            
        }
        $data['account_id'] = $account_id;
        $data['email_list'] = $emailIdList;
        $data['from_name'] = $from_name;
        $data['from_address'] = $from_address;
        $data['is_account_configured'] = $is_account_configured;
        $data['is_mail_configured'] = $is_mail_configured;
        $data['callbackurl'] =   $this->getCallBackUrl();
        
       
        Craft::$app->view->registerAssetBundle(ZohoMailAssetBundle::class);
       
        $this->renderTemplate('zoho-mail/index',$data);
    }

    private function getTestPayload($fromAddress,$fromName) {
        
        
        $payload = [
            'subject' => 'Zoho Mail plugin for Craft CMS - Test Email',
            'content'  => '<html><body><p>Hello,</p><br><br><p>We\'re glad you\'re using our Zoho Mail plugin. This is a test email to verify your configuration details. 
    Thank you for choosing Zoho Mail for your business email needs.<p><br><br>Team Zoho Mail</body></html>',
            'fromAddress'     => $fromAddress,
            'toAddress'       => $fromAddress
        ];
        $payload['mailFormat'] = 'html';
        return $payload;
        
    }

    private function getCallBackUrl()
    {
        return Craft::$app->getRequest()->getHostInfo().'/'.Craft::$app->getConfig()->getGeneral()->cpTrigger.'/zohomail/callback';
    }
    public function asHtml($content)
    {
        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_HTML;
        $response->data = $content;
        return $response;
    }
}

