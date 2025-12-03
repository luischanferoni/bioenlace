<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\data\SqlDataProvider;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\PersonaBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Listado de Recursos Humanos';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="persona-index">

    <h1><?= Html::encode($this->title) ?></h1>
<!--    <p>
        <?php //echo Html::a('Agregar Persona', ['create'], ['class' => 'btn btn-success']) ?>
    </p>-->

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],
//            'id_persona',
            'apellido',
            'nombre',
            'profesion',
            'especialidad',
            'efector',
            'servicio',
            'condicion',            
            [
                 'attribute'=>'Agenda',
                 'format'=>'raw',
                 'value' => function($data) {
                    return  Html::a('<span class="glyphicon glyphicon-time" aria-hidden="true"></span>', ['agenda-rrhhs/create/', 'id' => $data['id_rr_hh']]);
                                
                            }
             ],
            [
                 'attribute'=>'Usuario',
                 'format'=>'raw',
                 'value' => function($data) {
                                //Controla si tiene asignado un id de usuario
                                if($data['id_user']!=0){
                                    $persona = new common\models\Persona();
                                    //si tiene id de usuario asignado, consulta el nombre
                                    $nombre_usuario = $persona->user->username;
                                    //devuelve link para editar el usuario
                                    return  Html::a($nombre_usuario, ['user-management/user/update', 'id' => $data['id_user']]);
                                    }
                                    else{
                                        //si no tiene id de usuario asignado, devuelve link para crear usuario
                                        return  Html::a('Asignar Usuario', ['user/crear', 'id' => $data['id_persona']]);
                                    }
                            
                            }
            ],
            ['class' => 'yii\grid\ActionColumn', 
                            'template' => '{deleterrhh}',
                            'buttons'=>[
                              'deleterrhh' => function ($url, $model) {     
                                return Html::a('<span class="glyphicon glyphicon-trash"></span>', $url, [
                                        'title' => Yii::t('yii', 'Eliminar'),
                                        // 'data'=>[
                                        //     'method'=>'post',
                                        //     'confirm'=>'Esta seguro?',
                                        //     'params'=>[
                                        //         'id'=>$model['id_rr_hh'],
                                        //     ],
                                        //     'success'=>''
                                        // ]                                        
                                        'onClick'=>"
                                        if(confirm('Esta seguro?')){                                            
                                             $.ajax({
                                                type     :'POST',
                                                cache    : false,
                                                url  : '".$url."',
                                                data : {id:".$model['id_rr_hh']."},
                                                success  : function(response) {
                                                  $('body').append('<div class=\"alert alert-success\" role=\"alert\">'
                                                                      +'RRHH eliminado correctamente</div>'); 
                                                  window.setTimeout(function() { $('.alert').alert('close'); }, 3000);                                                   
                                                }
                                            });
                                            $('#w0').yiiGridView('applyFilter');
                                        }return false;",                                        
                                ]);                                
            
                              }
                            ],
                            'urlCreator' => function ($action, $model, $key, $index) {
                                if ($action === 'deleterrhh') {
                                    $url = \yii\helpers\Url::toRoute(['personas/deleterrhh']);
                                    //$url = Yii::$app->controller->createUrl('personas/deleterrhh'); // your own url generation logic
                                    return $url;
                                }
                            }                            
            ],
        ],
    ]); ?>

</div>
