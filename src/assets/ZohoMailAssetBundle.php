<?php

namespace zohomail\craftzohomail\assets;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;  

class ZohoMailAssetBundle extends AssetBundle
{
    public function init()
    {
        $this->sourcePath = '@zohomailplugin/assets'; 
        $this->js = [
            'js/zohomail-admin-page.js', 
        ];
        $this->css = [
            'css/zohomail-admin-page.css', 
        ];
        $this->depends = [CpAsset::class]; 

        parent::init();
    }
}
