<?php

/* @var $this \yii\web\View */
/* @var $content string */

use yii\helpers\Html;
use frontend\assets\AppAsset;

AppAsset::register($this);

?>

<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>">

<head>
    <meta charset="<?= Yii::$app->charset ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?= Html::csrfMetaTags() ?>
    <title><?= Html::encode($this->title) ?></title>

    <?php $this->head() ?>
</head>
<body class="boxed light theme-color-default">
    <?php $this->beginBody() ?>

    <?php if (!Yii::$app->user->isGuest): ?>
    <script>
    // Debe definirse ANTES de scripts POS_END (registerJs) para que AJAX incluya Bearer.
    window.userPerTabConfig = <?= \yii\helpers\Json::encode(Yii::$app->user->getPerTabSessions()) ?>;
    window.apiAuthToken = <?= json_encode(Yii::$app->session->get('apiJwtToken')) ?>;
    </script>
    <?php endif; ?>

    <script>
    // Helpers globales para consumir /api/v1 (web y wizard post-login)
    window.spaConfig = {
        baseUrl: '<?= rtrim(Yii::$app->urlManager->createAbsoluteUrl(['/']), '/') ?>',
        csrfToken: '<?= Yii::$app->request->csrfToken ?>',
        appVersion: <?= json_encode(Yii::$app->params['spaWebAppVersion'] ?? '1.0.0', JSON_UNESCAPED_UNICODE) ?>
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

    <div class="boxed-inner">
        <main class="main-content">
            <div class="content-inner pb-0 container" id="page_layout">
                <?= $content ?>
            </div>
        </main>
    </div>
    <?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
