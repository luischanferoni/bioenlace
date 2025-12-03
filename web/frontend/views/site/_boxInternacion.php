<?php 
use yii\helpers\Url;


$cantidadInternados = count($internados);
$urlInternados = Url::toRoute('internacion/ronda');
?>
<div class="col-lg-4">
    <div class="card">
        <div class="card-header">
            <div class="header-title">
                <div class="row">
                    <div class="col-md-8">
                        <h4>Pacientes Internados</h4>
                    </div>

                    <div class="col-md-4">
                        <a href="<?= $urlInternados ?>" class="btn mb-1 btn-primary">
                        <svg fill="none" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
                            <circle cx="11.7666" cy="11.7666" r="8.98856" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M18.0183 18.4852L21.5423 22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M11.4999 7V16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M16 11.5001H7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                          <?= $cantidadInternados ?>
                        </a>
                        <!--div class="dataTables_paginate paging_simple_numbers" id="datatable_paginate">
                            <ul class="pagination">
                                <li class="paginate_button page-item previous disabled" id="datatable_previous">
                                    <a href="#" aria-controls="datatable" data-dt-idx="previous" tabindex="0" class="page-link">
                                        <
                                    </a>
                                </li>
                                <li class="paginate_button page-item active">
                                    <a href="#" aria-controls="datatable" data-dt-idx="0" tabindex="0" class="page-link">
                                        10
                                    </a>
                                </li>
                                <li class="paginate_button page-item next disabled" id="datatable_next">
                                    <a href="#" aria-controls="datatable" data-dt-idx="next" tabindex="0" class="page-link">
                                        >
                                    </a>
                                </li>
                            </ul>
                        </div-->
                    </div>
                </div> 
            </div>
        </div>
        <div class="card-body">
            <?php 
            if( $cantidadInternados > 0 ){

                $max = 3; $i = 1;
                
                foreach ($internados as $key => $internado) {
                    # code...                    
                    if($i > $max) {break;}
                    $value = $internado;
                    $i++;
                
                
                // for ($i=1; $i <= $max ; $i++) { 
                //     # code...
                //     $value = $internados[$i-1];
                    
                    $url = Url::toRoute('internacion/'.$value['id']);
                ?>
                <div class="d-flex align-items-center p-3 mb-2 bg-soft-gray rounded">
                    <div class="bg-soft-primary avatar-45 rounded">
                            <svg fill="#000000" height="40px" width="40px" version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" 
                                    viewBox="0 0 460.476 460.476" xml:space="preserve">
                                <g>
                                    <g>
                                        <g>
                                            <path d="M219.978,0.899C217.364,0.317,214.65,0,211.86,0c-6.688,0-12.958,1.773-18.381,4.861l9.605,9.248L219.978,0.899z"/>
                                            <path d="M244.679,19.667c-2.394-4.469-5.676-8.387-9.607-11.53l-15.318,11.978C227.732,19.122,236.124,18.844,244.679,19.667z"/>
                                            <path d="M182.165,14.79c-3.965,5.243-6.573,11.567-7.303,18.454c5.236-2.395,11.633-5,18.875-7.311L182.165,14.79z"/>
                                            <path d="M176.59,49.07c4.953,14.734,18.867,25.352,35.27,25.352c20.551,0,37.211-16.66,37.211-37.211
                                                c0-0.623-0.017-1.241-0.047-1.857C219.651,30.476,190.626,42.114,176.59,49.07z"/>
                                            <path d="M163.867,168.289l0.008,270.642c0,11.899,9.646,21.545,21.546,21.545s21.546-9.646,21.546-21.545V266.714h9.303V438.93
                                                c0,11.899,9.646,21.545,21.546,21.545c11.899,0,21.546-9.646,21.546-21.545l-0.393-255.965
                                                C258.969,182.965,212.082,194.625,163.867,168.289z"/>
                                            <path d="M337.963,94.065l-46-75.898c-5.138-8.48-16.182-11.188-24.66-6.048c-8.48,5.14-11.188,16.18-6.049,24.661l31.982,52.77
                                                l-34.648,5.102c-10.321,0-71.327,0-81.525,0c4.763,6.899,16.142,22.303,31.406,35.909c14.207,12.664,31.774,23.762,50.5,24.968
                                                V130.56c3.198,0-3.595,0.861,66.256-9.425c5.951-0.876,11.067-4.675,13.629-10.118C341.415,105.574,341.081,99.21,337.963,94.065
                                                z"/>
                                            <path d="M120.847,132.376c-0.373,3.316-0.217-6.174-0.934,136.456c-0.05,9.916,7.948,17.995,17.864,18.045
                                                c0.03,0,0.061,0,0.092,0c9.873,0,17.902-7.98,17.952-17.864l0.529-105.15C144.206,156.204,132.131,145.972,120.847,132.376z"/>
                                        </g>
                                    </g>
                                </g>
                                </svg>
                    </div>
                    <div class="ms-3" style="width: 100%;">                                
                        <div class="d-flex align-items-center justify-content-between">
                            <h5 class="mb-0 d-flex align-items-center">
                                <?= $value['nombre'] ?>
                            </h5>                                    
                        </div>
                        <p class="mb-1">
                            <strong>Piso: </strong><?= $value['piso'] ?>
                            <strong>Sala: </strong> <?= $value['sala'] ?>
                            <strong>Cama: </strong><?= $value['cama'] ?>
                        </p>
                        
                        <div class="grid-cols-1 d-grid">
                            <a title="Ver datos del paciente" href='<?= $url ?>' class="p-2 btn btn-success me-4 mt-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-heart-pulse-fill" viewBox="0 0 16 16">
                                    <path d="M1.475 9C2.702 10.84 4.779 12.871 8 15c3.221-2.129 5.298-4.16 6.525-6H12a.5.5 0 0 1-.464-.314l-1.457-3.642-1.598 5.593a.5.5 0 0 1-.945.049L5.889 6.568l-1.473 2.21A.5.5 0 0 1 4 9H1.475Z"/>
                                    <path d="M.88 8C-2.427 1.68 4.41-2 7.823 1.143c.06.055.119.112.176.171a3.12 3.12 0 0 1 .176-.17C11.59-2 18.426 1.68 15.12 8h-2.783l-1.874-4.686a.5.5 0 0 0-.945.049L7.921 8.956 6.464 5.314a.5.5 0 0 0-.88-.091L3.732 8H.88Z"/>
                                </svg> Atender Paciente
                            </a>
                        </div>                        
                    </div> 
                    
                </div>       
            <?php 
     
        } 
            }else{ ?>
                <div class="d-flex align-items-center p-3 mb-2 bg-soft-gray rounded">                    
                    <div class="ms-3" style="width: 100%;">                                
                        <div class="d-flex align-items-center justify-content-between">
                            <h5 class="mb-0 d-flex align-items-center">
                                No se encontraron resultados.
                            </h5>                                    
                        </div>                        
                    </div>                    
                </div>    
            <?php } ?>
                               

        </div>
        <div class="card-footer">

        </div>
    </div> 
</div>