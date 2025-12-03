<?php

namespace frontend\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\helpers\ArrayHelper;
use yii\web\NotFoundHttpException;
use yii\httpclient\Client;

//use webvimark\modules\UserManagement\UserManagementModule;
use webvimark\modules\UserManagement\models\User;

use frontend\modules\mapear\controllers;

use common\models\LoginForm;
use common\models\Efector;
use common\models\ContactForm;
use common\models\Consulta;
use common\models\Rrhh;
use common\models\RrhhEfector;
use common\models\RrhhServicio;
use common\models\Persona;
use common\models\Agenda_rrhh;
use common\models\Turno;
use common\models\Guardia;
use common\models\Novedad;
use common\models\InfraestructuraPiso;
use common\models\InfraestructuraCama;

class SiteController extends Controller
{
    /*
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }
    */

    public function actions()
    {
        return [

            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    public function actionInicio()
    {
        $this->layout = 'main';

        // Obtener fecha desde parámetro o usar hoy
        $fechaParam = Yii::$app->request->get('fecha');
        if ($fechaParam) {
            $fecha = date('Y-m-d', strtotime($fechaParam));
        } else {
            $fecha = date('Y-m-d');
        }

        // Obtener turnos para la fecha seleccionada
        $idRrhh = Yii::$app->user->getIdRecursoHumano();
        $turnos = [];
        if ($idRrhh) {
            $turnos = Turno::getTurnosPorRrhhPorFecha($fecha, $idRrhh);
        }

        return $this->render('inicio-turnos', [
            'turnos' => $turnos,
            'fecha' => $fecha,
        ]);
    }

    public function actionAcciones()
    {
        $this->layout = 'main';

        return $this->render('acciones');
    }


    public function actionIndex()
    {
        if (Yii::$app->user->isGuest) {
            Yii::$app->user->loginRequired();
            return;
        }
        
        $this->layout = 'main';

        // Obtener fecha desde parámetro o usar hoy
        $fechaParam = Yii::$app->request->get('fecha');
        if ($fechaParam) {
            $fecha = date('Y-m-d', strtotime($fechaParam));
        } else {
            $fecha = date('Y-m-d');
        }

        // Obtener turnos para la fecha seleccionada
        $idRrhh = Yii::$app->user->getIdRecursoHumano();
        $turnos = [];
        if ($idRrhh) {
            $turnos = Turno::getTurnosPorRrhhPorFecha($fecha, $idRrhh);
        }

        return $this->render('inicio-turnos', [
            'turnos' => $turnos,
            'fecha' => $fecha,
        ]);
    }

    public function buscarInternados($pisos_efector, $sala)
    {
        $internados = array();
        $i = 0;
        foreach ($pisos_efector as $key => $piso) {

            $salas = $piso->infraestructuraSalas;

            foreach ($salas as $key => $sala) {

                $camas = $sala->infraestructuraCamas;

                foreach ($camas as $key => $cama) {

                    if ($cama->estado == 'ocupada') {
                        $i++;
                        if ($i > 5) continue;
                        $url = "internacion/view";
                        $id = $cama->internacionActual->id;

                        if (is_object($cama->internacionActual)) {
                            $internados[$id]['id'] = $id;
                            $internados[$id]['id_persona'] = $cama->internacionActual->id_persona;
                            $internados[$id]['nombre'] = $cama->internacionActual->paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D);
                            $internados[$id]['cama'] = $cama->nro_cama;
                            $internados[$id]['sala'] = $sala->nro_sala;
                            $internados[$id]['piso'] = $piso->nro_piso;
                        } else {
                            $internados[$id]['nombre'] = "Cama " . $cama->nro_cama . " - Ocupada";
                        }
                    }
                }
            }
        }

        return $internados;
    }

    /**
     * Se la llama desde config/web
     * 1. components/UserConfig afterLogin
     * 2. SiteController despuesDeLogin para establecer permisos iniciales, antes de decidir el Efector
     * 3. En pantalla para seleccionar el Efector se redirecciona y va a establecerSessionFinal
     */
    public static function despuesDeLogin()
    {
        if (Yii::$app->user->isSuperadmin) {
            Yii::$app->response->redirect(['site/index'])->send();
            return;
        }

        $urlARedireccionar = self::establecerSessionInicial();
        Yii::$app->response->redirect($urlARedireccionar)->send();
        return;
    }

    /**
     * Llega hasta aqui despues de elegir el efector con el que desea trabajar
     * setea en session el efector elegido y redirige a elegir el encounter class
     */
    public function actionSessionEfectorRedireccionar()
    {
        return $this->redirect(['site/inicio']);
    }

    /**
     * Llega hasta aqui despues de elegir el encounter class
     * setea en session el encounter y hace la redireccion final
     */
    /*public function actionSessionEncounterclassRedireccionar($codigo)
    {
        $url = self::establecerSessionFinal();

        return $this->redirect($url);
    }*/

    public function actionCambiarEncounterClass($codigo)
    {
        Yii::$app->user->setEncounterClass($codigo);

        return $this->redirect(self::generarUrlUsurioEfectorAredireccionar());
    }

    public function actionCambiarServicio($id_servicio)
    {
        Yii::$app->user->setServicioActual($id_servicio);

        $servicioDelRrhh = RrhhServicio::find()
            ->select(['id'])
            ->andWhere(['id_servicio' => $id_servicio])
            ->andWhere(['id_rr_hh' => Yii::$app->user->getIdRecursoHumano()])
            ->one();

        Yii::$app->user->setIdRrhhServicio($servicioDelRrhh->id);

        return $this->redirect(self::generarUrlUsurioEfectorAredireccionar());
    }

    public function actionGuiaServicios()
    {
        return $this->render('guia-servicios');
    }

    public function actionCentrosSalud($id)
    {
        if (isset($id) and $id != 0) {
            return $this->render('centros-salud', ['id' => $id]);
        }
    }

    public function actionVerCentroSalud($id)
    {
        $efector = Efector::findOne($id);
        return $this->render('ver-centro-salud', [
            'model' => $efector,
        ]);
    }

    /*
    * establece en session el id de efector, de recurso humano y/o los permisos que tiene para el efector usando updatePermissions
    */
    private static function establecerSessionInicial()
    {
        // Confirmamos que el usuario no este asociado a un efector
        $rrhh_efectores = RrhhEfector::getEfectores(Yii::$app->user->getIdPersona());

        // Si el usuario no esta en ningun efector se puede tratar de un usuario con permisos para todos los efectores o ninguno
        if (count($rrhh_efectores) == 0) {
            \webvimark\modules\UserManagement\components\AuthHelper::updatePermissions(Yii::$app->user);
            $keys = Yii::$app->session->get(\webvimark\modules\UserManagement\components\AuthHelper::SESSION_PREFIX_ROLES);

            $x_efector = false;
            foreach ($keys as $key) {
                if (strpos($key, '_x_efector_') !== false) {
                    $x_efector = true;
                    break;
                }
            }

            if (!$x_efector) {
                Yii::$app->user->logout();
                Yii::$app->session->setFlash(
                    'info',
                    'Usted no cuenta con los permisos necesarios para ingresar al sistema, comuníquese con su Administrador de Efector'
                );

                return [Yii::$app->user->loginUrl[0]];
            }
        }

        /*  if (count($rrhh_efectores) == 1) {
            // Seteamos el efector con el que el usuario trabajará          
            Yii::$app->user->setIdEfector($rrhh_efectores[0]['id_efector']);
            Yii::$app->user->setNombreEfector($rrhh_efectores[0]['nombre']);
            Yii::$app->user->setIdRecursoHumano($rrhh_efectores[0]['id_rr_hh']);
            $rrhhServicio = RrhhServicio::findActive()->andWhere(['id_rr_hh' => $rrhh_efectores[0]['id_rr_hh']])->all();
            Yii::$app->user->setServicios(ArrayHelper::map($rrhhServicio, 'id_servicio', 'servicio.nombre'));

            \webvimark\modules\UserManagement\components\AuthHelper::updatePermissions(Yii::$app->user);

            self::establecerAgendaDisponible($rrhh_efectores[0]['id_rr_hh']);

            //   return ['consultas/tipoatencion'];
        }*/

        // En session todos los efectores en los que el usuario trabaja
        // para que luego pueda cambiar si necesita
        Yii::$app->user->setEfectores(ArrayHelper::map($rrhh_efectores, 'id_efector', 'nombre'));

        return ['site/inicio'];
    }

    /*private static function establecerSessionEfectores($id_efector)
    {
        $rrhh_efector = RrhhEfector::find()->where(['id_efector' => $id_efector, 'id_persona' => Yii::$app->user->getIdPersona()])->one();

        // Si el usuario selecciona un efector y llega hasta aqui, pero no esta en RrhhEfector
        // quiere decir que es un usuario que tiene permisos para ver cualquier efector sin ser un recurso humano
        if (!$rrhh_efector) {

            $efector = Efector::find()->where(['id_efector' => $id_efector])->one();
        
            Yii::$app->user->setIdEfector($efector->id_efector);
            Yii::$app->user->setNombreEfector($efector->nombre);

            return ['site/index'];
        }

        Yii::$app->user->setIdEfector($rrhh_efector->id_efector);
        Yii::$app->user->setNombreEfector($rrhh_efector->efector->nombre);
        Yii::$app->user->setIdRecursoHumano($rrhh_efector->id_rr_hh);
        Yii::$app->user->setServicios(ArrayHelper::map($rrhh_efector->rrhhServicio, 'id_servicio', 'servicio.nombre'));
        // AuthHelper::updatePermissions recibe como parametro id_user pero no lo utiliza
        // debido al cambio en config/web.php 'components' => ['authManager'...
        \webvimark\modules\UserManagement\components\AuthHelper::updatePermissions(Yii::$app->user);

        self::establecerAgendaDisponible($rrhh_efector->id_rr_hh);
        
        return ['consultas/tipoatencion'];
    }*/

    public function actionEstablecerSessionFinal()
    {
        $encounterClass = Yii::$app->request->post('encounterClass');
        Yii::$app->user->setEncounterClass($encounterClass);

        $servicio = Yii::$app->request->post('servicio');
        Yii::$app->user->setServicioActual($servicio);

        $idEfector = Yii::$app->request->post('idEfector');
        $rrhhEfector = RrhhEfector::find()
            ->where([
                'id_efector' => $idEfector,
                'id_persona' => Yii::$app->user->getIdPersona()
            ])
            ->one();

        Yii::$app->user->setIdEfector($rrhhEfector->id_efector);
        Yii::$app->user->setNombreEfector($rrhhEfector->efector->nombre);
        Yii::$app->user->setIdRecursoHumano($rrhhEfector->id_rr_hh);

        // Todos los servicios que tiene disponibles para este efector
        Yii::$app->user->setServicios(ArrayHelper::map($rrhhEfector->rrhhServicio, 'id_servicio', 'servicio.nombre'));
        // AuthHelper::updatePermissions recibe como parametro id_user pero no lo utiliza
        // debido al cambio en config/web.php 'components' => ['authManager'...
        \webvimark\modules\UserManagement\components\AuthHelper::updatePermissions(Yii::$app->user);

        self::establecerAgendaDisponible($rrhhEfector->id_rr_hh);

        return \yii\helpers\Url::to(self::generarUrlUsurioEfectorAredireccionar());
    }
    /*
    * Despues de elegir el Efector se lo redirige al usuario a diferentes paginas 
    * dependiendo del rol/profesion que disponga
    */
    private static function generarUrlUsurioEfectorAredireccionar()
    {
        User::hasRole(['Medico']);
        if (User::hasRole(['Medico'])) {
            $url = ['/turnos/espera'];
        }

        if (User::hasRole(['Administrativo'])) {
            // Usuarios administrativos van a la nueva página de inicio con IA
            $url = ['/site/index'];
        } elseif (User::hasRole(['Enfermeria'])) {
            $url = ['/personas/buscar-persona'];
        } else {
            $url = ['/site/index'];
        }

        return $url;
    }

    /**
     * Establece en session un array que nos va a pemitir saber la agenda del dia actual
     */
    private static function establecerAgendaDisponible($id_rr_hh)
    {
        $serviciosDelRrhh = RrhhServicio::find()
            ->select(['id', 'id_servicio'])
            ->andWhere(['id_rr_hh' => $id_rr_hh])
            ->asArray()
            ->all();

        foreach ($serviciosDelRrhh as $servicioDelRrhh) {
            if (Yii::$app->user->getServicioActual() == $servicioDelRrhh["id_servicio"]) {
                Yii::$app->user->setIdRrhhServicio($servicioDelRrhh["id"]);
            }
        }

        $nroDiaDeSemana = date('N') - 1;
        $nroDiaDeSemanaManiana = $nroDiaDeSemana == 6 ? 0 : $nroDiaDeSemana + 1;
        $columnasAgenda = ['lunes_2', 'martes_2', 'miercoles_2', 'jueves_2', 'viernes_2', 'sabado_2', 'domingo_2'];
        $agendas = Agenda_rrhh::find()
            ->andWhere(['in', 'id_rrhh_servicio_asignado', ArrayHelper::getColumn($serviciosDelRrhh, 'id')])
            ->all();

        $servicios = [$nroDiaDeSemana => [], ($nroDiaDeSemana + 1) => []];
        foreach ($agendas as $agenda) {
            if (($agenda->{$columnasAgenda[$nroDiaDeSemana]} == null || $agenda->{$columnasAgenda[$nroDiaDeSemana]} == "")
                && ($agenda->{$columnasAgenda[$nroDiaDeSemanaManiana]} == null || $agenda->{$columnasAgenda[$nroDiaDeSemanaManiana]} == "")
            ) {
                continue;
            }

            $horasDeAgendaHoy = explode(",", $agenda->{$columnasAgenda[$nroDiaDeSemana]});
            $servicios[$nroDiaDeSemana] = [
                $agenda->rrhhServicioAsignado->id_servicio => [
                    'nombreServicio' => $agenda->rrhhServicioAsignado->servicio->nombre,
                    'horaInicial' => $horasDeAgendaHoy[0],
                    'horaFinal' => $horasDeAgendaHoy[count($horasDeAgendaHoy) - 1],
                ]
            ];
            // Sumo las de mañana por las dudas haya una agenda con horario corrido desde un dia al otro
            $horasDeAgendaManiana = explode(",", $agenda->{$columnasAgenda[$nroDiaDeSemanaManiana]});
            $servicios[$nroDiaDeSemana + 1] = [
                $agenda->rrhhServicioAsignado->id_servicio => [
                    'nombreServicio' => $agenda->rrhhServicioAsignado->servicio->nombre,
                    'horaInicial' => $horasDeAgendaManiana[0],
                    'horaFinal' => $horasDeAgendaManiana[count($horasDeAgendaManiana) - 1],
                ]
            ];
        }

        Yii::$app->user->setServicioYhorarioDeTurno($servicios);
    }

    public function actionImpersonate()
    {
        $id = file_get_contents(Yii::getAlias('@runtime') . '/impersonation/a.txt');

        $user = User::findOne($id);

        Yii::$app->user->login($user, $duration = 0);

        file_put_contents(Yii::getAlias('@runtime') . '/impersonation/a.txt', "", LOCK_EX);
    }

    public function actionError()
    {
        $exception = Yii::$app->errorHandler->exception;

        if ($exception instanceof yii\web\TooManyRequestsHttpException) {
            $this->layout = 'publico/error';
        }
        return $this->render('error', ['exception' => $exception]);
    }

    public function actionPrueba2()
    {
        die;
        // esto tenemos que usar en el paso despues de guardar la consulta para encontrar un beneficiario activo para la autofacturacion
        $beneficiario = \common\models\BeneficiarioSumar::find()
            ->where([
                'tipo_documento' => 'DNI',
                'numero_doc' => 39453011,
                'sexo' => 'M',
                'fecha_nacimiento_benef' => '2015-11-17',
                'activo' => ['S', '1'] // 1
            ])
            ->one();

        echo "nombre: " . $beneficiario->nombre_benef . ", ";
        echo "apellido: " . $beneficiario->apellido_benef . ", ";
        echo "tipo_documento: " . $beneficiario->tipo_documento . ", ";
        echo "numero_doc: " . $beneficiario->numero_doc . ", ";
        echo "clave_beneficiario: " . $beneficiario->clave_beneficiario . "<br><br>";


        $codigos = \common\models\NomencladorSumar::find()
            ->where([
                'codigo' => 'CT-C001-A97',
            ])
            ->all();
        foreach ($codigos as $key => $codigo) {
            # code...
            echo "codigo: " . $codigo->codigo . ", ";
            echo "descripcion: " . $codigo->descripcion . ", ";
            echo "grupo: " . $codigo->grupo . ", ";
            echo "sexo: " . $codigo->sexo . ", ";
            echo "ruralidad: " . $codigo->ruralidad . "<br>";
        }

        die;
    }

    public function actionPrueba()
    {
        die;
        Yii::$app->db->createCommand()->update('rr_hh', ['status' => 1], 'age > 30')->execute();
        $prescripcion = new \common\models\bundles\Prescripcion();
        $prescripcion->timestamp = date("c");

        $medicationRequestUri = uniqid('urn:uuid:');
        $entry = new \common\models\bundles\Entry();
        $entry->fullUrl = $medicationRequestUri;

        $medicationRequest = new \common\models\bundles\MedicationRequest();
        $entry->resource = $medicationRequest;
        $prescripcion->entry[] = $entry;

        $entry = new \common\models\bundles\Entry();
        $medicationRequest = new \common\models\bundles\MedicationRequest();
        $entry->resource = $medicationRequest;
        $prescripcion->entry[] = $entry;


        var_dump(json_encode($prescripcion));
        die;
        $r = Yii::$app->snowstorm->getMedicamentosGenericos("319775004");
        //"319775004 | aspirina 75 mg por cada comprimido para administración oral and tiene valor de numerador para la potencia de presentación (atributo)"
        //$r = Yii::$app->snowstorm->busquedaPorConceptId(395278001);
        //$r = Yii::$app->snowstorm->busquedaPorSemantica("delirios", "disorder");
        echo "<pre>";
        print_r($r);
        echo "</pre>";
        die;
        $historial = [];
        $personas_id = [];

        // var_dump(Yii::$app->user->getIdRecursoHumano()); die;
        // $turnos = \common\models\Turno::find()
        //             ->select(['turnos.id_turnos','turnos.id_persona', 'turnos.fecha', 'turnos.hora', 'turnos.fecha_alta', 'turnos.created_at',
        //             'efectores.nombre', 'rrhh_efector.id_rr_hh',
        //             'servicios.nombre AS nombre_servicio', 'CONCAT(personas.apellido, ", ", personas.nombre) AS medico'])  
        //             ->leftJoin('rrhh_servicio', 'turnos.id_rrhh_servicio_asignado = rrhh_servicio.id')
        //             ->leftJoin('rrhh_efector', 'rrhh_efector.id_rr_hh = rrhh_servicio.id_rr_hh')
        //             ->leftJoin('personas', 'rrhh_efector.id_persona = personas.id_persona')
        //             ->leftJoin('servicios', 'servicios.id_servicio = rrhh_servicio.id_servicio')
        //             ->leftJoin('efectores', 'efectores.id_efector = rrhh_efector.id_efector')
        //             ->andWhere('turnos.atendido is null')               
        //             ->limit(500)->asArray()->all();

        // foreach ($turnos as $turno) {
        //     if ($turno['nombre'] == null) { continue; }

        //     $personas_id[] = $turno['id_persona'];
        //     $historial[] = [
        //                 'id_persona' => $turno['id_persona'], 
        //                 'resumen' => 'Turno',
        //                 'efector' => $turno['nombre'],
        //                 'servicio' => $turno['nombre_servicio'],
        //                 'id_rr_hh' => $turno['id_rr_hh'],
        //                 'rr_hh' => $turno['medico'],
        //                 'parent_id' => $turno['id_turnos'], 
        //                 'parent_class' => 'Turno', 
        //                 'created_at' => $turno['fecha_alta'] == null ? $turno['created_at'] : $turno['fecha_alta'],
        //     ];
        // }

        // $consultas = \common\models\Consulta::find()
        //             ->select(['turnos.id_persona', 'consultas.id_consulta', 'turnos.fecha', 
        //             'turnos.hora', 'rrhh_efector.id_rr_hh', 'efectores.nombre', 'consultas.motivo_consulta',
        //             'servicios.nombre AS nombre_servicio', 'CONCAT(personas.apellido, ", ", personas.nombre) AS medico'])
        //             ->leftJoin('turnos', 'consultas.id_turnos = turnos.id_turnos')
        //             ->leftJoin('rrhh_servicio', 'turnos.id_rrhh_servicio_asignado = rrhh_servicio.id')
        //             ->leftJoin('rrhh_efector', 'rrhh_efector.id_rr_hh = rrhh_servicio.id_rr_hh')
        //             ->leftJoin('personas', 'rrhh_efector.id_persona = personas.id_persona')
        //             ->leftJoin('servicios', 'servicios.id_servicio = rrhh_servicio.id_servicio')
        //             ->leftJoin('efectores', 'efectores.id_efector = rrhh_efector.id_efector')
        //             ->limit(500)->asArray()->all();


        // foreach ($consultas as $consulta) {
        //     $personas_id[] = $consulta['id_persona'];
        //     $historial[] = [
        //                 'id_persona' => $consulta['id_persona'], 
        //                 'resumen' => 'Consulta por: '.$consulta['motivo_consulta'],
        //                 'efector' => $consulta['nombre'],
        //                 'servicio' => $consulta['nombre_servicio'],
        //                 'id_rr_hh' => $consulta['id_rr_hh'],
        //                 'rr_hh' => $consulta['medico'],                        
        //                 'parent_id' => $consulta['id_consulta'], 
        //                 'parent_class' => 'Consulta', 
        //                 'created_at' => $consulta['fecha'].' '.$consulta['hora']]; // esto esta asi de mal porque consultas no tiene el created_at
        // }

        // $encuestas = \common\models\EncuestaParchesMamarios::find()->select('id, created_at')
        //                 ->where(['in', 'id_persona', $personas_id])->all();
        // foreach ($encuestas as $encuesta) {
        //     $historial[] = [
        //         'id_persona' => $encuesta->id_persona,
        //         'resumen' => 'Control con parches mamarios - resultado '.$encuesta->resultado,
        //         'efector' => $encuesta->efector->nombre,
        //         'servicio' => 'Obstetricia',
        //         'id_rr_hh' => $encuesta->operador->id_rr_hh,
        //         'rr_hh' => $encuesta->operador->persona->apellido.', '.$encuesta->operador->persona->nombre,
        //         'parent_id' => $encuesta->id, 
        //         'parent_class' => 'EncuestaParchesMamarios', 
        //         'created_at' => $encuesta->created_at];
        // }

        $internaciones = \common\models\SegNivelInternacion::find()->select('*')
            ->where(['=', 'id_persona', 1])->all();

        foreach ($internaciones as $internacion) {
            $historial[] = [
                'id_persona' => $internacion->id_persona,
                'resumen' => $internacion->tipo_ingreso . ': ' . $internacion->fecha_inicio . ':' . $internacion->hora_inicio . '-' . $internacion->fecha_fin . ':' . $internacion->hora_fin,
                'efector' =>  $internacion->cama->sala->piso->id_efector,
                'servicio' => 'Obstetricia',
                'id_rr_hh' =>  $internacion->id_rrhh,
                'rr_hh' => '',
                'parent_id' => $internacion->id,
                'parent_class' => 'SegNivelInternacion',
                'created_at' => $internacion->created_at
            ];
        }

        usort($historial, function ($a, $b) {
            return $a['created_at'] <=> $b['created_at'];
        });

        //var_dump($historial);die;

        foreach ($historial as $historia) {
            $paciente = new \common\models\PacienteHistorial();
            $paciente->id_persona = $historia['id_persona'];
            // Este fecha lo necesito porque la tabla consultas no tiene el metadata
            // created_at
            $paciente->fecha = $historia['created_at'];

            $paciente->resumen = $historia['resumen'];
            $paciente->efector = $historia['efector'];
            $paciente->servicio = $historia['servicio'];
            $paciente->id_rr_hh = $historia['id_rr_hh'];
            $paciente->rr_hh = $historia['rr_hh'];

            $paciente->parent_class = $historia['parent_class'];
            $paciente->parent_id = $historia['parent_id'];
            $paciente->save();
        }
    }


    public function actionPruebaSnomed()
    {
        die;
        $json = Yii::$app->snowstorm->busquedaFiltradaEcl("", "<<313026002");
        var_dump($json);
    }

    public function actionPruebaMpi()
    {
        $mpi_api = new MpiApiController;

        $parametros['apellido'] = "Chanferoni";
        $parametros['nombre'] = "Luis";
        $parametros['documento'] = "29486884";
        $parametros['fecha_nacimiento'] = "1982-07-14";
        $parametros['sexo'] = 1;
        $parametros['tipo_doc'] = 1;

        $resultado = $mpi_api->candidatos($parametros);

        var_dump($resultado);
    }

    public function actionMigrarBdv2()
    {

        $rrhhs = \common\models\Rrhh::find()->select('rr_hh.*, rr_hh_efector.id_efector')
            ->innerJoin('rr_hh_efector', 'rr_hh_efector.id_rr_hh = rr_hh.id_rr_hh');

        echo $rrhhs->createCommand()->sql;
        die;
        /*
        # rr_hh JOIN con rr_hh_efector
        # - los que estan en rr_hh y no estan en rr_hh_efector se los usa para guardar en profesional_salud
        # - los que no estan en rr_hh y estan en rr_hh_efector se los descarta porque se ha perdido la informacion,
        #   se ha perdido el id_persona
        # - los que estan en rr_hh y tambien estan en rr_hh_efector se guardan en rrhh_efector


        # 1. Para guardar en profesional_salud traer desde rr_hh todos los registros
        #   * Habra repetidos para id_persona con la misma profesion, se saltea profesional_salud al tener clave compuesta id_persona id_profesion id_especialidad

        $rrhhs = Rrhh::find()->all();

        foreach($rrhhs as $rrhh) {
            $ps = new \common\models\ProfesionalSalud();

            $ps->id_persona = $rrhh->id_persona;
            $ps->id_profesion = $rrhh->id_profesion;
            $ps->id_especialidad = $rrhh->id_especialidad;

            $ps->save();
            //$this->deleted_at = new yii\db\Expression('NOW()');
        }


        # 2. Para guardar en rrhh_efector hacer un INNER join entre rr_hh y rr_hh_efector

        $rrhhs = Rrhh::find()->select('rr_hh.*, rr_hh_efector.id_efector')->innerJoin(['rr_hh_efector', 'rr_hh_efector.id_rr_hh = rr_hh.id_rr_hh'])->asArray()->all();

        foreach($rrhhs as $rrhh) {
            $rrhhefector = new \common\models\RrhhEfector();

            $rrhhefector->id_rr_hh = $rrhh['id_rr_hh'];
            $rrhhefector->id_persona = $rrhh['id_persona'];
            $rrhhefector->id_efector = $rrhh['id_efector'];
            if ($rrhh['eliminado'] == 1) {
                $rrhhefector->deleted_at = new yii\db\Expression('NOW()');
                $rrhhefector->deleted_by = 1;
            }

            $rrhhefector->save();
        }

        # 3. Para guardar en rrhh_laboral hacer un INNER join entre rr_hh y rr_hh_efector y guardar el id_condicion_laboral
        
        $rrhhs = Rrhh::find()->select('rr_hh.*, rr_hh_efector.id_condicion_laboral')->innerJoin(['rr_hh_efector', 'rr_hh_efector.id_rr_hh = rr_hh.id_rr_hh'])->asArray()->all();
        
        foreach($rrhhs as $rrhh) {
            $rrhhLaboral = new \common\models\RrhhLaboral();

            $rrhhLaboral->id_rr_hh = $rrhh['id_rr_hh'];
            $rrhhLaboral->id_condicion_laboral = $rrhh['id_condicion_laboral'];

            if ($rrhhLaboral['eliminado'] == 1) {
                $rrhhLaboral->deleted_at = new yii\db\Expression('NOW()');
                $rrhhLaboral->deleted_by = 1;
            }

            $rrhhLaboral->save();
        }


        # 4. Para guardar en rrhh_servicio hacer un inner join entre rr_hh y rr_hh_efector y guardar el id_servicio

        $rrhhs = Rrhh::find()->select('rr_hh.*, rr_hh_efector.id_servicio')->innerJoin(['rr_hh_efector', 'rr_hh_efector.id_rr_hh = rr_hh.id_rr_hh'])->asArray()->all();
        
        foreach($rrhhs as $rrhh) {
            $rrhhServicio = new \common\models\RrhhServicio();

            $rrhhServicio->id_rr_hh = $rrhh['id_rr_hh'];
            $rrhhServicio->id_servicio = $rrhh['id_servicio'];

            if ($rrhhServicio['eliminado'] == 1) {
                $rrhhServicio->deleted_at = new yii\db\Expression('NOW()');
                $rrhhServicio->deleted_by = 1;
            }

            $rrhhServicio->save();
        }
        */
    }

    public function actionFixAgenda()
    {
        $agendas = \common\models\Agenda_rrhh::find()
            //->where(['id_rrhh_servicio_asignado' => 3876])
            ->where(['>', 'created_at', '2024-03-27'])
            //->andWhere(['<', 'updated_at', '2024-04-31'])
            //->limit(10)
            ->all();

        $i = 0;

        foreach ($agendas as $agenda) {

            $flag = false;
            $updatedLunes = '';
            $updatedMartes = '';
            $updatedMiercoles = '';
            $updatedJueves = '';
            $updatedViernes = '';
            $updatedSabado = '';
            $updatedDomingo = '';

            $query = "UPDATE agenda_rrhh SET ";

            $lunes = explode(',', $agenda->lunes_2);
            $martes = explode(',', $agenda->martes_2);
            $miercoles = explode(',', $agenda->miercoles_2);
            $jueves = explode(',', $agenda->jueves_2);
            $viernes = explode(',', $agenda->viernes_2);
            $sabado = explode(',', $agenda->sabado_2);
            $domingo = explode(',', $agenda->domingo_2);


            if (count($lunes) > count(array_unique($lunes))) {
                $flag = true;
                $updatedLunes = implode(',', array_unique($lunes));
            }

            if (count($martes) > count(array_unique($martes))) {
                $flag = true;
                $updatedMartes = implode(',', array_unique($martes));;
            }

            if (count($miercoles) > count(array_unique($miercoles))) {
                $flag = true;
                $updatedMiercoles = implode(',', array_unique($miercoles));
            }

            if (count($jueves) > count(array_unique($jueves))) {
                $flag = true;
                $updatedJueves = implode(',', array_unique($jueves));
            }

            if (count($viernes) > count(array_unique($viernes))) {
                $flag = true;
                $updatedViernes = implode(',', array_unique($viernes));
            }

            if (count($sabado) > count(array_unique($sabado))) {
                $flag = true;
                $updatedSabado = implode(',', array_unique($sabado));
            }

            if (count($domingo) > count(array_unique($domingo))) {
                $flag = true;
                $updatedDomingo = implode(',', array_unique($domingo));
            }

            if ($flag) {
                $i++;
                $array = [];
                //echo ($i . ' AGENDA CON ID = ' . $agenda->id_agenda_rrhh . ' DUPLICADA');
                //echo ('<br>');

                //var_dump($updatedLunes, $updatedMartes, $updatedMiercoles, $updatedJueves, $updatedViernes, $updatedSabado, $updatedDomingo);
                //echo ('<br>');

                if ($updatedLunes != '') {

                    $array[] = "lunes_2 = '" . $updatedLunes . "'";
                    //$query = $query . "lunes_2 = " . $updatedLunes. "," ;
                }

                if ($updatedMartes != '') {
                    $array[] = "martes_2 = '" . $updatedMartes . "'";
                   // $query = $query . "martes_2 = " . $updatedMartes . "," ;
                }


                if ($updatedMiercoles != '') {
                    $array[] = "miercoles_2 = '" . $updatedMiercoles . "'";
                    //$query = $query . "miercoles_2 = " . $updatedMiercoles . "," ;
                }


                if ($updatedJueves != '') {
                    $array[] = "jueves_2 = '" . $updatedJueves . "'";
                    //$query = $query . "jueves_2 = " . $updatedJueves . "," ;
                }


                if ($updatedViernes != '') {
                    $array[] = "viernes_2 = '" . $updatedViernes . "'";
                    //$query = $query . "viernes_2 = " . $updatedViernes . "," ;
                }


                if ($updatedSabado != '') {
                    $array[] = "sabado_2 = '" . $updatedSabado . "'";
                   // $query = $query . "sabado_2 = " . $updatedSabado . "," ;
                }


                if ($updatedDomingo != '') {
                    $array[] = "domingo_2 = '" . $updatedDomingo . "'";
                   // $query = $query . "domingo_2 = " . $updatedDomingo . "," ;
                }

                $update = implode(', ',$array);

                $query = $query. $update . " where id_agenda_rrhh = " . $agenda->id_agenda_rrhh. ";";

                echo($query);
                echo ('<br>');
            }
        }

        echo ('SE ENCONTRARON '.$i .' REGISTROS CON VALORES DUPLICADOS');

    }

    /**
     * Procesar consulta con IA (disponible para todos los usuarios)
     * @return \yii\web\Response
     */
    public function actionProcessAdminQuery()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        // Verificar que el usuario esté autenticado
        if (Yii::$app->user->isGuest) {
            return [
                'success' => false,
                'error' => 'Debe estar autenticado para usar esta funcionalidad',
            ];
        }

        $query = Yii::$app->request->post('query');
        
        if (empty($query)) {
            return [
                'success' => false,
                'error' => 'La consulta no puede estar vacía',
            ];
        }

        try {
            // Procesar consulta con IA
            $result = \common\components\AdminQueryIAManager::processAdminQuery($query);
            return $result;
        } catch (\Exception $e) {
            Yii::error("Error procesando consulta: " . $e->getMessage(), 'site-controller');
            return [
                'success' => false,
                'error' => 'Error al procesar la consulta. Por favor, intente nuevamente.',
            ];
        }
    }

