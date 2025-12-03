<?php

use yii\helpers\Html;

use common\models\Persona;

/* @var $this yii\web\View */
/* @var $model common\models\RrhhEfector */

$this->title = Yii::$app->params['title'] ? Yii::$app->params['title'] : '';
$this->params['breadcrumbs'][] = ['label' => 'Recursos humanos', 'url' => ['index']];
$this->params['breadcrumbs'][] = 'Editar';
?>

<?= $this->render('_form', [
        'modeloRrhhEfector' => $modeloRrhhEfector,
        'modelosRrhhServicios' => $modelosRrhhServicios,
        'modelosRrhhCondicionesLaborales' => $modelosRrhhCondicionesLaborales,
        'modelosAgendas' => $modelosAgendas,
        'conServiciosParaSalud' => $conServiciosParaSalud
]) ?>
