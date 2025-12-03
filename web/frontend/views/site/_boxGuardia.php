<?php 
use yii\helpers\Url;
use yii\helpers\Html;

use common\models\Persona;

?>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-light">
                <div class="header-title">
                    <div class="row">
                        <div class="col-md-12">
                            <h4>Ultimos ingresos en guardia</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if(count($guardias) == 0){ ?>                            
                        <div class="d-flex align-items-center p-3 mb-2 bg-soft-gray rounded">                    
                            <div class="ms-3" style="width: 100%;">                                
                                <div class="d-flex align-items-center justify-content-between">
                                    <h5 class="mb-0 d-flex align-items-center">
                                        Ningún ingreso registrado hasta el momento
                                    </h5>                                    
                                </div>                        
                            </div>                    
                        </div>
                <?php } ?>  

                <?php foreach ($guardias as $guardia) { ?>
                    <div class="d-flex align-items-center p-3 mb-2 bg-soft-gray rounded">
                        <div class="bg-soft-dark avatar-40 rounded">
                            <svg fill="#000000" height="25px" width="25px" version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" 
                                        viewBox="0 0 458.838 458.838" xml:space="preserve">
                                    <g>
                                        <g>
                                            <g>
                                                <circle cx="337.094" cy="137.86" r="35.469"/>
                                                <path d="M391.509,314.344c1.938-1.665,3.512-3.809,4.502-6.357c3.212-8.273-0.89-17.584-9.164-20.797l-51.818-20.119
                                                    l-11.176-50.894l18.831,36.627l16.238,6.305l6.283-38.625c2.124-13.061-6.742-25.372-19.803-27.496
                                                    c-32.925-5.355-9.43-1.535-42.174-6.86c-8.274-1.346-16.242,1.722-21.515,7.47c0.643-11.169-2.953-20.183-8.824-21.142
                                                    c-3.626-0.592-7.392,1.997-10.437,6.583c-1.22-2-2.821-3.764-4.736-5.173c-4.904-20.991-14.909-41.456-30.625-56.44
                                                    c-9.722-43.63-22.265-68.191-29.502-79.665c0.002-0.158,0.012-0.313,0.012-0.472C197.6,16.695,180.905,0,160.311,0
                                                    s-37.289,16.695-37.289,37.289c0,19.007,14.224,34.681,32.606,36.986c8.42,14.407,21.921,30.061,43.388,40.675
                                                    c21.485,10.624,35.146,30.791,42.384,53.622L179.411,154.2l-27.267-39.565l26.822,22.249l1.193-14.514
                                                    c1.235-15.021-9.941-28.199-24.962-29.433c-5.106-0.42-36.865-3.031-48.502-3.987c-15.021-1.235-28.199,9.941-29.433,24.962
                                                    L68.414,253.67l11.661,76.024l-27.867,101.64c-3.176,11.582,3.639,23.546,15.222,26.722c11.578,3.175,23.546-3.638,26.722-15.222
                                                    l29.092-106.106c0.807-2.945,0.986-6.028,0.522-9.047l-10.79-70.347l8.131,0.668l26.501,72.252l-21.67,102.325
                                                    c-2.488,11.75,5.02,23.292,16.769,25.779c11.73,2.488,23.289-5.007,25.779-16.768l22.966-108.445
                                                    c0.847-3.999,0.549-8.156-0.859-11.993l-21.872-59.633l4.569-55.588l-12.416-2.879c-6.27-1.454-16.25-2.47-20.11-13.687
                                                    l-18.463-54.163l31.253,45.349c2.564,3.721,6.426,6.349,10.828,7.37l78.511,18.203c4.031,0.935,8.158,0.472,11.88-1.304
                                                    c0.504,0.554,1.034,1.108,1.592,1.661c0.652,5.763,3.128,13.25,8.653,14.153c2.78,0.454,5.642-0.963,8.199-3.714
                                                    c0.224,0.079,0.463,0.148,0.69,0.225c-10.425,63.657-15.496,101.513-15.599,105.675c-0.203,8.217,1.628,11.853,5.67,15.814
                                                    h-22.107c-8.425,0-15.255,6.83-15.255,15.255v89.694c0,8.425,6.83,15.255,15.255,15.255s15.255-6.83,15.255-15.255v-25.084
                                                    h60.415v25.084c0,8.425,6.83,15.255,15.255,15.255c8.425,0,15.255-6.83,15.255-15.255c0-97.753,0.266-90.99-0.635-94.031
                                                    l18.355,1.404l-13.719,85.541c-1.686,10.516,5.471,20.408,15.987,22.094c10.514,1.686,20.407-5.471,22.094-15.987l17.036-106.224
                                                    C408.776,326.356,402.176,316.243,391.509,314.344z M171.705,72.798c8.509-2.729,15.684-8.428,20.308-15.88
                                                    c5.133,10.333,11.397,25.68,17.196,47.314C203.585,101.097,186.314,94.158,171.705,72.798z M317.542,398.156h-60.415v-29.015
                                                    h60.415V398.156z M306.594,307.746l-3.318-1.288c-8.662-3.364-13.846-11.838-13.486-20.631l2.671-62.756l13.024,59.309
                                                    c1.155,5.256,4.863,9.585,9.879,11.534l46.524,18.064L306.594,307.746z"/>
                                            </g>
                                        </g>
                                    </g>
                            </svg>
                        </div>
                        <div class="ms-3" style="width: 100%;">                                
                            <div class="d-flex align-items-center justify-content-between">
                                <h5 class="mb-0 d-flex align-items-center">
                                    <?= $guardia->paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N) ;?>
                                </h5>                                    
                            </div>
                            <p class="mb-1"><strong><?= $guardia->paciente->tipoDocumento->nombre ?>: </strong> <?= $guardia->paciente->documento ?></p>
                        </div>
                        <div class="ms-1" style="width: 13%;">
                            <a title="Historia" href='<?= Url::toRoute('paciente/historia/'.$guardia->id_persona) ?>' target="_blank" class="p-1 btn btn-dark btn-sm me-4 mt-4">
                                Atender
                            </a>                                  
                        </div> 
                    </div>   

                <?php } ?>
  
            </div>
            <div class="card-footer">
                <?= Html::a('Ver todos los ingresos activos', ['guardia/index'], ['class' => 'btn btn-success float-end']) ?>
            </div>            
        </div>
    </div>