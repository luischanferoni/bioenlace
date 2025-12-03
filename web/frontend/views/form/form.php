<?php
use yii\helpers\Html;
use kartik\form\ActiveForm;
use kartik\builder\Form;
use kartik\date\DatePicker;
use kartik\time\TimePicker;
use kartik\select2\Select2;
use yii\httpclient\Client;
use common\models\Persona;

echo '<div class="row d-flex align-items-center  mb-5"> 
<div class="card">
    <div class="card-body">
      <div class="row">';
      $datosPersona =  "";
      if($formTipoId == 3 || $formTipoId == 4 ){
        $datosPersona = $personaSession->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON);
      }
if($mensajeSuccess != "") echo '<div class="alert alert-success" role="alert">'.$mensajeSuccess.'</div>';
echo '
<h1>'.$formTitulo.'</h1>
<div class="row">
   <div class="col-md-8">
       <p>'.$formDescripcion.'</p>
       <p>'.$datosPersona.'</p>
   </div>
   <div class="col-md-4">
   <div class="imgAbt">
       <img width="220"  src="'.$formLogo.'" />
   </div>
</div>
</div>
</div>';

$form = ActiveForm::begin(['type'=>ActiveForm::TYPE_VERTICAL, 'formConfig'=>['labelSpan'=>4]]);


if(! empty($secciones)){
  foreach ($secciones as $seccion) {
    if(array_key_exists('preguntas', $seccion)){    
      
      $preguntas = $seccion['preguntas'];
      $attributes=[];
      foreach($preguntas as $pregunta){
        switch ($pregunta["tipoPreguntaId"]){
          case 1:            
            $attributes[$pregunta["id"]]=[
              'label'=> $pregunta['titulo'],
              'type'=>Form::INPUT_TEXT, 
              'options'=>['placeholder'=>'Ingrese '.$pregunta['titulo']],
              'columnOptions'=>['colspan'=>2]
            ];            
            break;
          case 2:
            $attributes[$pregunta["id"]]=[
              'label'=>$pregunta['titulo'],
              'type'=>Form::INPUT_TEXTAREA,              
              'columnOptions'=>['colspan'=>2]
            ];
            break;
            case 3:
              $attributes[$pregunta["id"]]=[
                'label'=>$pregunta['titulo'],
                'type'=>Form::INPUT_TEXT, 
                'options'=>['placeholder'=>'Ingrese '.$pregunta['titulo']],
                'columnOptions'=>['colspan'=>2]
              ];
            break;
            case 4:
              $campos = $pregunta["campos"];
              $arrayOpciones = []; 
              foreach($campos as $campo){
                $arrayOpciones[$campo['id']] = $campo['nombre'];
              }
              $attributes[$pregunta["id"]]=[
                'label'=>$pregunta['titulo'],
                'type'=>Form::INPUT_CHECKBOX_LIST, 
                'items'=> $arrayOpciones, 
                'options'=>['inline'=>false]
              ];

              break;
              case 5:
                $campos = $pregunta["campos"];
                $arrayOpciones = []; 
                foreach($campos as $campo){
                  $arrayOpciones[$campo['id']] = $campo['nombre'];
                }
                $attributes[$pregunta["id"]]=[
                  'label'=>$pregunta['titulo'],
                  'type'=>Form::INPUT_RADIO_LIST, 
                  'items'=> $arrayOpciones, 
                  'options'=>['inline'=>false]
                ];
               
              break;
              case 6:
                $attributes[$pregunta["id"]]=[
                  'label'=>$pregunta['titulo'],
                  'type'=>Form::INPUT_WIDGET, 
                  'widgetClass'=>'\kartik\date\DatePicker'
                ];               
              break;
              case 9:
                $code = $pregunta['campos'][0]['nombre'];                
                eval($code);  
                $entidadList = \yii\helpers\ArrayHelper::map($entidad, 'id', 'nombre');
                $attributes[$pregunta["id"]]=[
                  'label'=>$pregunta['titulo'],
                  'type'=>Form::INPUT_DROPDOWN_LIST, 
                  'items'=> $entidadList, 
                  'options'=>['inline'=>false]
                ];
              break;
              case 10:
                $attributes[$pregunta["id"]]=[
                  'label'=>$pregunta['titulo'],
                  'type'=>Form::INPUT_WIDGET, 
                  'widgetClass'=>'kartik\time\TimePicker'
                ];
              break;
              case 11:
                $campos = $pregunta["campos"];
                $arrayOpciones = []; 
                foreach($campos as $campo){
                  $arrayOpciones[$campo['id']] = $campo['nombre'];
                }
                $attributes[$pregunta["id"]]=[
                  'label'=>$pregunta['titulo'],
                  'type'=>Form::INPUT_DROPDOWN_LIST, 
                  'items'=> $arrayOpciones, 
                  'options'=>['inline'=>false]
                ];
              break;
              case 12:
                $campos = $pregunta["campos"];
                $arrayOpciones = []; 
                foreach($campos as $campo){
                  $arrayOpciones[$campo['id']] = $campo['nombre'];
                }
                
                $attributes[$pregunta["id"]]=[
                  'label'=>$pregunta['titulo'],
                  'type'=>Form::INPUT_WIDGET, 
                  'widgetClass'=>'\kartik\select2\Select2', 
                  'multiple' => true,                 
                  'options' => [ 'data' => $arrayOpciones, 
                                 'pluginOptions' => ['placeholder' => 'Seleccione ...',
                                                      'multiple' => true],  
                                ]                 
                                      
                ];    
              break;
              case 13:
                $endpoint = $pregunta['campos'][0]['nombre'];                
                $client = new Client();
                $responseEndpoint = $client->createRequest()
                    ->setMethod('GET')
                    ->setUrl($endpoint)
                    ->setHeaders(['Content-type' => 'application/json'])
                    ->setData([])
                    ->send();
                if ($responseEndpoint->isOk) {
                  $endpointList = \yii\helpers\ArrayHelper::map($responseEndpoint->data, 'id', 'nombre');
                  $attributes[$pregunta["id"]]=[
                    'label'=>$pregunta['titulo'],
                    'type'=>Form::INPUT_DROPDOWN_LIST, 
                    'items'=> $endpointList, 
                    'options'=>['inline'=>false]
                  ];
                }else{
                  echo "El endpoint de la pregunta ".$pregunta['titulo']." no funciona.";
                }                
              break;
               
        }

        
      }
      $tituloSeccion = ($seccion['mostrartitulo'] == 1)? $seccion['titulo']: '';
      echo '<div class="row"><div class="col-sm-12">
      <div class="card  ">
        <div class="card-header text-white bg-primary text-center">'.$tituloSeccion.'</div>
        <div class="card-body">
        '                
      ;     
      echo Form::widget([
        'model'=>$model,
        'form'=>$form,
        'columns'=>$seccion['columnas'],
        'attributes'=> $attributes
      ]);
      echo '</div>
        </div>
        </div>
        </div>'                 
        ;

    }
  }
}  

echo Form::widget([       // 3 column layout
  'model'=>$model,
  'form'=>$form,
  'columns'=>1,
  'compactGrid'=>true,
  'attributes'=>[
    'actions'=>[
      'type'=>Form::INPUT_RAW, 
      'value'=>'<div style="text-align: right; margin-top: 20px">' . 
          Html::resetButton('Reset', ['class'=>'btn btn-secondary btn-default']) . ' ' .
          Html::button('Guardar', ['type'=>'submit', 'class'=>'btn btn-primary']) . 
          '</div>'
  ],
]
]);

ActiveForm::end();


?>

