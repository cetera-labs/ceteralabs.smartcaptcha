<?php

namespace Ceteralabs\Smartcaptcha;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Web\HttpClient;

class SmartCaptcha
{
    const MODULE_ID = 'ceteralabs.smartcaptcha';

    public static function isActiveFlag()
    {
        return Option::get(self::MODULE_ID, 'active', 'Y') === 'Y';
    }

    public static function checkSmartcaptchaActive()
    {
        if (!self::isActiveFlag()) {
            return false;
        }
        return (strlen(self::getClientKey()) > 0 && strlen(self::getServerKey()) > 0);
    }

    public static function getClientKey()
    {
        return Option::get(self::MODULE_ID, 'smartcaptcha_client_key', '');
    }

    public static function getServerKey()
    {
        return Option::get(self::MODULE_ID, 'smartcaptcha_server_key', '');
    }

    public static function verify($responseToken)
    {
        try {
            if (empty($responseToken)) {
                return false;
            }

            $secret = self::getServerKey();
            if (empty($secret)) {
                return false;
            }

            $http = new HttpClient([
                'socketTimeout' => 2,
                'streamTimeout' => 2,
                'timeout'       => 2,
            ]);

            $result = $http->post('https://smartcaptcha.yandexcloud.net/validate', [
                'secret' => $secret,
                'token'  => $responseToken,
            ]);

            if ($http->getStatus() !== 200 || !$result) {
                return false;
            }

            $data = json_decode($result, true);
            return is_array($data) && ($data['status'] ?? null) === 'ok';
        } catch (\Throwable $e) {
            \CEventLog::Add([
                'SEVERITY'      => 'WARNING',
                'AUDIT_TYPE_ID' => 'CETERALABS.SMARTCAPTCHA_ERROR',
                'MODULE_ID'     => self::MODULE_ID,
                'ITEM_ID'       => self::MODULE_ID,
                'DESCRIPTION'   => $e->getMessage(),
            ]);
            return false;
        }
    }
}
