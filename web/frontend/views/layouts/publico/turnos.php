<?php

/* @var $this \yii\web\View */
/* @var $content string */

use yii\helpers\Html;

use yii\widgets\Breadcrumbs;
use frontend\assets\AppAsset;
use common\models\Mensajes;
use common\models\Referencia;
use yii\helpers\Url;

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
            <nav class="nav navbar navbar-expand-xl navbar-light iq-navbar">
                <div class="container-fluid navbar-inner">
                    <div class="d-flex justify-content-between w-100 align-items-center">
                        <div class="float-end">
                            <div class="logo-main">
                                <div class="logo-normal">
                                    <img class="img-fluid" src="<?php echo Yii::getAlias('@web').'/'?>images/logo_ministerio_salud_20.png">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="content-inner pb-0 container" id="page_layout">
                <?= $content ?>
            </div>

            <footer class="footer">
                <div class="footer-body">
                    <ul class="left-panel list-inline mb-0 p-0">
                        <p>© SISSE <?=date("Y")?> - MINISTERIO DE SALUD DE SANTIAGO DEL ESTERO</p>
                    </ul>
                    <div class="right-panel">
                        <img src="<?= Yii::getAlias('@web') ?>/images/logoSD.png" width="100px" alt="">
                    </div>
                </div>
            </footer>
        </main>
    </div>
<?php /*?>
<div class="wrap">    
    <div class="clearfix"></div>
    <div class="container">
        <div class="row">
            <div class="col-lg-6 col-xs-6">
                <img class="img-responsive" src="<?php echo Yii::getAlias('@web').'/'?>images/rsz_logo_ceamm.png">
            </div>            
            <div class="col-lg-6 col-xs-6" style="padding-top: 20px;">        
                <img class="img-responsive" src="<?php echo Yii::getAlias('@web').'/'?>images/rsz_logo_ministerio_salud.png">
            </div>
        </div>
        <hr>
        <?= $content ?>        
    </div>
</div>

<footer class="footer">
    <div class="container">
        <p class="pull-left"></p>

        <p class="pull-right"><?php echo 'Ministerio de Salud de Santiago del Estero'; ?></p>
    </div>
</footer>

<?php */ $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
