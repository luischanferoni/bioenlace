<style>
.panel-collapsable .panel-heading h4:after {
    font-family: 'Glyphicons Halflings';
    content: "\e114";
    float: right;
    color: white;
    margin-right: 5px;
    cursor: pointer;
  }
  .panel-collapsable .collapsed h4:after {
    content: "\e080";
  }
  .panel-heading .btn-group {
    float: right;
  }
</style>

<?php

use yii\helpers\Html;
//use yii\widgets\DetailView;
use frontend\components\PanelWidget;
use yii\bootstrap5\Modal;

echo PanelWidget::widget([
    'model' => $model,
    
    // 'menu' => [
    //         ['Cargar nuevos datos', ['atenciones-enfermeria/create', 'id_persona' => $id_persona, 'id_rr_hh' => $id_rr_hh]]
    // ],
    'attributes' => [
        /*[
            'label' => 'Fecha control/atención',
            'value' => $model?Yii::$app->formatter->asDate($model->fecha_creacion, 'dd/MM/yyyy'):'--',
        ],*/
        [                 
            'label' => 'Control/Atención',
            'value' => $model?$model->formatearDatos():'--',
            'format' => 'raw',
        ],
        'observaciones',
        [                                                  
            'label' => 'Profesional',
            'value' => $model?(is_object($model->user)?$model->user->nombre.' '.$model->user->apellido:''):'--',
        ],
    ],
]);

$this->registerJs(
    "$(function(){
        $('.linkaModalGeneral').click(function(event){
            event.preventDefault();
            $('#modal-general')
                .find('#modal-title')                                
                .text($(this).attr('data-title'));

            $('#modal-general').modal('show')                            
                .find('#modal-content')
                .load($(this).attr('href'));
        });
        
        $(function(){
            $('#modal-general').on('submit', 'form', function(e) {
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
                            $('#modal-general').modal('hide');                                
                        } else {
                            $('body').append('<div class=\"alert alert-danger\" role=\"alert\">'
                                +'<i class=\"fa fa-exclamation fa-1x\"></i> '+data.error+'</div>');                            
                            window.setTimeout(function() { $('.alert').alert('close'); }, 12000);                             
                        }
                    },
                    error: function () {
                        $('body').append('<div class=\"alert alert-success\" role=\"alert\">'
                            +'<i class=\"fa fa-exclamation fa-1x\"></i> Error inesperado</div>'); 
                        window.setTimeout(function() { $('.alert').alert('close'); }, 6000); 
                        $('#modal-general').modal('hide');
                    }
                });
                e.preventDefault();
            });            
        });
    });                        
");

Modal::begin([
    'title'=>'<h3 id="modal-title"></h3>',
    'id'=>'modal-general',
    'size'=>'modal-lg',
]);
echo "<div id='modal-content'></div>";
Modal::end();