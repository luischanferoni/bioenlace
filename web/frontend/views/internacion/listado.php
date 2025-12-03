<?php

use yii\helpers\Html;
use yii\grid\GridView;

use common\models\Persona;

use webvimark\modules\UserManagement\models\User;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\SegNivelInternacionBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Listado Internación';
$this->params['breadcrumbs'][] = $this->title;

?>
<div class="seg-nivel-internacion-index">
        <div class="card">
            <div class="card-header bg-soft-info">
                <h3> Listado Internados </h3>
            </div>
            <div class="card-body">
                    <div class="col-lg-12">
                        <div class="card">
                    <!--        <div class="card-header">
                                    <h4 class="mb-0">Listado de Internados</h4>
                            </div> -->
                            <div class="card-body">
                                    <div class="custom-table-effect table-responsive  border rounded">
                                        <div id="datatable_wrapper" class="dataTables_wrapper dt-bootstrap5 no-footer">
                            <!-- ***               <div class="row align-items-center">
                                                <div class="col-md-6">
                                                        <div class="dataTables_length" id="datatable_length">
                                                            <label>Show 
                                                                <select name="datatable_length" aria-controls="datatable" class="form-select form-select-sm">
                                                                    <option value="10">10</option>
                                                                    <option value="25">25</option>
                                                                    <option value="50">50</option>
                                                                    <option value="100">100</option>
                                                                </select> entries</label>
                                                        </div>
                                                    </div>
                                        
                                                    <div class="col-md-6">
                                                        <div id="datatable_filter" class="dataTables_filter">
                                                            <label>Search:
                                                                <input type="search" class="form-control form-control-sm" placeholder="" aria-controls="datatable">
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>-->
                                                <div class="table-responsive my-3">
                                                            <table class="table mb-0 dataTable no-footer" id="datatable" data-toggle="data-table" aria-describedby="datatable_info">
                                                                <thead>
                                                                    <tr class="bg-white">
                                                                        <th scope="col" class="sorting" tabindex="0" aria-controls="datatable" rowspan="1" colspan="1" aria-label="Profiles: activate to sort column ascending">Apellido y Nombre</th>
                                                                        <th scope="col" class="sorting sorting_desc" tabindex="0" aria-controls="datatable" rowspan="1" colspan="1" aria-label="Contact: activate to sort column ascending" aria-sort="descending">Documento</th>
                                                                        <th scope="col" class="sorting" tabindex="0" aria-controls="datatable" rowspan="1" colspan="1" aria-label="Email ID: activate to sort column ascending">F. Nacimiento</th>
                                                                        <th scope="col" class="sorting" tabindex="0" aria-controls="datatable" rowspan="1" colspan="1" aria-label="Country: activate to sort column ascending">Usuario Alta</th>
                                                                        <th scope="col" class="sorting" tabindex="0" aria-controls="datatable" rowspan="1" colspan="1" aria-label="Purchases: activate to sort column ascending">Cama</th>
                                                                        <th scope="col" class="sorting" tabindex="0" aria-controls="datatable" rowspan="1" colspan="1" aria-label="Purchases: activate to sort column ascending">Sala</th>
                                                                        <th scope="col" class="sorting" tabindex="0" aria-controls="datatable" rowspan="1" colspan="1" aria-label="Purchases: activate to sort column ascending">Piso</th>
                                                            <!--            <th scope="col" class="sorting" tabindex="0" aria-controls="datatable" rowspan="1" colspan="1" aria-label="Status: activate to sort column ascending">Estado</th> -->
                                                                        <th scope="col" class="sorting" tabindex="0" aria-controls="datatable" rowspan="1" colspan="1" aria-label="Action: activate to sort column ascending">Acciones</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                
                                                                <?php      
                                                                
                                                                $tipoRow = "odd";

                                                    foreach ($pisos_efector as $key => $piso) {
                                                        $datoPiso = $piso->nro_piso.' '.$piso->descripcion;
                                                        $salas = $piso->infraestructuraSalas;
                                                        foreach ($salas as $key => $sala) {
                                                                $camas = $sala->infraestructuraCamas;
                                                                $datoSala =  $sala->nro_sala.' '.$sala->descripcion;
                                                                foreach ( $camas as $key => $dato ) { 

                                                                    $tipoRow = $tipoRow == "odd" ? "even" : "odd"; 
                                                                    if ($dato->attributes['estado'] == "ocupada"){  ?>

                                                                        <tr class="<?php echo $tipoRow; ?>">
                                                                            <td class="">
                                                                                <div class="d-flex align-items-center">
                                                                                    <div class="media-support-info">
                                                                                        <h5 class="iq-sub-label"><?php echo $dato->internacionActual->paciente->attributes['apellido']
                                                                                                                            .' '.$dato->internacionActual->paciente->attributes['nombre']; ?></h5>
                                                                                    </div>
                                                                                </div>
                                                                            </td>
                                                                            <td class="sorting_1"> <?php echo $dato->internacionActual->paciente->attributes['documento']; ?> </td>
                                                                            <td class=""> <?php echo $dato->internacionActual->paciente->attributes['fecha_nacimiento']; ?> </td>
                                                                            <td class=""> <?php echo $dato->internacionActual->rrhh->rrhhEfector->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON); ?> </td>
                                                                            <td class=""> <?php echo $dato->attributes['nro_cama']; ?>
                                                                            </td>
                                                                            <td class=""> <?php echo $datoSala; ?> </td>
                                                                            <td class=""> <?php echo $datoPiso; ?> </td>
                                                                <!--            <td>
                                                                                <span class="badge bg-soft-danger p-2 text-danger">Inactive</span>
                                                                            </td> -->
                                                                            <td>
                                                                               
                                                                                <div class="d-flex justify-content-evenly">
                                                                                    <?php 
                                                                                        if (User::hasRole("Medico") || User::hasRole("enfermeria")) {
                                                                                            echo Html::a('Historia Clínica', 
                                                                                                    ['paciente/historia/'.$dato->internacionActual->id_persona],
                                                                                                    ['class' => 'btn btn-outline-info me-2']) . 
                                                                                                Html::a('Internacion', 
                                                                                                    ['internacion/'.$dato->internacionActual->id],
                                                                                                    ['class' => 'btn btn-outline-success me-2']);
                                                                                        }
                                                                                    ?>
                                                                                </div>
                                                                            </td>
                                                                        </tr>

                                                                <?php   }
                                                                }               
                                                            }           
                                                        }            
                                                                ?>
                                                                    

                                                                </tbody>
                                                            </table>
                                                </div>
                        <!--                        <div class="row align-items-center">
                                                    <div class="col-md-6">
                                                        <div class="dataTables_info" id="datatable_info" role="status" aria-live="polite">Muestra 1 a 9 de 9 entradas
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6"><div class="dataTables_paginate paging_simple_numbers" id="datatable_paginate">
                                                        <ul class="pagination">
                                                            <li class="paginate_button page-item previous disabled" id="datatable_previous">
                                                                <a href="#" aria-controls="datatable" data-dt-idx="0" tabindex="0" class="page-link">Previous</a>
                                                            </li>
                                                            <li class="paginate_button page-item active">
                                                                <a href="#" aria-controls="datatable" data-dt-idx="1" tabindex="0" class="page-link">1</a>
                                                            </li>
                                                            <li class="paginate_button page-item next disabled" id="datatable_next">
                                                                <a href="#" aria-controls="datatable" data-dt-idx="2" tabindex="0" class="page-link">Next</a>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div> ->
                                            <div class="clear">
                                            </div>
                                        </div>
                                    </div>
                            </div>
                        </div>
                    </div> <!-- fin col-lg-12 -->
            </div> <!-- fin card-body -->
        </div> <!-- fin card -->
</div> <!-- fin seg-nivel-internacion-index -->

<?php /*
$this->registerJs("
    $(document).ready(function() {
        var pacienteInternado= ".($pacienteInternado ? 'true' : 'false').";
        console.log(pacienteInternado);
        if (pacienteInternado){
            Swal.fire({
                title: 'La persona seleccionada  ya se encuentra en internación.',
                backdrop: `rgba(60,60,60,0.8)`,
            });
     }
    });
"); */
?>