<?php

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses('ceteralabs.smartcaptcha', [
    'Ceteralabs\\SmartCaptcha\\SmartCaptcha' => 'lib/smartcaptcha.php',
    'Ceteralabs\\SmartCaptcha\\EventHandlers\\Main' => 'lib/eventhandlers/main.php',
]);
