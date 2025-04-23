<?php

namespace zohomail\craftzohomail;

use Craft;
use craft\base\Plugin;
use craft\base\Model;
use craft\services\Plugins;
use craft\events\RegisterComponentTypesEvent;
use craft\helpers\MailerHelper;
use yii\base\Event;
use craft\services\Email;
use craft\mail\MailTransportType;
use craft\events\ModelEvent;
use craft\web\UrlManager;
use craft\helpers\UrlHelper;
use craft\events\RegisterUrlRulesEvent;
use zohomail\craftzohomail\models\Settings;
use zohomail\craftzohomail\controllers\ZohoMailController;
use zohomail\craftzohomail\mail\ZohoMailAdapter;
use zohomail\craftzohomail\migrations\Install;
use craft\web\Controller;
use yii\web\Response;
/**
 * Zoho Mail plugin
 *
 * @method static ZohoMail getInstance()
 * @method Settings getSettings()
 * @author Zoho Mail <support@zohomail.com>
 * @copyright Zoho Mail
 * @license MIT
 */
class ZohoMail extends Plugin
{
    public string $schemaVersion = '1.0.0';
    /**
     * @inheritdoc
     */
    public bool $hasCpSettings = true;

    public const CMS_ZOHO_HANDLER = 'zoho-mail';
    /**
     * @inheritdoc
     */
    public bool $hasCpSection = true;
    public static ZohoMail $plugin;


    public function init(): void
    {
        parent::init();
        \Yii::setAlias('@zohomailplugin', __DIR__);
        $request = Craft::$app->getRequest();
        $this->_registerCpRoutes();

        $eventType = MailerHelper::EVENT_REGISTER_MAILER_TRANSPORT_TYPES;

        Event::on(MailerHelper::class, $eventType,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = ZohoMailAdapter::class;  
            }
        );
    }

       /**
     * @inheritdoc
     */
    public function getCpNavItem(): ?array
    {
        $ret = parent::getCpNavItem();

        $ret['label'] = Craft::t(self::CMS_ZOHO_HANDLER, 'Zoho Mail');
        $ret['url'] = 'zohomail';
        return $ret;

    }

    private function _registerCpRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules['zohomail'] = self::CMS_ZOHO_HANDLER.'/zoho-mail/index';
            $event->rules['POST zohomail/configureoauth'] = self::CMS_ZOHO_HANDLER.'/zoho-mail/configure-oauth';
            $event->rules['zohomail/callback'] = self::CMS_ZOHO_HANDLER.'/zoho-mail/call-back';
            $event->rules['POST zohomail/saveEmail'] = self::CMS_ZOHO_HANDLER.'/zoho-mail/save-mail';
            $event->rules['POST zohomail/testmail'] = self::CMS_ZOHO_HANDLER.'/zoho-mail/test-mail';
        });
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    public function getSettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('zohomail'));
    }

    public function install():void{
        parent::install();
        $install = new Install();
        $install->up();
    }

 
}
