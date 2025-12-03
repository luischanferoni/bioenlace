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
                    'nombre',
                    [
                        'attribute' => 'documento',
                        'label' => 'Nro. Doc',
                    ],
                    [
                        'class' => 'yii\grid\ActionColumn',
                        'contentOptions' => ['style' => 'width:150px;'],
                        'template' => '{view} {update} {opciones}',
                        'buttons' => [
                            'view'  => function ($url, $model) {
                                return Html::a('
                                            <svg xmlns="http://www.w3.org/2000/svg" class="icon-32" width="32" viewBox="0 0 24 24" fill="none">
                                                <path d="M22.4541 11.3918C22.7819 11.7385 22.7819 12.2615 22.4541 12.6082C21.0124 14.1335 16.8768 18 12 18C7.12317 18 2.98759 14.1335 1.54586 12.6082C1.21811 12.2615 1.21811 11.7385 1.54586 11.3918C2.98759 9.86647 7.12317 6 12 6C16.8768 6 21.0124 9.86647 22.4541 11.3918Z" fill="currentColor"></path>
                                                <path d="M12 17C14.7614 17 17 14.7614 17 12C17 9.23858 14.7614 7 12 7C9.23858 7 7 9.23858 7 12C7 14.7614 9.23858 17 12 17Z" fill="white"></path>
                                                <path d="M12 15C13.6569 15 15 13.6569 15 12C15 10.3431 13.6569 9 12 9C10.3431 9 9 10.3431 9 12C9 13.6569 10.3431 15 12 15Z" fill="currentColor"></path>
                                                <mask id="mask0_18_1017" style="mask-type:alpha" maskUnits="userSpaceOnUse" x="9" y="9" width="6" height="6">
                                                <path d="M12 15C13.6569 15 15 13.6569 15 12C15 10.3431 13.6569 9 12 9C10.3431 9 9 10.3431 9 12C9 13.6569 10.3431 15 12 15Z" fill="currentColor"></path>
                                                </mask>
                                                <g mask="url(#mask0_18_1017)">
                                                <path opacity="0.89" d="M13.5 12C14.3284 12 15 11.3284 15 10.5C15 9.67157 14.3284 9 13.5 9C12.6716 9 12 9.67157 12 10.5C12 11.3284 12.6716 12 13.5 12Z" fill="white" fill-opacity="0.6"></path>
                                                </g>
                                            </svg>', 
                                            ['personas/admin-view', 'id' => $model->id_persona]);
                            },                            
                            'opciones' => function ($url, $model) {
                                $array_opciones = [
                                    [
                                        'label' => 'Consultas',
                                        'url' => ['consultas/historialconsultas', 'id' => $model->id_persona],
                                        'linkOptions' => [
                                            'class' => 'modalGeneral',
                                            'data-title' => 'Historial de Consultas',
                                        ]
                                    ],
                                    [
                                        'label' => 'Antecedentes',
                                        'url' => ['consultas/historialantecedentes', 'id' => $model->id_persona],
                                        'linkOptions' => [
                                            'class' => 'modalGeneral',
                                            'data-title' => 'Historial de Antecedentes',
                                        ]
                                    ],
                                    [
                                        'label' => 'Coberturas',
                                        'url' => ['personas/viewpuco', 'dni' => $model->documento, 'sexo' =>  $model->sexo],
                                        'linkOptions' => [
                                            'class' => 'modalGeneral',
                                            'data-title' => 'Coberturas - ' . $model->apellido . ', ' . $model->nombre,
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