<?php

use Bitrix\Main\ModuleManager;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class ceteralabs_smartcaptcha extends CModule
{
    var $MODULE_ID = "ceteralabs.smartcaptcha";
    var $MODULE_VERSION;
    var $MODULE_VERSION_DATE;
    var $MODULE_NAME;
    var $MODULE_DESCRIPTION;
    var $PARTNER_NAME;
    var $PARTNER_URI;

    public function __construct()
    {
        $this->MODULE_NAME = Loc::GetMessage("CETERALABS_SMARTCAPTCHA_MODULE_NAME");
        $this->MODULE_DESCRIPTION = Loc::GetMessage("CETERALABS_SMARTCAPTCHA_MODULE_DESCRIPTION");
        $this->PARTNER_NAME = "Cetera Labs";
        $this->PARTNER_URI = "http://cetera.ru";

        $arModuleVersion = [];
        include __DIR__ . "/version.php";

        if (isset($arModuleVersion) && is_array($arModuleVersion)) {
            $this->MODULE_VERSION = $arModuleVersion["VERSION"];
            $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        }
    }

    public function DoInstall()
    {
        ModuleManager::registerModule($this->MODULE_ID);
        RegisterModuleDependences('main', 'OnPageStart', $this->MODULE_ID, '\\Ceteralabs\\SmartCaptcha\\EventHandlers\\Main', 'OnPageStart');
        RegisterModuleDependences('main', 'OnEndBufferContent', $this->MODULE_ID, '\\Ceteralabs\\SmartCaptcha\\EventHandlers\\Main', 'OnEndBufferContent');
    }

    public function DoUninstall()
    {
        UnRegisterModuleDependences('main', 'OnPageStart', $this->MODULE_ID, '\\Ceteralabs\\SmartCaptcha\\EventHandlers\\Main', 'OnPageStart');
        UnRegisterModuleDependences('main', 'OnEndBufferContent', $this->MODULE_ID, '\\Ceteralabs\\SmartCaptcha\\EventHandlers\\Main', 'OnEndBufferContent');
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }
}
