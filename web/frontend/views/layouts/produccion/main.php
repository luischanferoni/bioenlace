<?php

/* @var $this \yii\web\View */
/* @var $content string */

use yii\helpers\Html;
use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;
use yii\widgets\Breadcrumbs;
use frontend\assets\AppAsset;
use common\models\Mensajes;
use common\models\Referencia;
use yii\helpers\Url;
use common\components\Core\Permission\BioenlaceGhostNav;
use common\components\Legacy\UserManagementCompat;

AppAsset::register($this);

if(Yii::$app->user->nombreEfector)
{
    $efector_nombre= ' - '.Yii::$app->user->nombreEfector;//.yii::$app->user->getNombreEfector();
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
</head>
<body>
<?php $this->beginBody() ?>

<div class="wrap">
    <?php
    $a = explode("/", $_SERVER['REQUEST_URI']);
    NavBar::begin([
        'brandLabel' => $a[1]=='bioenlace_prueba'?'BIOENLACE'.'<span class="label label-warning">PRUEBA</span>'.$efector_nombre:'BIOENLACE'.$efector_nombre,
        'brandUrl' => Yii::$app->homeUrl,
        'options' => [
            'class' => $a[1]=='bioenlace_prueba'?'navbar-default navbar-fixed-top':'navbar-inverse navbar-fixed-top',
        ],
    ]);
    echo Nav::widget([
        'options' => ['class' => 'navbar-nav navbar-right'],
        'items' => [
             Yii::$app->user->isGuest ?
                            ['label' => 'Ingresar', 'url' => ['/auth/login']] : '',
            
            ],
    ]);
if(Yii::$app->user->idEfector){
    //Obtengo la cantidad de referencias
    $cant = Referencia::cantidadPorEfector(Yii::$app->user->idEfector);

    echo BioenlaceGhostNav::widget([
        'options' => ['class' => 'navbar-nav navbar-right'],
        'encodeLabels' => false,
        'activateParents' => true,
        'items' => [
            //Yii::$app->user->idEfector ?[
            ['label' => 'Inicio', 'url' => ['/site/index']],
            ['label' => 'Localidades', 'url' => ['/localidades']],
            //['label' => 'Mensajes', 'url' => ['/mensajes']],
            ['label' => 'Personas', 'url' => ['/personas']],
            ['label' => 'Efectores', 'url' => ['/efectores']],
            ['label' => 'Referencias <span class="badge">'.$cant.'</span>', 'url' => ['/referencias']],
            ['label' => 'Reportes', 
                'items' => [
                    ['label' => 'Reportes Estadisticos', 'url' => ['/personas/reportesestadisticos']],
                    ['label' => 'Reportes', 'url' => ['/personas/reporte']], 
                    ['label' => 'Reportes Estadisticos Nivel Central', 'url' => ['/personas/reportesestadisticos-central']],
                    ['label' => 'Reportes Nivel Central', 'url' => ['/personas/reporte-central']],
                    ['label' => 'Atenciones de Enfermeria', 'url' => ['/atenciones_enfermeria/reporte']],
                ]
            ],
            ['label' => 'Turnos', 
                'items' => [
                    ['label' => 'Registrar', 'url' => ['/turnos/index']],
                    ['label' => 'Lista de Espera', 'url' => ['/turnos/espera']]    
                ]
            ],
            ['label' => 'Enfermería', 
                'items' => [
                    ['label' => 'Reporte mensual', 
                        'url' => ['/atenciones-enfermeria/generar-reporte'], 
                        'linkOptions' => [
                            'target' => '_blank'
                        ]]    
                ]
            ],
            [
                'label' => 'Profesionales',
                'items' => [
                    ['label' => 'Profesiones', 'url' => ['/profesiones']],
                    ['label' => 'Especialidades', 'url' => ['/especialidades']],
                    ['label' => 'Agenda laboral', 'url' => ['/personas/index-personas-pes']],
                    ['label' => 'Listado por efector (PES)', 'url' => ['/personas/index-personas-pes']],
                ],
            ],
            [
                'label' => 'Administrar',
                'items' => UserManagementCompat::adminMenuItems()
            ],
            [
                'label' => $user,
                'items' => [                   
                    ['label' => 'Modificar Contraseña', 'url' => ['/auth/change-own-password']],
                    ['label' => 'Confirmacion de email', 'url' => ['/auth/confirm-email']],
                     !Yii::$app->user->isGuest ? ['label' => 'Salir', 'url' => ['/auth/logout']]:'',
                ],
            ],
            //]:'',
        ],
    ]);
NavBar::end();
    }
    else{
        echo BioenlaceGhostNav::widget([
        'options' => ['class' => 'navbar-nav navbar-right'],
        'encodeLabels' => false,
        'activateParents' => true,
        'items' => [
            [
                'label' => 'Administrar',
                'items' => UserManagementCompat::adminMenuItems()
            ],
            [
                'label' => $user,
                'items' => [                   
                    ['label' => 'Modificar Contraseña', 'url' => ['/auth/change-own-password']],
                    ['label' => 'Confirmacion de email', 'url' => ['/auth/confirm-email']],
                     !Yii::$app->user->isGuest ? ['label' => 'Salir', 'url' => ['/auth/logout']]:'',
                ],
            ],
            //]:'',
        ],
    ]);
NavBar::end();
    }
//    Yii::$app->dbmq->send('test');
   // WebNotifications::widget(['url'=>Url::toRoute('/nfy/default/poll',['id'=>'queueComponentId'])]);
//$this->widget('nfy.extensions.webNotifications.WebNotifications', array());
    ?>

    <div class="container">    
        <?= Breadcrumbs::widget([
            'links' => isset($this->params['breadcrumbs']) ? $this->params['breadcrumbs'] : [],
        ]) ?>
        <?= $content ?>
    </div>
</div>

<footer class="footer">
    <div class="container">
        <p class="pull-left">&copy; BIOENLACE <?= date('Y') ?></p>

        <p class="pull-right"><?php echo 'Ministerio de Salud de Santiago del Estero'; ?></p>
    </div>
</footer>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
