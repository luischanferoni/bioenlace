<?php
use yii\widgets\DetailView;
use common\models\Persona;

echo DetailView::widget([
        'model' => $model,
        'attributes' => [
            [
                'label' => 'Empadronado MPI',
                'value' => 'El paciente se encuentra empadronado en el MPI provincial',
                'visible' => $federado?true:false,
                'contentOptions' => ['class' => 'bg-soft-success'],
                'captionOptions' => ['class' => 'bg-soft-success'],
            ],   
            [
                'label' => 'Nombre Completo',
                'value' => $model->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON),
            ],            
            'apellido_materno',
            'apellido_paterno',
            [
                'label' => 'Sexo biologico',
                'value' => $model->sexoTexto,
            ],
            [
                'label' => 'Genero Legal',
                'value' => $model->generoTexto,
            ],
            [
            'label' => 'Tipo y Nro Documento ',
            'value' => $model->tipoDocumento->nombre." - ".$model->documento,
            ],
            [
                'label' => 'Fecha de nacimiento',
                'value' => Yii::$app->formatter->asDate($model->fecha_nacimiento, 'dd/MM/yyyy'),
            ], 
            [
            'label' => 'Estado Civil',
            'value' => $model->estadoCivil->nombre,
            ],
            [
            'label' => 'Defunción',
            'value' => Yii::$app->formatter->asDate($model->fecha_defuncion, 'dd/MM/yyyy'),
            'visible' => (!is_null($model->fecha_defuncion))?true:false,
            ],          
            
            
        ],
    ])
    ?>