    /**
     * Procesar consulta CRUD
     * @return \yii\web\Response
     */
    public function actionProcessCrudQuery()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        // Verificar que el usuario esté autenticado
        if (Yii::$app->user->isGuest) {
            return [
                'success' => false,
                'error' => 'Debe estar autenticado para usar esta funcionalidad',
            ];
        }

        $query = Yii::$app->request->post('query');
        
        if (empty($query)) {
            return [
                'success' => false,
                'error' => 'La consulta no puede estar vacía',
            ];
        }

        try {
            // Procesar consulta CRUD
            $result = \common\components\CrudAgent::processCrudQuery($query);
            return $result;
        } catch (\Exception $e) {
            Yii::error("Error procesando consulta CRUD: " . $e->getMessage(), 'site-controller');
            return [
                'success' => false,
                'error' => 'Error al procesar la consulta. Por favor, intente nuevamente.',
            ];
        }
    }

    /**
     * Obtener acciones comunes para el usuario actual
     * @return \yii\web\Response
     */
    public function actionGetCommonActions()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        // Verificar que el usuario esté autenticado
        if (Yii::$app->user->isGuest) {
            return [
                'success' => false,
                'error' => 'Debe estar autenticado para usar esta funcionalidad',
                'actions' => [],
            ];
        }

        try {
            // Obtener acciones disponibles para el usuario
            $availableActions = \common\components\ActionMappingService::getAvailableActionsForUser();
            
            // Filtrar acciones comunes según el rol del usuario
            $commonActions = self::filterCommonActions($availableActions);
            
            // Limitar a 12 acciones más comunes
            $commonActions = array_slice($commonActions, 0, 12);
            
            // Formatear para respuesta
            $formattedActions = [];
            foreach ($commonActions as $action) {
                $formattedActions[] = [
                    'route' => $action['route'],
                    'name' => $action['display_name'],
                    'description' => $action['description'],
                ];
            }

            return [
                'success' => true,
                'actions' => $formattedActions,
            ];
        } catch (\Exception $e) {
            Yii::error("Error obteniendo acciones comunes: " . $e->getMessage(), 'site-controller');
            return [
                'success' => false,
                'error' => 'Error al cargar acciones comunes',
                'actions' => [],
            ];
        }
    }

    /**
     * Filtrar acciones comunes para usuarios administrativos
     * @param array $actions
     * @return array
     */
    private static function filterCommonActions($actions)
    {
        // Palabras clave que indican acciones comunes para administrativos
        $keywords = [
            'persona', 'buscar', 'crear', 'listar', 'reporte', 'usuario',
            'efector', 'servicio', 'turno', 'consulta', 'configuracion',
            'administrar', 'gestionar'
        ];

        // Ordenar acciones por relevancia
        usort($actions, function($a, $b) use ($keywords) {
            $scoreA = self::calculateRelevanceScore($a, $keywords);
            $scoreB = self::calculateRelevanceScore($b, $keywords);
            return $scoreB <=> $scoreA; // Orden descendente
        });

        return $actions;
    }

    /**
     * Calcular puntuación de relevancia para una acción
     * @param array $action
     * @param array $keywords
     * @return int
     */
    private static function calculateRelevanceScore($action, $keywords)
    {
        $score = 0;
        $text = strtolower($action['display_name'] . ' ' . $action['description'] . ' ' . $action['controller'] . ' ' . $action['action']);
        
        foreach ($keywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                $score += 2;
            }
        }

        // Priorizar acciones de index (listados)
        if ($action['action'] === 'index') {
            $score += 3;
        }

        return $score;
    }
}
