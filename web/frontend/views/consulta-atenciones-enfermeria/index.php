<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\Url;
use yii\bootstrap5\Modal;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\PersonaBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Personas';
$this->params['breadcrumbs'][] = $this->title;
//print_r($dataProvider);
?>
<div class="persona-index">

    <h1><?= Html::encode($this->title) ?></h1>
    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <?=
    GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],
//            'id_persona',
            'apellido',
            'nombre',
//            'id_tipodoc',
            [
                'label' => 'Tipo Documento',
                'attribute' => 'id_tipodoc',
                'value' => function ($data) {
                    $cliente = \common\models\Tipo_documento::findOne(['id_tipodoc' => $data->id_tipodoc]);
                    return $cliente->nombre;
                }
            ],
            'documento',
            
                    ['class' => 'yii\grid\ActionColumn', 'template' => '{view}'],
                ],
            ]);
                    ?>
    
</div>
<?php
    Modal::begin([
        'title' => 'hola',
        'id' => 'modalControles',
        'size' => 'modal-lg',
        ]);
        echo "<div id='modalContent'></div>";
    Modal::end();
    ?>    
<?php
    $this->registerJs(
        " $(function(){
    $('.modalButtonControles').click(function(){
        $('.modal').modal('show')
            .find('#modalContent')
            .load($(this).attr('value'));
            console.log($(this).attr('value'));
    });
});
            $(function(){
            
            $('#modalControles').on('submit', 'form', function(e) {
                var form = $(this);
                var formData = form.serialize();
                $.ajax({
                    url: form.attr('action'),
                    type: form.attr('method'),
                    data: formData,
                    success: function (data) {
                        if(typeof(data.error) === 'undefined'){
                            $('#submit_consulta').toggleClass('disable');
                            $('body').append('<div class=\"alert alert-success\" role=\"alert\">'
                                +'<i class=\"fa fa-thumbs-up fa-1x\"></i> '+data.success+'</div>');
                            window.setTimeout(function() { $('.alert').alert('close'); }, 3000); 
                            $('#modalControles').modal('hide');                                
                        }else{
                            $('body').append('<div class=\"alert alert-danger\" role=\"alert\">'
                                +'<i class=\"fa fa-exclamation fa-1x\"></i> '+data.error+'</div>');                            
                            window.setTimeout(function() { $('.alert').alert('close'); }, 12000);                             
                        }
                    },
                    error: function () {
                            $('body').append('<div class=\"alert alert-success\" role=\"alert\">'
                                +'<i class=\"fa fa-exclamation fa-1x\"></i> Error inesperado</div>'); 
                            window.setTimeout(function() { $('.alert').alert('close'); }, 6000); 
                            $('#modalControles').modal('hide');
                    }
                });
                e.preventDefault();
            });            
        });
        ");
?>