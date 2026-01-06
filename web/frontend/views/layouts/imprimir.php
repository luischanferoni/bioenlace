<?php

/* @var $this \yii\web\View */
/* @var $content string */

use yii\helpers\Html;
use frontend\assets\AppAsset;

AppAsset::register($this);

if(Yii::$app->user->getNombreEfector())
{
    $efector_nombre= ' - '.Yii::$app->user->getNombreEfector();//.yii::$app->user->getNombreEfector();
}
else
{
    $efector_nombre='';
}
if(Yii::$app->user->username)
{
    $user=Yii::$app->user->username;
    
}
    else
    {
        $user='';
    }

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
    <?php $this->registerJs('var baseUrl = "' . Yii::$app->urlManager->createAbsoluteUrl(['/']) . '"', yii\web\View::POS_HEAD); ?>
    <script>
    document.addEventListener('DOMContentLoaded',function(){/*fun code to run*/
        window.print();
    });
    </script>
    <style>
        @media print    
        {    
            .no-print, .no-print *
            {
                display: none !important;
            }
        }
    </style>
</head>
<body>
<?php $this->beginBody() ?>
    

        <?= $content ?>

<footer class="footer">
        <p class="pull-left">&copy; BIOENLACE <?= date('Y') ?></p>
        <p class="pull-right"><?php echo 'Ministerio de Salud de Santiago del Estero'; ?></p>
</footer>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
