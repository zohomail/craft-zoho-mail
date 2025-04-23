<?php


namespace zohomail\craftzohomail\mail;

use AsyncAws\Ses\SesClient;
use Craft;
use craft\behaviors\EnvAttributeParserBehavior;
use craft\helpers\App;
use craft\mail\transportadapters\BaseTransportAdapter;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use zohomail\craftzohomail\Helper\ZohoMailApi;
use Symfony\Component\Mailer\Exception\HttpTransportException;

/**
 * @property-read null|string $settingsHtml
 */
class ZohoMailAdapter extends BaseTransportAdapter
{
    public function __construct(array $config = [])
    {
        parent::__construct($config);
    }
    
    public const REGIONS = [
       'zoho.com',
       'zoho.eu',
       'zoho.com.cn',
       'zoho.in',
       'zoho.com.au',
       'zoho.jp',
       'zoho.sa',
       'zohocloud.ca'
    ];

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return 'Zoho Mail';
    }




    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        
        return Craft::$app->getView()->renderTemplate('zoho-mail/_settings', [
            'adapter' => $this,
            'regions' => self::REGIONS,
            
        ]);
    }
   /**
     * @inheritdoc
     */
    public function defineTransport(): array|AbstractTransport
    {
        
        $transport = new ZohoMailTransport();
        return $transport;
    }


     /**
     * @inheritdoc
     */
    public function validate($attributeNames = null, $clearErrors = true) {
         $zmailSettings = Craft::$app->getProjectConfig()->get("zohomail.settings");
        if(!isset($zmailSettings)){
            Craft::error('Please configure zoho mail settings before proceed ', __METHOD__);
            return false;
        }
        return true;
        
    }
    
}
