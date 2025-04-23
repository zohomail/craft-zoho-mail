<?php

namespace zohomail\craftzohomail\services;

use Craft;
use craft\base\Component;
use craft\db\Query;

class ZohoMailConfigService extends Component
{

    
    public function addConfig($values)
    {
        $cacheKey = 'zoho_mail_config_properties';
        $clientId = $values["client_id"];
        $clientSecret = $values["client_secret"];
        $refreshToken = $values["refresh_token"];
        $accessToken = $values["access_token"];
        $domain = $values["domain"];
        $redirectUrl =  $values["redirect_url"];

        $existingSetting  = (new Query())->select('*')->from('{{%zoho_mail_config}}')
                                                      ->limit(1)
                                                      ->one();
        if($existingSetting){
            Craft::$app->db->createCommand()->update('zoho_mail_config', [
                                            'client_id' => $clientId,
                                            'client_secret' => base64_encode($clientSecret),
                                            'redirect_url' => $redirectUrl,
                                            'refresh_token' => base64_encode($refreshToken),
                                            'access_token' => base64_encode($accessToken),
                                            'domain'  => $domain,
                                            'created_at' => time(),
                                            'updated_at' => time(),
                                        ], 
                                        [
                                            'id' => $existingSetting['id'], 
                                        ])
                                        ->execute();
                                    }
        else {
            Craft::$app->db->createCommand()
                ->insert('zoho_mail_config', [
                    'client_id' => $clientId,
                    'client_secret' => base64_encode($clientSecret),
                    'redirect_url' => $redirectUrl,
                    'refresh_token' => base64_encode($refreshToken),
                    'access_token' => base64_encode($accessToken),
                    'domain'  => $domain,
                    'created_at' => time(),
                    'updated_at' => time(),
                ])
                ->execute();

        }
        Craft::$app->getCache()->delete($cacheKey);
        return $this->getConfig();
        
    }

    
    public function getConfig()
    {
        
        $cacheKey = 'zoho_mail_config_properties';
        $config = Craft::$app->getCache()->get($cacheKey);

       
        if (!$config) {
            $existingSetting  = (new Query())->select('*')
                                            ->from('{{%zoho_mail_config}}')
                                            ->limit(1)  
                                            ->one();    
            
            if ($existingSetting) {
                Craft::$app->getCache()->set($cacheKey, $existingSetting, 3600);
                return $existingSetting;
            }
            else {
                return null;
            }
        }

        return $config;
        
    }
    

    
    public function updateConfig($accessToken,$id)
    {
        Craft::$app->db->createCommand()->update('zoho_mail_config', [
            'access_token' => base64_encode($accessToken),
            'updated_at' => time(),
        ], 
        [
            'id' => $id, 
        ])
        ->execute();
        Craft::$app->getCache()->delete('zoho_mail_config_properties');
        return $this->getConfig();
    }
 
}
