<?php

/* @var $this \yii\web\View */
/* @var $content string */

use yii\helpers\Html;
use frontend\assets\AppAsset;
use frontend\components\Layout\SpaLayoutPageBuilder;

AppAsset::register($this);
$layout = SpaLayoutPageBuilder::build();
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Html::encode($layout['language']) ?>">
<head>
    <meta charset="<?= Html::encode($layout['charset']) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= Html::csrfMetaTags() ?>
    <title><?= Html::encode($this->title) ?></title>
    <?php $this->head() ?>
</head>
<body class="boxed light theme-color-default">
<?php $this->beginBody() ?>

<?php if (!$layout['isGuest']): ?>
<script>
window.userPerTabConfig = <?= \yii\helpers\Json::encode($layout['userPerTabConfig']) ?>;
window.apiAuthToken = <?= json_encode($layout['apiAuthToken']) ?>;
</script>
<?php endif; ?>

<script>
window.spaConfig = {
    baseUrl: '<?= $layout['spaConfig']['baseUrl'] ?>',
    csrfToken: '<?= $layout['spaConfig']['csrfToken'] ?>',
    appVersion: <?= json_encode($layout['spaConfig']['appVersion'], JSON_UNESCAPED_UNICODE) ?>
};
window.getBioenlaceApiClientHeaders = function (extra) {
    var ver = (window.spaConfig && window.spaConfig.appVersion) ? String(window.spaConfig.appVersion) : '1.0.0';
    var base = {
        'X-App-Client': 'web-frontend',
        'X-App-Version': ver,
        'X-Client': 'web'
    };
    if (window.apiAuthToken) {
        base['Authorization'] = 'Bearer ' + window.apiAuthToken;
    }
    return Object.assign(base, extra || {});
};
</script>

<?= $content ?>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
