<?php

namespace frontend\controllers;

use Yii;
use yii\base\DynamicModel;
use yii\web\Controller;
use yii\filters\VerbFilter;

use webvimark\modules\UserManagement\models\User;
use common\models\Persona;
use common\models\ServiciosEfector;
use common\models\busquedas\ConsultaBusqueda;

use kartik\mpdf\Pdf;
use common\models\Servicio;
use common\models\Efector;
use common\models\RrhhEfector;

class ReporteController extends Controller
{


    public function behaviors()
    {
         //control de acceso mediante la extensión
         return [
            'ghost-access' => [
                'class' => 'frontend\components\SisseGhostAccessControl',
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                    'planilla4'=> ['post', 'get']
                ],
            ],
        ];
    }

    public function actionPlanilla4()
    {

        $session = Yii::$app->getSession();        
        $id = Yii::$app->request->get('id');
        
        $idEfector = Yii::$app->user->getIdEfector();    
        $serviciosXEfector = ServiciosEfector::allServiciosXEfector($idEfector);
        $resultados = [];
        $mensaje = "";

        if (Yii::$app->request->post()) {

            $tipoAtencion = Yii::$app->request->post('tipoAtencion');
            $medico = Yii::$app->request->post('medico');
            $servicio = Yii::$app->request->post('servicio');
            if($tipoAtencion != "" && $tipoAtencion != "" && $servicio != ""){
            
                $efector = Efector::findOne(['id_efector' => $idEfector]);
                $nombreEfector = $efector->nombre;
                $nombreDepartamento = $efector->localidad->departamento->nombre;
                $servicio = Yii::$app->request->post('servicio');
                $nombreServicio = Servicio::findOne(["id_servicio" => $servicio])->nombre;
                $nombreMedico = RrhhEfector::findOne(['id_rr_hh' => $medico])->persona->getNombreCompleto('');
                $desde = Yii::$app->request->post('desde');
                $hasta = Yii::$app->request->post('hasta');

                
                $diaD = date("d",strtotime($desde));
                $mesD = $this->obtenerMes($desde);
                $anioD = date("Y",strtotime($desde));
                $diaH = date("d",strtotime($hasta));
                $mesH = $this->obtenerMes($hasta);
                $anioH = date("Y",strtotime($hasta));
                $mismoDia = ($desde == $hasta)? true: false;

                $searchModel = new ConsultaBusqueda();
                $searchModel->id_efector = $idEfector;
                $resultados = $searchModel->searchParaReporteC4($idEfector,$servicio,$medico, $desde, $hasta, $tipoAtencion);

                
                if(count($resultados) > 0){ 
                    

                    $content =  $this->renderPartial('_planilla4', [ 
                        'nombreEfector'=> $nombreEfector,
                        'nombreDepartamento'=> $nombreDepartamento,
                        'nombreServicio' => $nombreServicio,
                        'nombreMedico'=> $nombreMedico,
                        'diaD'=> $diaD,
                        'mesD'=> $mesD,
                        'anioD'=> $anioD,
                        'diaH'=> $diaH,
                        'mesH'=> $mesH,
                        'anioH'=> $anioH,
                        'mismoDia'=> $mismoDia,
                        'resultados'=> $resultados
                    ]);            
                    
                    $pdf = new Pdf([                    
                        'mode' => Pdf::MODE_CORE,                    
                        'format' => Pdf::FORMAT_A4,                    
                        'orientation' => Pdf::ORIENT_LANDSCAPE,                    
                        'destination' => Pdf::DEST_BROWSER, 
                        'content' => $content,                   
                        'cssFile' => '@vendor/kartik-v/yii2-mpdf/src/assets/kv-mpdf-bootstrap.min.css',                    
                        'cssInline' => '.kv-heading-1{font-size:18px}',                    
                        'options' => ['title' => 'Planilla 4 - Ministerio de Salud de SDE'],                    
                        'methods' => [ 
                            'SetHeader'=>['Planilla 4 - Ministerio de Salud de SDE'], 
                            'SetFooter'=>['{PAGENO}'],
                        ]
                    ]);                
                    
                    return $pdf->render(); 
                }else{
                    if($mismoDia){
                        $mensaje =  "No se encontraron atenciones para el profesional ".$nombreMedico." el dia ".$diaD." ".$mesD." ".$anioD;
                    }else{
                        $mensaje =  "No se encontraron atenciones para el profesional ".$nombreMedico." el período desde ".$diaD." ".$mesD." ".$anioD." a ".$diaH." ".$mesH." ".$anioH;
                    } 
                    
                }
            }else{
                $mensaje = "Debe especificar servicio, médico y tipo de atencion para realizar esta consulta.";             
            }                
        }
        // Se omité el servicio de odontología
        $servicios = array_filter($serviciosXEfector, function($item){
            if ($item['id_servicio']== 2) return false;   
            return true;
        });
        return $this->render('formPlanilla4', [
            'data' => '',
            'idEfector'=> $idEfector,
            'servicios'=> $servicios,
            'resultados'=> $resultados,
            'mensaje'=>$mensaje
        ]);

    }

    public function actionPlanilla5()
    {
        $session = Yii::$app->getSession();        
        $id = Yii::$app->request->get('id');        
        $idEfector = Yii::$app->user->getIdEfector();
        $serviciosXEfector = ServiciosEfector::allServiciosXEfector($idEfector);
        $resultados = [];
        $mensaje = "";

        if (Yii::$app->request->post()) {

            $servicio = Yii::$app->request->post('servicio');
            $fecha = Yii::$app->request->post('fecha');
            if($servicio != "" && $fecha != ""){
            
                $efector = Efector::findOne(['id_efector' => $idEfector]);
                $nombreEfector = $efector->nombre;            
                $nombreServicio = Servicio::findOne(["id_servicio" => $servicio])->nombre;           
                

                $searchModel = new ConsultaBusqueda();
                $searchModel->id_efector = $idEfector;
                $resultados = $searchModel->searchParaReporte5($idEfector,$servicio, $fecha);
                $dia = date("d",strtotime($fecha));
                $mes = date("m",strtotime($fecha));
                $anio = date("Y",strtotime($fecha));
            
                if(count($resultados) > 0){                

                    // Array de dias para poder mostrar los datos
                    // Obtener el número de días en el mes dado
                    $diasEnMes = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);                
                    // Inicializar el array de días del mes
                    $arrayResultadosPorDia = [];            
                    
                    foreach (range(1, $diasEnMes) as $dia) {
                        $arrayResultadosPorDia[$dia] = [];
                    }

                    foreach ($resultados as $key => $registro) {
                        $arrayResultadosPorDia[$registro['dia']] = $registro;
                    }
                    
                    
                    $content =  $this->renderPartial('_planilla5', [ 
                        'nombreEfector'=> $nombreEfector,                    
                        'nombreServicio' => $nombreServicio,                    
                        'mes'=> $mes,
                        'anio'=> $anio,
                        'resultados'=> $arrayResultadosPorDia
                    ]);            
                    
                    $pdf = new Pdf([                    
                        'mode' => Pdf::MODE_CORE,                    
                        'format' => Pdf::FORMAT_A4,                    
                        'orientation' => Pdf::ORIENT_PORTRAIT,                    
                        'destination' => Pdf::DEST_BROWSER,
                        'content' => $content,                   
                        'cssFile' => '@vendor/kartik-v/yii2-mpdf/src/assets/kv-mpdf-bootstrap.min.css',                    
                        'cssInline' => '.kv-heading-1{font-size:18px}',                    
                        'options' => ['title' => 'Planilla 5 - Ministerio de Salud de SDE'],                    
                        'methods' => [ 
                            'SetHeader'=>['Planilla 5 - Ministerio de Salud de SDE'], 
                            'SetFooter' => ['SECRETARIA TÉCNICA DE ESTADÍSTICAS - DIRECCIÓN GENERAL DE MEDICINA PREVENTIVA.']
                            //'SetFooter'=>['{PAGENO}'],
                        ]
                    ]);                
                    
                    return $pdf->render();
                }else{
                    $mensaje =  "No se encontraron atenciones registradas para el servicio ".$nombreServicio." el período ".$mes." ".$anio;
                } 
            }else{
                $mensaje = "Debe especificar servicio y fecha para realizar este reporte"; 
            }
                
        }

        return $this->render('formPlanilla5', [
            'data' => '',
            'idEfector'=> $idEfector,
            'servicios'=> $serviciosXEfector,
            'resultados'=> $resultados,
            'mensaje'=>$mensaje
        ]);

    }
    
    public $freeAccessActions = ['planilla4', 'planilla5', 'planilla9', 'planillac7', 'reportefarmacia', 'reporte'];

    public function actionReportefarmacia()
    {

        $session = Yii::$app->getSession();        
        $id = Yii::$app->request->get('id');
        
        $idEfector = Yii::$app->user->getIdEfector();
        $serviciosXEfector = ServiciosEfector::allServiciosXEfector($idEfector);
        $resultados = [];
        $mensaje = "";

        if (Yii::$app->request->post()) {
            
            $servicio = Yii::$app->request->post('servicio');
            $fecha = Yii::$app->request->post('fecha');
            $tipoAtencion = Yii::$app->request->post('tipoAtencion');
            if( $servicio != "" && $fecha != "" && $tipoAtencion != ""){

                    $efector = Efector::findOne(['id_efector' => $idEfector]);
                    $nombreEfector = $efector->nombre;
                    $nombreDepartamento = $efector->localidad->departamento->nombre;
                    
                    $nombreServicio = Servicio::findOne(["id_servicio" => $servicio])->nombre;            
                    
                    $searchModel = new ConsultaBusqueda();
                    $searchModel->id_efector = $idEfector;
                    $resultados = $searchModel->searchReporteFarmacia($idEfector,$servicio, $fecha, $tipoAtencion);
                    
                    if(count($resultados) > 0){ 

                        $dia = date("d",strtotime($fecha));
                        $mes = $this->obtenerMes($fecha);
                        $anio = date("Y",strtotime($fecha));
                        $content =  $this->renderPartial('_reporteFarmacia', [ 
                            'nombreEfector'=> $nombreEfector,
                            'nombreDepartamento'=> $nombreDepartamento,
                            'nombreServicio' => $nombreServicio,                   
                            'dia'=> $dia,
                            'mes'=> $mes,
                            'anio'=> $anio,
                            'resultados'=> $resultados
                        ]);            
                        
                        $pdf = new Pdf([                    
                            'mode' => Pdf::MODE_CORE,                    
                            'format' => Pdf::FORMAT_A4,                    
                            'orientation' => Pdf::ORIENT_LANDSCAPE,                    
                            'destination' => Pdf::DEST_BROWSER, 
                            'content' => $content,                   
                            'cssFile' => '@vendor/kartik-v/yii2-mpdf/src/assets/kv-mpdf-bootstrap.min.css',                    
                            'cssInline' => '.kv-heading-1{font-size:18px}',                    
                            'options' => ['title' => 'Reporte de Farmacia - Ministerio de Salud de SDE'],                    
                            'methods' => [ 
                                'SetHeader'=>['Reporte de Farmacia - Ministerio de Salud de SDE'], 
                                'SetFooter'=>['{PAGENO}'],
                            ]
                        ]);                
                        
                        return $pdf->render();
                    }else{
                        $mensaje =  "No se encontraron resultados para el dia ".date('d-m-Y', strtotime($fecha));
                    }
                }else{
                    $mensaje = "Debe especificar servicio, fecha y tipo de atención para realizar este reporte.";
                }
        }
        return $this->render('formReporteFarmacia', [
            'data' => '',
            'idEfector'=> $idEfector,
            'servicios'=> $serviciosXEfector,
            'resultados'=> $resultados,
            'mensaje'=>$mensaje
        ]);

    }

    public function actionPlanilla9()
    {

        $session = Yii::$app->getSession();        
        $id = Yii::$app->request->get('id');
        
        $idEfector = Yii::$app->user->getIdEfector();
        $serviciosXEfector = ServiciosEfector::allServiciosXEfector($idEfector);
        $resultados = [];
        $mensaje = "";

        if (Yii::$app->request->post()) {
            
            $efector = Efector::findOne(['id_efector' => $idEfector]);
            $nombreEfector = $efector->nombre;
            $nombreDepartamento = $efector->localidad->departamento->nombre;
            $servicio = Yii::$app->request->post('servicio');
            $nombreServicio = Servicio::findOne(["id_servicio" => $servicio])->nombre;            
            $fecha = Yii::$app->request->post('fecha');

            $searchModel = new ConsultaBusqueda();
            $searchModel->id_efector = $idEfector;
            $resultados = $searchModel->searchReporteOdontologia($idEfector,$servicio, $fecha);
            
            //var_dump($resultados);die();
            if(count($resultados) > 0){ 
                
                $dia = date("d",strtotime($fecha));
                $mes = $this->obtenerMes($fecha);
                $anio = date("Y",strtotime($fecha));
                $content =  $this->renderPartial('_planilla9', [ 
                    'nombreEfector'=> $nombreEfector,
                    'nombreDepartamento'=> $nombreDepartamento,
                    'nombreServicio' => $nombreServicio,                   
                    'dia'=> $dia,
                    'mes'=> $mes,
                    'anio'=> $anio,
                    'resultados'=> $resultados
                ]);            
                
                $pdf = new Pdf([                    
                    'mode' => Pdf::MODE_CORE,                    
                    'format' => Pdf::FORMAT_A4,                    
                    'orientation' => Pdf::ORIENT_PORTRAIT,                    
                    'destination' => Pdf::DEST_BROWSER, 
                    'content' => $content,                   
                    'cssFile' => '@vendor/kartik-v/yii2-mpdf/src/assets/kv-mpdf-bootstrap.min.css',                    
                    'cssInline' => '.kv-heading-1{font-size:18px}',                    
                    'options' => ['title' => 'Reporte de Odontología - Ministerio de Salud de SDE'],                    
                    'methods' => [ 
                        'SetHeader'=>['Reporte de Odontología - Ministerio de Salud de SDE'], 
                        'SetFooter'=>['{PAGENO}'],
                    ]
                ]);                
                
                return $pdf->render(); 
            }else{
                $mensaje =  "No se encontraron practicas para el período ".$fecha;
            }
                
        }
        return $this->render('formPlanilla9', [
            'data' => '',
            'idEfector'=> $idEfector,
            'servicios'=> $serviciosXEfector,
            'resultados'=> $resultados,
            'mensaje'=>$mensaje
        ]);

    }

    public function actionPlanillac7()
    {

        $session = Yii::$app->getSession();        
        $id = Yii::$app->request->get('id');
        
        $idEfector = Yii::$app->user->getIdEfector();
        $servicio = 2;        
        $resultados = [];
        $mensaje = "";

        if (Yii::$app->request->post()) {
            
            $medico = Yii::$app->request->post('medico');
            $tipoAtencion = Yii::$app->request->post('tipoAtencion');
            if($medico !="" && $tipoAtencion !="" ){          
            
                $efector = Efector::findOne(['id_efector' => $idEfector]);
                $nombreEfector = $efector->nombre;
                $nombreDepartamento = $efector->localidad->departamento->nombre;
                $servicio = Yii::$app->request->post('servicio');
                $nombreServicio = Servicio::findOne(["id_servicio" => $servicio])->nombre;
                
                $nombreMedico = RrhhEfector::findOne(['id_rr_hh' => $medico])->persona->getNombreCompleto('');
                $desde = Yii::$app->request->post('desde');
                $hasta = Yii::$app->request->post('hasta');                


                $searchModel = new ConsultaBusqueda();
                $searchModel->id_efector = $idEfector;
                $resultados = $searchModel->searchParaReporteC4($idEfector, $servicio, $medico, $desde, $hasta, $tipoAtencion);

                $diaD = date("d",strtotime($desde));
                $mesD = $this->obtenerMes($desde);
                $anioD = date("Y",strtotime($desde));
                $diaH = date("d",strtotime($hasta));
                $mesH = $this->obtenerMes($hasta);
                $anioH = date("Y",strtotime($hasta));

                if(count($resultados) > 0){                
                    
                    $mismoDia = ($desde == $hasta)? true: false;
                    $content =  $this->renderPartial('_planillaC7', [ 
                        'nombreEfector'=> $nombreEfector,
                        'nombreDepartamento'=> $nombreDepartamento,
                        'nombreServicio' => $nombreServicio,
                        'nombreMedico'=> $nombreMedico,
                        'diaD'=> $diaD,
                        'mesD'=> $mesD,
                        'anioD'=> $anioD,
                        'diaH'=> $diaH,
                        'mesH'=> $mesH,
                        'anioH'=> $anioH,
                        'mismoDia'=> $mismoDia,
                        'resultados'=> $resultados
                    ]);            
                    
                    $pdf = new Pdf([                    
                        'mode' => Pdf::MODE_CORE,                    
                        'format' => Pdf::FORMAT_A4,                    
                        'orientation' => Pdf::ORIENT_LANDSCAPE,                    
                        'destination' => Pdf::DEST_BROWSER, 
                        'content' => $content,                   
                        'cssFile' => '@vendor/kartik-v/yii2-mpdf/src/assets/kv-mpdf-bootstrap.min.css',                    
                        'cssInline' => '.kv-heading-1{font-size:18px}',                    
                        'options' => ['title' => 'Planilla 7 - Ministerio de Salud de SDE'],                    
                        'methods' => [ 
                            'SetHeader'=>['Planilla 7 - Ministerio de Salud de SDE'], 
                            'SetFooter'=>['{PAGENO}'],
                        ]
                    ]);                
                    
                    return $pdf->render();
                }else{
                    $mensaje =  "No se encontraron atenciones para el profesional ".$nombreMedico." el período desde ".$diaD." ".$mesD." ".$anioD." a ".$diaH." ".$mesH." ".$anioH;
                } 
            }else{
                $mensaje =  "Debe seleccionar médico y tipo de atención.";
               
            }
                
        }
        return $this->render('formPlanillaC7', [
            'data' => '',
            'idEfector'=> $idEfector,
            'servicio' => $servicio,            
            'resultados'=> $resultados,
            'mensaje'=>$mensaje
        ]);

    }

    public function obtenerMes($fecha){
        $meses = [
            '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo',
            '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio',
            '07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre',
            '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
         ];
        return $meses[date("m",strtotime($fecha))];

    }

}
