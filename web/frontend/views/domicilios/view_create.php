<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\domicilio */

//$this->title = $model->id_domicilio;

$this->params['breadcrumbs'][] = ['label' => 'Domicilios', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$persona= new \common\models\Persona();
$persona_domicilio= new \common\models\Persona_domicilio();
$d_persona= $persona_domicilio::findOne($model->id_domicilio);
$persona = $persona::findOne($d_persona->id_persona);
//$dom_activo = $d_persona->activo;
$this->title = 'Nuevo Domicilio para '.$persona->apellido.', '.$persona->nombre;
?>
<div class="domicilio-view">

    <h1><?= Html::encode($this->title) ?></h1>
   
    <p>
        <?= Html::a('Actualizar', ['update', 'id' => $model->id_domicilio , 'idp' => $d_persona->id_persona], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Eliminar', ['delete', 'id' => $model->id_domicilio], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => 'Realmente desea borrar este registro?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id_domicilio',
            'calle',
            'numero',
            'manzana',
            'lote',
            'sector',
            'grupo',
            'torre',
            'depto',
            'barrio',
            'id_localidad',
            'latitud',
            'longitud',
            'urbano_rural',
//            'usuario_alta',
//            'fecha_alta',
        ],
    ]) ?>

</div>
