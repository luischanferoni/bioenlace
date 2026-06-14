<?php

namespace common\components\Domain\Clinical\Service;

use common\models\Cirugia;
use common\models\Clinical\Encounter;
use common\models\ConsultaDerivaciones;
use common\models\Guardia;
use common\models\Person\Persona;
use common\models\Scheduling\Turno;
use common\models\SegNivelInternacion;
use common\models\ServiciosEfector;
use Yii;

/**
 * Resuelve si el staff puede iniciar captura clínica desde un parent (turno, internación, …)
 * y devuelve servicio + encounter class para lookup de {@see \common\models\Clinical\EncounterDefinition}.
 */
final class EncounterCaptureContextService
{
    /**
     * @param string|null $parent {@see Encounter::PARENT_*}
     * @param int|string|null $parentId
     * @return array{success: bool, msg: string, idServicio: int|null, encounterClass: string|null}
     */
    public static function validarPermisoAtencion($parent, $parentId, Persona $paciente): array
    {
        $internacionActiva = SegNivelInternacion::personaInternada($paciente->id_persona);
        $guardiaActiva = Guardia::pacienteIngresado($paciente->id_persona);

        if ($internacionActiva || $guardiaActiva) {
            $efectorActual = Yii::$app->user->getIdEfector();
            $efectorCoincide = false;

            if ($internacionActiva && $internacionActiva->id_efector == $efectorActual) {
                $efectorCoincide = true;
            }
            if ($guardiaActiva && $guardiaActiva->id_efector == $efectorActual) {
                $efectorCoincide = true;
            }

            if (!$efectorCoincide) {
                return [
                    'success' => false,
                    'msg' => 'El paciente está en un efector diferente al seleccionado. Para cargar la consulta debe cambiar el efector desde el menú superior.',
                    'idServicio' => null,
                    'encounterClass' => null,
                ];
            }
        }

        if ($parent == null) {
            Yii::warning('Llamada a getModeloConsulta sin parent');
            $encounterClass = Yii::$app->user->getEncounterClass();
            $idServicio = Yii::$app->user->getServicioActual();

            $isValidEncounter = in_array($encounterClass, [
                Encounter::ENCOUNTER_CLASS_AMB,
                Encounter::ENCOUNTER_CLASS_EMER,
            ], true);

            if (!$isValidEncounter) {
                return [
                    'success' => false,
                    'msg' => 'El tipo de encuentro determina el tipo de consulta que se creará, por lo que es importante seleccionar el correcto antes de proceder.',
                    'idServicio' => null,
                    'encounterClass' => null,
                ];
            }

            $parent = ($encounterClass === Encounter::ENCOUNTER_CLASS_AMB)
                ? Encounter::PARENT_GENERICO_AMB
                : Encounter::PARENT_GENERICO_EMER;

            $parentId = 0;
        }

        if ($parent == Encounter::PARENT_TURNO) {
            $turno = Turno::findOne($parentId);

            if (!$turno) {
                Yii::warning('Llamada a getModeloConsulta parentId a un Turno que no existe, parentId: ' . $parentId);

                return [
                    'success' => false,
                    'msg' => 'Ocurrio un error con el turno, por favor comunicarse con los administradores de SISSE',
                    'idServicio' => null,
                    'encounterClass' => null,
                ];
            }

            if ($turno->estado !== Turno::ESTADO_PENDIENTE) {
                Yii::warning('Llamada a getModeloConsulta parentId a un Turno que no pendiente, parentId: ' . $parentId);

                return [
                    'success' => false,
                    'msg' => 'Ocurrio un error el turno ya fue atendido, por favor comunicarse con los administradores de SISSE',
                    'idServicio' => null,
                    'encounterClass' => null,
                ];
            }

            $idServicio = $turno->id_servicio_asignado;
            $encounterClass = Encounter::ENCOUNTER_CLASS_AMB;
            $parentId = $turno->id_turnos;
        }

        if ($parent == Encounter::PARENT_INTERNACION) {
            $idSegNivelInternacion = SegNivelInternacion::personaInternadaEnEfector(
                $paciente->id_persona,
                Yii::$app->user->getIdEfector()
            );

            if (!$idSegNivelInternacion) {
                Yii::warning('Llamada a getModeloConsulta parentId a un SegNivelInternacion que no existe, parentId: ' . $parentId);

                return [
                    'success' => false,
                    'msg' => 'Ocurrio un error con la internacion, por favor comunicarse con los administradores de SISSE',
                    'idServicio' => null,
                    'encounterClass' => null,
                ];
            }

            $idServicio = Yii::$app->user->getServicioActual();
            $encounterClass = Encounter::ENCOUNTER_CLASS_IMP;
        }

        if ($parent == Encounter::PARENT_DERIVACION) {
            $derivacion = ConsultaDerivaciones::findOne($parentId);

            if (!$derivacion) {
                Yii::warning('Llamada a getModeloConsulta parentId a una Derivacion que no existe, parentId: ' . $parentId);

                return [
                    'success' => false,
                    'msg' => 'Ocurrio un error con la derivacion, por favor comunicarse con los administradores de SISSE',
                    'idServicio' => null,
                    'encounterClass' => null,
                ];
            }

            $idServicio = $derivacion->id_servicio;
            $encounterClass = Encounter::ENCOUNTER_CLASS_AMB;
        }

        if ($parent == Encounter::PARENT_CIRUGIA) {
            $cirugia = Cirugia::findOne((int) $parentId);
            if (!$cirugia) {
                Yii::warning('validarPermisoAtencion: cirugía inexistente, parentId: ' . $parentId);

                return [
                    'success' => false,
                    'msg' => 'No se encontró la cirugía indicada.',
                    'idServicio' => null,
                    'encounterClass' => null,
                ];
            }
            if ((int) $cirugia->id_persona !== (int) $paciente->id_persona) {
                return [
                    'success' => false,
                    'msg' => 'La cirugía no corresponde al paciente de esta historia clínica.',
                    'idServicio' => null,
                    'encounterClass' => null,
                ];
            }
            $sala = $cirugia->sala;
            if (!$sala || $sala->deleted_at !== null) {
                return [
                    'success' => false,
                    'msg' => 'La sala de quirófano asociada no está disponible.',
                    'idServicio' => null,
                    'encounterClass' => null,
                ];
            }
            $efectorSesion = Yii::$app->user->getIdEfector();
            if ($efectorSesion && (int) $sala->id_efector !== (int) $efectorSesion) {
                return [
                    'success' => false,
                    'msg' => 'La cirugía pertenece a otro efector. Cambie el efector en el menú superior.',
                    'idServicio' => null,
                    'encounterClass' => null,
                ];
            }
            $idServicio = Yii::$app->user->getServicioActual();
            $encounterClass = Encounter::ENCOUNTER_CLASS_IMP;
        }

        if ($parent == Encounter::PARENT_GUARDIA) {
            $guardia = Guardia::findOne($parentId);

            if (!$guardia) {
                Yii::warning('Llamada a getModeloConsulta parentId a una Guardia que no existe, parentId: ' . $parentId);

                return [
                    'success' => false,
                    'msg' => 'Ocurrio un error con la Guardia, por favor comunicarse con los administradores de SISSE',
                    'idServicio' => null,
                    'encounterClass' => null,
                ];
            }

            $idServicio = Yii::$app->user->getServicioActual();
            $encounterClass = Encounter::ENCOUNTER_CLASS_EMER;
        }

        if ($parent == Encounter::PARENT_GENERICO_AMB || $parent == Encounter::PARENT_GENERICO_EMER) {
            $idServicio = Yii::$app->user->getServicioActual();
            $encounterClass = Yii::$app->user->getEncounterClass();
            if ($encounterClass == Encounter::ENCOUNTER_CLASS_AMB) {
                $turno = $paciente->turnoHoy(
                    $idServicio,
                    Yii::$app->user->getIdProfesionalEfectorServicio(),
                    Yii::$app->user->getIdEfector()
                );

                if ($turno) {
                    return [
                        'success' => false,
                        'msg' => 'El paciente tiene un turno para hoy, para su servicio. Por favor busque el turno en la historia clínica y realice la atención desde dicho turno.',
                        'idServicio' => null,
                        'encounterClass' => null,
                    ];
                }

                $idSegNivelInternacion = SegNivelInternacion::personaInternadaEnEfector(
                    $paciente->id_persona,
                    Yii::$app->user->getIdEfector()
                );

                if ($idSegNivelInternacion) {
                    return [
                        'success' => false,
                        'msg' => 'El paciente se encuentra actualmente internado. En su historia clínica verifique los detalles de la internación y comuníquese con el personal indicado para solicitar el alta de ser necesario',
                        'idServicio' => null,
                        'encounterClass' => null,
                    ];
                }

                $derivaciones = ConsultaDerivaciones::getDerivacionesActivasPorPacientePorServiciosPorEfector(
                    $paciente->id_persona,
                    [Yii::$app->user->getServicioActual()],
                    Yii::$app->user->getIdEfector()
                );

                if (count($derivaciones) > 0) {
                    return [
                        'success' => false,
                        'msg' => 'El paciente tiene un derivación para su servicio. Por favor busque el turno en la historia clínica y realice la atención desde dicho turno.',
                        'idServicio' => null,
                        'encounterClass' => null,
                    ];
                }

                $servicio = ServiciosEfector::find()->andWhere([
                    'id_servicio' => Yii::$app->user->getServicioActual(),
                    'id_efector' => Yii::$app->user->getIdEfector(),
                ])->one();

                if (!$servicio) {
                    Yii::warning(
                        'No se puede determinar el servicio del usuario, id_servicio: '
                        . Yii::$app->user->getServicioActual()
                        . ', id_efector: '
                        . Yii::$app->user->getIdEfector()
                    );

                    return [
                        'success' => false,
                        'msg' => 'Ocurrio un error con la atencion, por favor comunicarse con los administradores de SISSE',
                        'idServicio' => null,
                        'encounterClass' => null,
                    ];
                }

                if (
                    $servicio->formas_atencion == ServiciosEfector::DERIVACION_DELEGAR_A_CADA_PROFESIONAL
                    || $servicio->formas_atencion == ServiciosEfector::DERIVACION_ORDEN_LLEGADA_PARA_TODOS
                ) {
                    return [
                        'success' => false,
                        'msg' => 'Este servicio solo acepta consultas con derivación previa',
                        'idServicio' => null,
                        'encounterClass' => null,
                    ];
                }
            }
        }

        if ($parent == Encounter::PARENT_PASE_PREVIO) {
            $turno = Turno::findOne($parentId);

            if (!$turno) {
                Yii::warning('Llamada a getModeloConsulta parentId a un Turno que no existe, parentId: ' . $parentId);

                return [
                    'success' => false,
                    'msg' => 'Ocurrio un error con el turno, por favor comunicarse con los administradores de SISSE',
                    'idServicio' => null,
                    'encounterClass' => null,
                ];
            }

            if ($turno->estado !== Turno::ESTADO_PENDIENTE) {
                Yii::warning('Llamada a getModeloConsulta parentId a un Turno que no pendiente, parentId: ' . $parentId);

                return [
                    'success' => false,
                    'msg' => 'Ocurrio un error el turno ya fue atendido, por favor comunicarse con los administradores de SISSE',
                    'idServicio' => null,
                    'encounterClass' => null,
                ];
            }

            $idServicio = $turno->id_servicio_asignado;
            $idEfector = Yii::$app->user->getIdEfector();

            $servPasePrevio = ServiciosEfector::find()
                ->where(['id_efector' => $idEfector])
                ->andWhere(['id_servicio' => $idServicio])
                ->one();

            $idServiocioPP = $servPasePrevio !== null ? $servPasePrevio->pase_previo : null;

            if (!isset($idServiocioPP)) {
                Yii::warning('Llamada a getModeloConsulta con un servicio que no tiene pase previo');

                return [
                    'success' => false,
                    'msg' => 'Ocurrio un error, por favor comunicarse con los administradores de SISSE',
                    'idServicio' => null,
                    'encounterClass' => null,
                ];
            }

            $idServicio = $idServiocioPP;
            $encounterClass = Encounter::ENCOUNTER_CLASS_AMB;
            $parentId = $turno->id_turnos;
        }

        if ($parentId === null) {
            Yii::warning('Llamada a getModeloConsulta sin parentId');

            return [
                'success' => false,
                'msg' => 'Ocurrio un error, por favor comunicarse con los administradores de SISSE',
                'idServicio' => null,
                'encounterClass' => null,
            ];
        }

        return [
            'success' => true,
            'msg' => '',
            'idServicio' => $idServicio,
            'encounterClass' => $encounterClass,
        ];
    }
}
