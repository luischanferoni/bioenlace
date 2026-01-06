<?php
use frontend\assets\AppAsset;
use webvimark\modules\UserManagement\UserManagementModule;
use yii\bootstrap5\BootstrapAsset;
use yii\helpers\Html;

/* @var $this \yii\web\View */
/* @var $content string */

$this->title = UserManagementModule::t('front', 'BIOENLACE');
AppAsset::register($this);

?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>">
<head>
	<meta charset="<?= Yii::$app->charset ?>"/>
	<meta name="robots" content="noindex, nofollow">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?= Html::csrfMetaTags() ?>
	<title><?= Html::encode($this->title) ?></title>
	<?php $this->head() ?>
</head>
<body>
<?php if (getenv("SISSE_SHOW_TEST_ENVIRONMENT_WARNING") == "Y"): ?>
  <div class="alert alert-warning" role="alert">
    AMBIENTE DE PRUEBAS  --  AMBIENTE DE PRUEBAS  --  AMBIENTE DE PRUEBAS  -- 
    AMBIENTE DE PRUEBAS  --  AMBIENTE DE PRUEBAS  --  AMBIENTE DE PRUEBAS
  </div>
  <div class="alert alert-info" role="alert">
    Para ingresar al Sistema en línea siga el 
    siguiente enlace: <a href="https://sisse.msalsgo.gob.ar">BIOENLACE</a>.
  </div>
<?php endif;?>
<?php $this->beginBody() ?>

<?= $content ?>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>