<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\Url;
use yii\bootstrap5\Modal;
use yii\bootstrap5\Dropdown;

use webvimark\modules\UserManagement\models\User;

use common\models\Persona;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\PersonaBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Listado de personas';
$this->params['breadcrumbs'][] = $this->title;
$esAdmin = User::hasRole(['Admin']);
?>

<div class="card">
    <div class="card-body">
        <div class="custom-table-effect">
            <br>
            <?= GridView::widget([
                'dataProvider' => $dataProvider,
                'filterModel' => $searchModel,
                'tableOptions' => ['class' => 'table mb-0 dataTable table-responsive  border rounded'],
                'headerRowOptions' => ['class' => 'bg-soft-primary'],
                'filterRowOptions' => ['class' => 'bg-white'],
                'pager' => ['class' => 'yii\bootstrap5\LinkPager', 'prevPageLabel' => 'Anterior', 'nextPageLabel' => 'Siguiente', 'options' => ['class' => 'pagination justify-content-center mt-5']],
                'columns' => [
                    'apellido',
                    'otro_apellido',
                    'nombre',
                    'otro_nombre',
                    [
                        'attribute' => 'documento',
                        'label' => 'Nro. Doc',
                    ],
                    [
                        'attribute' => 'id_user',
                        'label' => 'Usuario',
                        'format' => 'raw',
                        'value' => function ($data) {
                            //Controla si tiene asignado un id de usuario
                            if ($data->id_user != 0) {
                                //si tiene id de usuario asignado, consulta el nombre
                                $persona = new Persona();
                                $nombre_usuario = $persona->user->username;
                                //devuelve link para editar el usuario
                                if ($nombre_usuario != '-')
                                    return  Html::a($nombre_usuario, ['user-management/user/update', 'id' => $data->id_user]);
                                else
                                    return  Html::a('Asignar Usuario', ['user-efector/asignarusuario', 'id' => $data->id_persona]);
                            } else {
                                //si no tiene id de usuario asignado, devuelve link para crear usuario
                                return  Html::a('Asignar Usuario', ['user-efector/asignarusuario', 'id' => $data->id_persona]);
                            }
                        },
                        'visible' => $esAdmin
                    ],
                    [
                        'class' => 'yii\grid\ActionColumn',
                        'contentOptions' => ['style' => 'width:150px;'],
                        'template' => '{view} {update} {opciones}',
                        'buttons' => [
                           /* 'view' => function ($url, $model) {
                                $url = Url::to(['paciente/historia', 'id' => $model->id_persona]);
                                return Html::a('<svg aria-hidden="true" style="display:inline-block;font-size:inherit;height:1em;overflow:visible;vertical-align:-.125em;width:1.125em" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path fill="currentColor" d="M573 241C518 136 411 64 288 64S58 136 3 241a32 32 0 000 30c55 105 162 177 285 177s230-72 285-177a32 32 0 000-30zM288 400a144 144 0 11144-144 144 144 0 01-144 144zm0-240a95 95 0 00-25 4 48 48 0 01-67 67 96 96 0 1092-71z"></path></svg>', $url);
                            },*/
                            'opciones' => function ($url, $model) {
                                $array_opciones = [
                                    [
                                        'label' => 'Coberturas',
                                        'url' => ['personas/viewpuco', 'dni' => $model->documento, 'sexo' =>  $model->sexo_biologico],
                                        'linkOptions' => [
                                            'class' => 'linkaModalGeneral',
                                            'data-title' => 'Coberturas - ' . $model->apellido . ', ' . $model->nombre,
                                        ]
                                    ],
                                    [
                                        'label' => 'Vacunas',
                                        'url' => ['personas/vacunas', 'dni' => $model->documento, 'sexo' =>  $model->sexo_biologico],
                                        'linkOptions' => [
                                            'class' => 'linkaModalGeneral',
                                            'data-title' => 'Vacunas - ' . $model->apellido . ', ' . $model->nombre,
                                        ]
                                    ],
                                ];

                                return
                                    Html::tag('span', 'Ver Más', [
                                        'id' => 'dropdownMenuButton',
                                        'class' => 'btn btn-link dropdown-toggle',
                                        'data-bs-toggle' => 'dropdown',
                                        'aria-haspopup' => 'true',
                                        'aria-expanded' => 'false'
                                    ]) . Dropdown::widget([
                                        'items' => $array_opciones,
                                        'options' => ['aria-labelledby' => 'dropdownMenuButton']
                                    ]);
                            },
                        ]
                    ],
                ],
            ]); ?>
        </div>
    </div>
</div>
<?php
Modal::begin([
    'title' => '<h4 id="modal-title"></h4>',
    'id' => 'modal-general',
    'size' => 'modal-lg',
]);
echo "<div id='modal-content'></div>";
Modal::end();
?>