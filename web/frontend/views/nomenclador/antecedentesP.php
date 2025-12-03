<?php

use yii\helpers\Html;
use yii\grid\GridView;
use webvimark\modules\UserManagement\models\User;
use common\models\Persona;
use yii\helpers\Url;
use common\models\Servicio;
use yii\helpers\ArrayHelper;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\ReferenciaBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Antecedentes Personales';
$this->params['breadcrumbs'][] = $this->title;
$esAdministrativo = User::hasRole(['Administrativo'], $superAdminAllowed = true);
$esMedico = User::hasRole(['Medico'], $superAdminAllowed = true);
$id_efector = Yii::$app->user->idEfector;
?>
<style>
    div .alert{
        position: relative !important;
    }
</style>
<div class="referencia-index">
    <div class="card">
        <div class="card-body">
            <div class="custom-table-effect">
                <h1><?= Html::encode($this->title)?></h1>

                <?php #echo $this->render('_search', ['model' => $searchModel]); ?>


                <?= GridView::widget([
                    'dataProvider' => $dataProvider,
                    'filterModel' => $searchModel,
                    'columns' => [
                        ['class' => 'yii\grid\SerialColumn'],

                        [
                            'attribute' => 'id_servicio',
                            'label' => 'Nombre',
                            'value' => function($data) {
                                return $data['nombre'];
                            },
                            'filter' => Html::activeDropDownList($searchModel, 'id_servicio',
                                ArrayHelper::map(Servicio::find()->all(),'id_servicio', 'nombre'),
                                ['class' => 'form-control',
                                    'prompt' => '- Seleccione uno -'])
                        ],

                        [
                            'label' => 'Codigo',
                            'value' => function($data) {
                                return $data['concepto'];
                            }
                        ],

                        [
                            'attribute' => 'terminos_motivos',
                            'label' => 'Descripción',
                            'value' => function ($data){
                                return $data['termino'];
                            }
                        ],
                    ],
                ]); ?>
            </div>
        </div>
    </div>
</div>

