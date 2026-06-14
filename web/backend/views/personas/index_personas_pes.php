<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\PersonaBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Profesionales (asignaciones PES)';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="persona-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],
            'apellido',
            'nombre',
            'profesion',
            'especialidad',
            'efector',
            'servicio',
            'condicion',
            [
                'attribute' => 'Agenda',
                'format' => 'raw',
                'value' => static function ($data) {
                    return Html::tag(
                        'span',
                        '<span class="glyphicon glyphicon-time text-muted" aria-hidden="true"></span>',
                        [
                            'title' => 'La agenda se configura desde el asistente o la API /api/v1/profesional-agenda.',
                        ]
                    );
                },
            ],
            [
                'attribute' => 'Usuario',
                'format' => 'raw',
                'value' => function ($data) {
                    if ($data['id_user'] != 0) {
                        $persona = new common\models\Person\Persona();
                        $nombre_usuario = $persona->getNombredeusuario($data['id_user']);

                        return Html::a($nombre_usuario, ['user-management/user/update', 'id' => $data['id_user']]);
                    }

                    return Html::a('Asignar Usuario', ['user/crear', 'id' => $data['id_persona']]);
                },
            ],
            [
                'class' => 'yii\grid\ActionColumn',
                'template' => '{deletepes}',
                'buttons' => [
                    'deletepes' => function ($url, $model) {
                        $idEliminar = (int) ($model['id_pes'] ?? $model['id'] ?? 0);
                        if ($idEliminar <= 0) {
                            return '';
                        }

                        return Html::a('<span class="glyphicon glyphicon-trash"></span>', $url, [
                            'title' => Yii::t('yii', 'Eliminar'),
                            'onClick' => "
                                        if(confirm('¿Eliminar todas las asignaciones PES de esta persona en este efector?')){
                                             $.ajax({
                                                type     :'POST',
                                                cache    : false,
                                                url  : '".$url."',
                                                data : {id:" . $idEliminar . "},
                                                success  : function(response) {
                                                  $('body').append('<div class=\"alert alert-success\" role=\"alert\">'
                                                                      +'Asignaciones eliminadas correctamente</div>');
                                                  window.setTimeout(function() { $('.alert').alert('close'); }, 3000);
                                                }
                                            });
                                            $('#w0').yiiGridView('applyFilter');
                                        }return false;",
                        ]);
                    },
                ],
                'urlCreator' => function ($action, $model, $key, $index) {
                    if ($action === 'deletepes') {
                        return \yii\helpers\Url::toRoute(['personas/eliminar-asignaciones-pes-en-efector']);
                    }
                },
            ],
        ],
    ]); ?>

</div>
