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
            || (strtolower((string)$request->getHeader('X-Requested-With')) === 'xmlhttprequest');
    }

    protected static function responseContentType(): string
    {
        $contentType = '';

        foreach (array_reverse(headers_list()) as $header) {
            if (stripos($header, 'Content-Type:') === 0) {
                $contentType = trim(substr($header, strlen('Content-Type:')));
                break;
            }
        }

        if ($contentType === '') {
            try {
                $response = Application::getInstance()->getContext()->getResponse();
                $value = $response->getHeaders()->get('Content-Type');

                if (is_array($value)) {
                    $value = end($value);
                }

                $contentType = trim((string) $value);
            } catch (\Throwable $e) {
                return '';
            }
        }

        return strtolower(trim(explode(';', $contentType, 2)[0]));
    }

    protected static function isExplicitNonHtmlResponse(): bool
    {
        $contentType = self::responseContentType();

        return $contentType !== '' && !in_array($contentType, ['text/html', 'application/xhtml+xml'], true);
    }

    protected static function isLazyLoadEnabled(): bool
    {
        return Option::get(self::MODULE_ID, 'smartcaptcha_lazy_load', 'N') === 'Y';
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

    protected static function lazyInlineInit(string $bxId = ''): string
    {
        $rootExpr = $bxId ? 'd.getElementById("comp_' . \CUtil::JSEscape($bxId) . '")||d' : 'd';

        $js = <<<'JS'
        <script data-skip-moving="true">
        (function (currentWindow) {
            var w = currentWindow;

            try {
                if (currentWindow.parent && currentWindow.parent.document) {
                    w = currentWindow.parent;
                }
            } catch (e) {
                w = currentWindow;
            }

            var d = w.document;

            function createManager() {
                var apiPromise = null;
                var observer = null;
                var callbackName = 'ceteralabsSmartCaptchaReady';

                function apiIsReady() {
                    return w.smartCaptcha && typeof w.smartCaptcha.render === 'function';
                }

                function loadApi() {
                    if (apiIsReady()) {
                        return Promise.resolve();
                    }

                    if (apiPromise) {
                        return apiPromise;
                    }

                    apiPromise = new Promise(function (resolve, reject) {
                        w[callbackName] = function () {
                            if (apiIsReady()) {
                                resolve();
                            } else {
                                apiPromise = null;
                                reject(new Error('SmartCaptcha API is unavailable'));
                            }
                        };

                        var script = d.querySelector('script[data-ceteralabs-smartcaptcha-api]');

                        if (script) {
                            return;
                        }

                        script = d.createElement('script');
                        script.src = 'https://smartcaptcha.yandexcloud.net/captcha.js?render=onload&onload=' + callbackName;
                        script.async = true;
                        script.defer = true;
                        script.setAttribute('data-ceteralabs-smartcaptcha-api', 'Y');
                        script.onerror = function () {
                            apiPromise = null;

                            if (script.parentNode) {
                                script.parentNode.removeChild(script);
                            }

                            reject(new Error('SmartCaptcha API loading failed'));
                        };

                        (d.head || d.documentElement).appendChild(script);
                    });

                    return apiPromise;
                }

                function render(container) {
                    if (!container || !d.documentElement.contains(container)) {
                        return;
                    }

                    var state = container.getAttribute('data-ceteralabs-smartcaptcha-state');

                    if (state === 'loading' || state === 'rendered') {
                        return;
                    }

                    if (container.querySelector('iframe')) {
                        container.setAttribute('data-ceteralabs-smartcaptcha-state', 'rendered');
                        return;
                    }

                    var siteKey = container.getAttribute('data-sitekey');

                    if (!siteKey) {
                        return;
                    }

                    container.setAttribute('data-ceteralabs-smartcaptcha-state', 'loading');

                    loadApi().then(function () {
                        if (!d.documentElement.contains(container)) {
                            return;
                        }

                        if (!container.querySelector('iframe')) {
                            w.smartCaptcha.render(container, {sitekey: siteKey});
                        }

                        container.setAttribute('data-ceteralabs-smartcaptcha-state', 'rendered');
                    }).catch(function () {
                        if (d.documentElement.contains(container)) {
                            container.removeAttribute('data-ceteralabs-smartcaptcha-state');
                        }
                    });
                }

                function isNearViewport(container) {
                    if (!container.getClientRects().length) {
                        return false;
                    }

                    var rect = container.getBoundingClientRect();
                    var viewportHeight = w.innerHeight || d.documentElement.clientHeight;

                    return rect.bottom >= -200 && rect.top <= viewportHeight + 200;
                }

                function getContainers(root) {
                    var containers = [];

                    if (!root) {
                        return containers;
                    }

                    if (root.nodeType === 1 && typeof root.matches === 'function' && root.matches('.smart-captcha')) {
                        containers.push(root);
                    }

                    if (typeof root.querySelectorAll === 'function') {
                        var found = root.querySelectorAll('.smart-captcha');

                        for (var i = 0; i < found.length; i++) {
                            containers.push(found[i]);
                        }
                    }

                    return containers;
                }

                function renderScope(scope) {
                    var containers = getContainers(scope);

                    for (var i = 0; i < containers.length; i++) {
                        render(containers[i]);
                    }
                }

                function register(root) {
                    var containers = getContainers(root || d);

                    for (var i = 0; i < containers.length; i++) {
                        var container = containers[i];

                        if (container.getAttribute('data-ceteralabs-smartcaptcha-registered') === 'Y') {
                            continue;
                        }

                        container.setAttribute('data-ceteralabs-smartcaptcha-registered', 'Y');

                        if (isNearViewport(container)) {
                            render(container);
                        } else if (observer) {
                            observer.observe(container);
                        }
                    }
                }

                if ('IntersectionObserver' in w) {
                    observer = new w.IntersectionObserver(function (entries) {
                        for (var i = 0; i < entries.length; i++) {
                            if (!entries[i].isIntersecting) {
                                continue;
                            }

                            observer.unobserve(entries[i].target);
                            render(entries[i].target);
                        }
                    }, {rootMargin: '200px 0px'});
                }

                function handleInteraction(event) {
                    var target = event.target;

                    if (!target || typeof target.closest !== 'function') {
                        return;
                    }

                    var scope = target.closest('form');

                    if (!scope) {
                        scope = target.closest('[data-reveal],.reveal');
                    }

                    if (scope) {
                        renderScope(scope);
                    }
                }

                d.addEventListener('focusin', handleInteraction, true);
                d.addEventListener('pointerdown', handleInteraction, true);

                if (w.BX && typeof w.BX.addCustomEvent === 'function') {
                    w.BX.addCustomEvent('onAjaxSuccess', function () {
                        register(d);
                    });
                }

                return {
                    register: register,
                    render: render
                };
            }

            var manager = w.CeteralabsSmartCaptchaLazy;

            if (!manager) {
                manager = createManager();
                w.CeteralabsSmartCaptchaLazy = manager;
            }

            var root = __CETERALABS_SMARTCAPTCHA_ROOT__;
            manager.register(root);
        })(window);
        </script>
        JS;

        return str_replace('__CETERALABS_SMARTCAPTCHA_ROOT__', $rootExpr, $js);
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

        if (self::isExplicitNonHtmlResponse()) {
            return;
        }

        $replacementCount = 0;

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
            $content,
            -1,
            $replacementCount
        );

        if ($replacementCount === 0) {
            return;
        }

        $label       = trim(Option::get(self::MODULE_ID, 'smartcaptcha_label', '')) ?: Loc::getMessage('CETERALABS_SMARTCAPTCHA_LABEL');
        $defaultErrs = @unserialize(Loc::getMessage('CETERALABS_SMARTCAPTCHA_DEFAULT_ERRORS'), ['allowed_classes' => false]);

        if (!is_array($defaultErrs)) {
            $defaultErrs = [];
        }

        $customErr   = self::errorText();

        $content = preg_replace('/<img[^>]+captcha\.php[^>]+>/i', '', $content);
        $safeLabel = htmlspecialcharsbx($label);
        $safeCustomErr = htmlspecialcharsbx($customErr);

        $content = preg_replace_callback(
            '/Введите[^<]*(картинке|символы)[^<]*/iu',
            static function () use ($safeLabel) {
                return $safeLabel;
            },
            $content
        );

        $content = str_replace($defaultErrs, $safeCustomErr, $content);

        $isAjax = self::isAjaxRequest();

        if (self::isLazyLoadEnabled()) {
            $style = '<style data-skip-moving="true">.smart-captcha{display:block;min-height:102px;}' .
                'td .smart-captcha{min-height:102px;line-height:normal;}' .
                '.smart-captcha[style*="height: 0px"]{height:auto!important;min-height:102px!important}</style>';
            $lazyInit = self::lazyInlineInit($isAjax ? self::currentBxAjaxId() : '');

            if ($isAjax) {
                $content = $style . $lazyInit . $content;
            } elseif (stripos($content, '</head>') !== false) {
                $content = preg_replace('/<\/head>/i', ($style . $lazyInit) . '</head>', $content, 1);
            } else {
                $content .= $style . $lazyInit;
            }

            return;
        }

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
