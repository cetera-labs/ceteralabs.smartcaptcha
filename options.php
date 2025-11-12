<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$module_id = 'ceteralabs.smartcaptcha';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    Option::set($module_id, 'smartcaptcha_client_key', trim($_POST['smartcaptcha_client_key'] ?? ''));
    Option::set($module_id, 'smartcaptcha_server_key', trim($_POST['smartcaptcha_server_key'] ?? ''));
    Option::set($module_id, 'smartcaptcha_label', trim($_POST['smartcaptcha_label'] ?? ''));
    Option::set($module_id, 'smartcaptcha_error', trim($_POST['smartcaptcha_error'] ?? ''));
    Option::set($module_id, 'active', (isset($_POST['active']) && $_POST['active'] === 'Y') ? 'Y' : 'N');

    echo '<div style="color: green; margin: 10px 0;">' . Loc::getMessage('CETERALABS_SMARTCAPTCHA_SAVED') . '</div>';
}

$publicKey = Option::get($module_id, 'smartcaptcha_client_key', '');
$secretKey = Option::get($module_id, 'smartcaptcha_server_key', '');
$label     = Option::get($module_id, 'smartcaptcha_label', '');
$errorMsg  = Option::get($module_id, 'smartcaptcha_error', '');
$active    = Option::get($module_id, 'active', 'N');
?>

<form method="post">
    <?= bitrix_sessid_post() ?>
    <table class="adm-detail-content-table edit-table">
        <tr>
            <td width="40%"><?= Loc::getMessage('CETERALABS_SMARTCAPTCHA_ACTIVE') ?>:</td>
            <td width="60%">
                <input type="checkbox" name="active" value="Y" <?= ($active === 'Y' ? 'checked' : '') ?> />
            </td>
        </tr>
        <tr>
            <td width="40%"><?= Loc::getMessage('CETERALABS_SMARTCAPTCHA_CLIENT_KEY') ?>:</td>
            <td width="60%"><input type="text" size="70" name="smartcaptcha_client_key" value="<?= htmlspecialcharsbx($publicKey) ?>"></td>
        </tr>
        <tr>
            <td width="40%"><?= Loc::getMessage('CETERALABS_SMARTCAPTCHA_SERVER_KEY') ?>:</td>
            <td width="60%"><input type="text" size="70" name="smartcaptcha_server_key" value="<?= htmlspecialcharsbx($secretKey) ?>"></td>
        </tr>
        <tr>
            <td width="40%"><?= Loc::getMessage("CETERALABS_SMARTCAPTCHA_LABEL") ?>:</td>
            <td width="60%">
                <input type="text" size="70" name="smartcaptcha_label" value="<?= htmlspecialcharsbx($label) ?>">
            </td>
        </tr>
        <tr>
            <td width="40%"><?= Loc::getMessage("CETERALABS_SMARTCAPTCHA_ERROR") ?>:</td>
            <td width="60%">
                <input type="text" size="70" name="smartcaptcha_error" value="<?= htmlspecialcharsbx($errorMsg) ?>">
            </td>
        </tr>
    </table>
    <input type="submit" value="<?= Loc::getMessage('CETERALABS_SMARTCAPTCHA_SAVE') ?>" class="adm-btn-save">
</form>
