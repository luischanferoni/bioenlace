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
