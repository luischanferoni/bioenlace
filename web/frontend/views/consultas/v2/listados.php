<?php
    use yii\helpers\Html;
    use yii\helpers\Url;

    use yii\bootstrap5\LinkPager;
    use kartik\export\ExportMenu;
?>

<div class="col-md-12">

    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between flex-wrap">
                <div class="d-flex align-items-center flex-wrap">
                    <div class="dropdown me-2">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Formularios
                        </button>
                        <ul class="dropdown-menu">
                            <?php foreach($formularios as $formulario) { ?>
                                <?php 
                                    if ($formulario['id'] == $formId) {
                                        $formTitulo = $formulario['nombre'];
                                    }
                                ?>
                                <li>
                                    <a class="dropdown-item <?=$formulario['id'] == $formId ? 'active' : ''?>" href="<?=Url::current(['formId' => $formulario['id']])?>">
                                        <?=$formulario["nombre"]?>
                                    </a>
                                </li>
                            <?php } ?>
                        </ul>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            ATENCIONES
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#">Ambulatorio</a></li>
                            <li><a class="dropdown-item" href="#">Guardia</a></li>
                            <li><a class="dropdown-item" href="#">Internacion</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<section>
        <div class="card ">
            <div class="card-header ">
                <div class="row">
                    <div class="col-10">
                        <h3><?=$formTitulo . ' - '.$totalAbsolutoDeInstancias.' registros'?></h3>
                    </div>
                    <?php if (count($filtroGridView) > 0) { ?>
                        <div class="col-2">
                            <div class="float-end"><?=Html::a('Quitar filtro', ['consultas/listados', 'formId' => $formId], ['class' => 'btn btn-outline-danger rounded-pill mt-2'])?></div>
                        </div>
                    <?php } ?>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <?php
                        $columns = [];

                        foreach($atributos as $atributo) {
                            $filter = "";
                            if ($atributo['tipo'] == 'select') {                                
                                $filter = html::dropDownList($atributo['id'],
                                        (isset($filtroGridView[$atributo['id']]) ? $filtroGridView[$atributo['id']] : ''),
                                        $atributo['opciones'], ['class' => 'form-control']);
                            }
                            if ($atributo['tipo'] == 'text') {
                                $filter = Html::input('text', $atributo['id'], (isset($filtroGridView[$atributo['id']]) ? $filtroGridView[$atributo['id']] : ''), 
                                                        ['class' => 'form-control']);
                            }

                            $columns[] = [
                                'attribute' => $atributo['clave'],
                                'label' => $atributo['nombre'],
                                'headerOptions' => ['class' => 'text-wrap'],
                                'format' => 'raw',
                                'filter' => $filter,//'<input class="form-control" name="' . $value['id'] . '" value="" type="text">'
                                'contentOptions' => function ($model, $key, $index, $column) {
                                    if ($column->attribute == 'riesgo') {
                                        if ($model['riesgo'] === 'Riesgo Muy Alto') {
                                            return ['class' => 'bg-soft-danger'];
                                        }
                                        if ($model['riesgo'] === 'Riesgo Moderado') {
                                            return ['class' => 'bg-soft-warning'];
                                        }
                                        return ['class' => 'bg-soft-info'];
                                    }
                                    return [];
                                },
                            ];
                        }
                        echo ExportMenu::widget([
                            'dataProvider' => $providerExportar,
                            'columns' => $columns,
                            'clearBuffers' => true, //optional
                            'dropdownOptions' => [
                                'label' => 'Exportar',
                                'class' => 'badge bg-secondary text-light'
                            ],
                        ]);
                    

                        echo yii\grid\GridView::widget([
                            'dataProvider' => $provider,
                            'filterModel' => [],
                            'summary' => '',
                            'tableOptions' => ['class' => 'table table-responsive table-hover'],
                            'columns' => $columns,                       
                        ]);

                        echo LinkPager::widget([
                            'pagination' => $pages,
                            'prevPageLabel' => 'Anterior', 'nextPageLabel' => 'Siguiente', 
                            'options' => ['class' => 'pagination justify-content-center mt-5']
                        ]);                        
                    ?>
                </div>
            </div>
        </div>
</section>