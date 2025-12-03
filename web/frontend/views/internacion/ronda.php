<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\Url;

use common\models\Persona;
use webvimark\modules\UserManagement\models\User;


/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\SegNivelInternacionBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Internaciones';
$this->params['breadcrumbs'][] = $this->title;
$hola = 1;

?>
<h1 class="text-white">Pacientes Internados</h1>
<div>
    
<div class="card">
    <div class="card-body">
        <p>Filtrar por piso y sala </p>
    <div class="row">
        <?php $urlReset = "ronda";
                echo $this->render('_searchPorPisoSala', 
                    [
                        'pisos_efector'=>$pisos_efector,
                        'urlReset'=> $urlReset
                    ]) 
        ?>   
    </div> 
</div>
</div>
       
        <?php foreach ($pisos_efector as $key => $piso) { 
                    if (Yii::$app->request->post()) {
                        $pisoSeleccionado = Yii::$app->request->post('piso');                       
                        if($pisoSeleccionado != null && $pisoSeleccionado != '' && $piso->id != $pisoSeleccionado )   continue;                     
                    }
            ?>
                          
                                    <div class="p-2 show mb-4 h3 bg-soft-primary alert-left alert-primary d-inline-block rounded">
                                        <span> Piso: <?= $piso->descripcion; ?></span>
                                    </div>                                    


                                    <?php $salas = $piso->infraestructuraSalas;

                                    foreach ($salas as $key => $sala) { 
                                        $hayCamasOcupadas = false;
                                        if (Yii::$app->request->post()) {
                                            $salaSeleccionada = Yii::$app->request->post('sala');
                                            if($salaSeleccionada != '' && $sala->id != $salaSeleccionada )   continue;                     
                                        }
                                        ?>
                                        <div class="p-2 show mb-3 h5 bg-soft-success alert-left alert-success d-table rounded">
                                            <span> Sala: <?= $sala->descripcion; ?></span>
                                        </div>
                                        
                                        <div class="row gx-3">
                                        <?php $camas = $sala->infraestructuraCamas;
                                        foreach ($camas as $key => $cama) {

                                            if ($cama->estado == 'ocupada') {
                                                $class = 'col-md-3';                                                    
                                                $id = $cama->internacionActual->id;
                                                $hayCamasOcupadas = true;
                                                
                                                $nombre= $cama->internacionActual->paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON);
                                                $documento = $cama->internacionActual->paciente->documento;
                                                $pacienteId = $cama->internacionActual->paciente->id_persona;
                                                $esEnfermero = User::hasRole(['enfermeria']) ? true : false;
                                                $esMedico = User::hasRole(['Medico'], $superAdminAllowed = true);

                                                $operaciones = ($esEnfermero || $esMedico)? Html::a('Atender', ['internacion/view', 'id' => $id], $options = ['class' => 'buttonsli dropdown-item']):'';
                                                $operaciones .=  ($esEnfermero || $esMedico)?Html::a('Ver Historial', ['paciente/historia', 'id' => $pacienteId], $options = ['class' => 'buttonsli dropdown-item']):'';
                                                //$operaciones .= ($esEnfermero || $esMedico)? Html::a('Tratamientos', ['internacion/view', 'id' => $id], $options = ['class' => 'buttonsli dropdown-item']):'';
                                                //$operaciones .= ($esEnfermero || $esMedico)? Html::a('Solicitar Practica', ['internacion-practica/create', 'id' => $id], $options = ['class' => 'buttonsli dropdown-item buttonAltaMedica']):'';
                                                //$operaciones .= ($esEnfermero || $esMedico)? Html::a('Medicamentos', ['internacion-suministro-medicamento/create', 'idi' => $id], $options = ['class' => 'buttonsli dropdown-item']):'';
                                                
                                                if($cama->internacionActual->enableCambioCama()) {
                                                    $operaciones .= ($esEnfermero || $esMedico)? Html::a('Cambio Cama', ['internacion-hcama/create', 'id' => $id], $options = ['class' => 'buttonsli dropdown-item']):'';
                                                }
                                                $operaciones .= ($esEnfermero || $esMedico)? '<li>'.Html::a('Alta Médica', ['/internacion/update', 'id' => $id], $options = ['class' => 'buttonsli dropdown-item']).'</li>':'';
                                                $nroCama = $cama->nro_cama;

                                                echo $this->render('_cardRonda', [
                                                    'nroCama' => $nroCama,
                                                    'nombre' => $nombre,
                                                    'documento' => $documento,
                                                    'operaciones'=> $operaciones                                                   
                                                ]) ;

                                            
                                            } 
                                        ?>
                                        <?php } ?>
                                        </div><?php
                                        if(!$hayCamasOcupadas){
                                        
                                        echo '<div class="alert alert-top alert-info alert-dismissible fade show mb-3" role="alert">
                                        <span><i class="fas fa-bell"></i></span>
                                        <span> No hay camas ocupadas en esta sala!</span>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                     </div>';
                                    } ?>
                                        
                                    <?php } ?>
                                    
                              
                    <?php } ?>
       


</div>             
<div class="modal remote fade" id="modalAltaMedica">
        <div class="modal-dialog">
            <div class="modal-content loader-lg"></div>
        </div>
</div>
<?php
$this->registerCss("

.op{
    padding: 0;
    list-style: none;
    min-width: 100%;
}
.op li{
    display: inline-block;
    position: relative;
    line-height: 21px;
    text-align: left;
    min-width: 100%;
}
.op li a{
    display: block;
    padding: 8px 25px;       
    min-width: 100%;
}
.op li a:hover{
    color: #fff;
    background: #939393;
}
.op li ul.dropdown-menu{
    min-width: 100%; 
    background: #f2f2f2;
    display: none;
    position: absolute;
    z-index: 999;
    left: 0;
}
.op li ul.dropdown-menu li{
    display: block;
}
");
$this->registerJs("
    $(document).ready(function() {
        $('.dropdownOperaciones a:first-child').click(function(e){
            e.preventDefault();
            $(this).parent().find('.dropdown-menu').slideToggle(100);            
        });

        $('.buttonsli').click(function(){
            var url = $(this).attr('href');            
            if(url.indexOf('/sisse/internacion/update') >= 0 ) {
                $('#modalAltaMedica').modal('show').find('.modal-dialog').load(url);
            }else{            
                window.location.href = url;
            }         
        });
        
        $('#piso').select2('val', '".Yii::$app->request->post('piso')."');        
    });
");
?>