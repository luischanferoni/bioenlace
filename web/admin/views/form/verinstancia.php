<section>
<div class="row">
      <div class="card ">
        <div class="card-header "><h3>Datos Cargados.</h3></div>
        <div class="card-body">
        <div class="row">
<?php

/*$myArray = [
"id"=> 12,
"fecha_creacion"=> "05-07-2023 11:19:13",
"documento"=>"27608130",
"nombre"=>  "IBAÑES, MARINE",
"establecimiento:"=> "prueba2",
"cerramiento_externo"=>"Tapia",
"tendido_electrico"=> "NO",
"rampa_de_acceso"=> "Cableado Externo",
"provicion_de_agua"=> "Cisterna con Bomba Elevadora",
"comunicacion"=>  "",
"cantidad_de_impresoras"=> "20" ,
"cantidad_de_fotocopiadoras"=> "23"
];*/

echo \yii\widgets\DetailView::widget([
    'model' => $datosForm,
    'attributes' => array_map(function ($key) {
        return "$key:raw";
        // or build some logic for the right format
        // e.g. use '$key:email' if key contains 'email'
    }, array_keys($datosForm)),
]);
?>
            </div>
      </div>
      </div>

</div>
</section>