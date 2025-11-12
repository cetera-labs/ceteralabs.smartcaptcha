<?php

namespace Ceteralabs\Smartcaptcha\EventHandlers;

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\Localization\Loc;
use Ceteralabs\Smartcaptcha\SmartCaptcha;

Loc::loadMessages(__FILE__);

class Main
{
    const MODULE_ID = 'ceteralabs.smartcaptcha';

    protected static function isAjaxRequest(?HttpRequest $request = null): bool
    {
        $request ??= Application::getInstance()->getContext()->getRequest();
        return ((string)$request->get('bxajaxid') !== '')
            || (strtolower($request->getHeader('X-Requested-With')) === 'xmlhttprequest');
    }

    protected static function currentBxAjaxId(?HttpRequest $request = null): string
    {
        $request ??= Application::getInstance()->getContext()->getRequest();
        $id = (string)$request->get('bxajaxid');
        return $id ? preg_replace('~[^a-z0-9_]~i', '', $id) : '';
    }

    protected static function errorText(): string
    {
        return trim(Option::get(self::MODULE_ID, 'smartcaptcha_error', ''))
            ?: Loc::getMessage('CETERALABS_SMARTCAPTCHA_ERROR')
            ?: 'Подтвердите, что вы не робот.';
    }

    protected static function ajaxInlineInit(string $bxId = ''): string
    {
        $rootExpr = $bxId ? 'd.getElementById("comp_' . \CUtil::JSEscape($bxId) . '")||d' : 'd';

        $css = '<style>.smart-captcha{display:block;min-height:102px}' .
            '.smart-captcha[style*="height: 0px"]{height:auto!important;min-height:102px!important}</style>';

        $js  = '<script data-skip-moving="true">(function(){' .
            'function renderAll(){var w=window.parent||window,d=w.document,sc=w.smartCaptcha;' .
            'if(!(sc&&typeof sc.render==="function")){setTimeout(renderAll,100);return;}' .
            'var root=' . $rootExpr . ';' .
            'root.querySelectorAll(".smart-captcha").forEach(function(n){' .
            'if(!n.querySelector("iframe")){var k=n.getAttribute("data-sitekey");if(k){try{sc.render(n,{sitekey:k});}catch(e){}}}' .
            '});}' .
            'setTimeout(renderAll,0);' .
            'if(window.parent&&window.parent.BX){window.parent.BX.addCustomEvent("onAjaxSuccess",function(){setTimeout(renderAll,0);});}' .
            '})();</script>';

        return $css . $js;
    }

    public static function OnPageStart()
    {
        if (defined('ADMIN_SECTION') || !SmartCaptcha::checkSmartcaptchaActive()) {
            return;
        }

        try {
            $request = Application::getInstance()->getContext()->getRequest();
            self::checkSmartCaptcha($request);
        } catch (\Throwable $e) {
            \CEventLog::Add([
                'SEVERITY'      => 'WARNING',
                'AUDIT_TYPE_ID' => 'CETERALABS.SMARTCAPTCHA_ERROR',
                'MODULE_ID'     => self::MODULE_ID,
                'ITEM_ID'       => self::MODULE_ID,
                'DESCRIPTION'   => $e->getMessage(),
            ]);
        }
    }

    protected static function checkSmartCaptcha(HttpRequest $request): bool
    {
        global $APPLICATION;

        $source     = $request->isPost() ? 'getPost' : 'getQuery';
        $captchaSid = $request->$source('captcha_sid') ?: $request->$source('captcha_code');
        $token      = $request->getPost('smart-token');

        if (!$captchaSid || !$token) {
            return true;
        }

        $ok = SmartCaptcha::verify($token);

        if (!$ok) {
            $msg = self::errorText();
            $APPLICATION->ResetException();
            $APPLICATION->ThrowException($msg);
            return false;
        }

        $connection = Application::getConnection();
        $sqlHelper  = $connection->getSqlHelper();

        $connection->queryExecute(sprintf(
            'UPDATE b_captcha SET CODE=%s WHERE ID=%s',
            $sqlHelper->convertToDbString('OK'),
            $sqlHelper->convertToDbString($captchaSid)
        ));

        $_POST['captcha_word']    = 'OK';
        $_REQUEST['captcha_word'] = 'OK';

        return true;
    }

    public static function OnEndBufferContent(&$content)
    {
        if (defined('ADMIN_SECTION') || !SmartCaptcha::checkSmartcaptchaActive()) {
            return;
        }

        $content = preg_replace_callback(
            '/<input[^>]+name\s*=\s*["\']captcha_word["\'][^>]*>/i',
            function () {
                $uid = 'smartcaptcha-' . substr(md5(uniqid('', true)), 0, 6);
                $key = SmartCaptcha::getClientKey();
                return sprintf(
                    '<div id="%s" class="smart-captcha" data-sitekey="%s"></div>',
                    $uid,
                    htmlspecialcharsbx($key)
                );
            },
            $content
        );

        $label       = trim(Option::get(self::MODULE_ID, 'smartcaptcha_label', '')) ?: Loc::getMessage('CETERALABS_SMARTCAPTCHA_LABEL');
        $defaultErrs = @unserialize(Loc::getMessage('CETERALABS_SMARTCAPTCHA_DEFAULT_ERRORS')) ?: [];
        $customErr   = self::errorText();

        $content = preg_replace('/<img[^>]+captcha\.php[^>]+>/i', '', $content);
        $content = preg_replace('/Введите[^<]*(картинке|символы)[^<]*/iu', $label, $content);
        $content = str_replace($defaultErrs, $customErr, $content);

        $isAjax = self::isAjaxRequest();

        if ($isAjax) {
            $content = self::ajaxInlineInit(self::currentBxAjaxId()) . $content;
        } else {
            $script = '<script src="https://smartcaptcha.yandexcloud.net/captcha.js" async defer></script>';
            $style  = '<style data-skip-moving="true">.smart-captcha{display:block;min-height:102px;}td .smart-captcha{min-height:102px;line-height:normal;}</style>';

            if (stripos($content, '</head>') !== false) {
                $content = preg_replace('/<\/head>/i', ($script . $style) . '</head>', $content, 1);
            } else {
                $content .= $script . $style;
            }
        }
    }
}
