<?php

namespace common\components\Domain\Clinical\Encounter\Application\Service;

use common\components\Domain\Clinical\Inpatient\Domain\InpatientClinicalContext;
use common\models\Scheduling\Cirugia;
use common\models\Clinical\Encounter;
use common\models\Clinical\ConsultaDerivaciones;
use common\models\Clinical\Emergency\EmergencyEpisode;
use common\models\Person\Persona;
use common\models\Organization\ProfesionalEfectorServicio;
use common\models\Scheduling\Turno;
use common\models\Clinical\InpatientStay;
use common\models\Organization\ServiciosEfector;
use common\components\Platform\Core\Http\Contract\ApiException;
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
     * @return array{idServicio: int|null, encounterClass: string|null}
     * @throws ApiException cuando el contexto no permite iniciar la captura
     */
    public static function validarPermisoAtencion($parent, $parentId, Persona $paciente): array
    {
        $internacionActiva = InpatientStay::personaInternada($paciente->id_persona);
        $guardiaActiva = EmergencyEpisode::pacienteIngresado($paciente->id_persona);

        if ($internacionActiva || $guardiaActiva) {
            $efectorActual = Yii::$app->user->getIdEfector();
            $efectorCoincide = false;

            if ($internacionActiva) {
                $idEfectorInternacion = $internacionActiva->resolveIdEfectorContextForPes();
                if ($idEfectorInternacion !== null && (int) $idEfectorInternacion === (int) $efectorActual) {
                    $efectorCoincide = true;
                }
            }
            if ($guardiaActiva && $guardiaActiva->id_efector == $efectorActual) {
                $efectorCoincide = true;
            }

            if (!$efectorCoincide) {
                throw new ApiException('capture_subject_other_effector');
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
                throw new ApiException('capture_encounter_class_invalid');
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

                throw new ApiException('appointment_not_found');
            }

            self::validarTurnoCapturable($turno);

            $idServicio = $turno->id_servicio_asignado;
            $encounterClass = Encounter::ENCOUNTER_CLASS_AMB;
            $parentId = $turno->id_turnos;
        }

        if ($parent == Encounter::PARENT_INTERNACION) {
            $idEfector = (int) Yii::$app->user->getIdEfector();
            $internacion = self::resolveInternacionActivaParaCaptura($paciente, $parentId, $idEfector);
            if ($internacion === null) {
                Yii::warning(
                    'validarPermisoAtencion: internación no válida para captura, parentId: ' . $parentId,
                    __METHOD__
                );

                throw new ApiException('capture_active_inpatient_not_found');
            }

            $idServicio = self::resolveServicioParaInternacion($internacion);
            if ($idServicio <= 0) {
                throw new ApiException('capture_inpatient_service_required');
            }

            // Asegura episode + care plan inpatient + encounter IMP abierto (contexto de evolución del episodio).
            try {
                InpatientClinicalContext::ensure($internacion);
            } catch (\Throwable $e) {
                try {
                    (new AdvanceCarePlanLifecycle())->onInpatientAdmission($internacion);
                } catch (\Throwable $e2) {
                    Yii::warning(
                        'No se pudo asegurar contexto clínico de internación #' . $internacion->id
                        . ': ' . $e2->getMessage(),
                        __METHOD__
                    );
                }
            }

            $encounterClass = Encounter::ENCOUNTER_CLASS_IMP;
        }

        if ($parent == Encounter::PARENT_DERIVACION) {
            $derivacion = ConsultaDerivaciones::findOne($parentId);

            if (!$derivacion) {
                Yii::warning('Llamada a getModeloConsulta parentId a una Derivacion que no existe, parentId: ' . $parentId);

                throw new ApiException('capture_referral_not_found');
            }

            $idServicio = $derivacion->id_servicio;
            $encounterClass = Encounter::ENCOUNTER_CLASS_AMB;
        }

        if ($parent == Encounter::PARENT_CIRUGIA) {
            $cirugia = Cirugia::findOne((int) $parentId);
            if (!$cirugia) {
                Yii::warning('validarPermisoAtencion: cirugía inexistente, parentId: ' . $parentId);

                throw new ApiException('capture_surgery_not_found');
            }
            if ((int) $cirugia->id_persona !== (int) $paciente->id_persona) {
                throw new ApiException('capture_surgery_patient_mismatch');
            }
            $sala = $cirugia->sala;
            if (!$sala || $sala->deleted_at !== null) {
                throw new ApiException('capture_operating_room_unavailable');
            }
            $efectorSesion = Yii::$app->user->getIdEfector();
            if ($efectorSesion && (int) $sala->id_efector !== (int) $efectorSesion) {
                throw new ApiException('capture_surgery_other_effector');
            }
            $idServicio = Yii::$app->user->getServicioActual();
            $encounterClass = Encounter::ENCOUNTER_CLASS_IMP;
        }

        if ($parent == Encounter::PARENT_GUARDIA) {
            $guardia = EmergencyEpisode::findOne($parentId);

            if (!$guardia) {
                Yii::warning('Llamada a getModeloConsulta parentId a una Guardia que no existe, parentId: ' . $parentId);

                throw new ApiException('capture_emergency_not_found');
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
                    throw new ApiException('capture_appointment_context_required');
                }

                $idInpatientStay = InpatientStay::personaInternadaEnEfector(
                    $paciente->id_persona,
                    Yii::$app->user->getIdEfector()
                );

                if ($idInpatientStay) {
                    throw new ApiException('capture_patient_inpatient');
                }

                $derivaciones = ConsultaDerivaciones::getDerivacionesActivasPorPacientePorServiciosPorEfector(
                    $paciente->id_persona,
                    [Yii::$app->user->getServicioActual()],
                    Yii::$app->user->getIdEfector()
                );

                if (count($derivaciones) > 0) {
                    throw new ApiException('capture_referral_context_required');
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

                    throw new ApiException('capture_service_context_unavailable');
                }

                if (
                    $servicio->formas_atencion == ServiciosEfector::DERIVACION_DELEGAR_A_CADA_PROFESIONAL
                    || $servicio->formas_atencion == ServiciosEfector::DERIVACION_ORDEN_LLEGADA_PARA_TODOS
                ) {
                    throw new ApiException('capture_referral_required');
                }
            }
        }

        if ($parent == Encounter::PARENT_PASE_PREVIO) {
            $turno = Turno::findOne($parentId);

            if (!$turno) {
                Yii::warning('Llamada a getModeloConsulta parentId a un Turno que no existe, parentId: ' . $parentId);

                throw new ApiException('appointment_not_found');
            }

            self::validarTurnoCapturable($turno);

            $idServicio = $turno->id_servicio_asignado;
            $idEfector = Yii::$app->user->getIdEfector();

            $servPasePrevio = ServiciosEfector::find()
                ->where(['id_efector' => $idEfector])
                ->andWhere(['id_servicio' => $idServicio])
                ->one();

            $idServiocioPP = $servPasePrevio !== null ? $servPasePrevio->pase_previo : null;

            if (!isset($idServiocioPP)) {
                Yii::warning('Llamada a getModeloConsulta con un servicio que no tiene pase previo');

                throw new ApiException('capture_previous_service_unavailable');
            }

            $idServicio = $idServiocioPP;
            $encounterClass = Encounter::ENCOUNTER_CLASS_AMB;
            $parentId = $turno->id_turnos;
        }

        if ($parentId === null) {
            Yii::warning('Llamada a getModeloConsulta sin parentId');

            throw new ApiException('capture_context_unresolved');
        }

        return [
            'idServicio' => $idServicio,
            'encounterClass' => $encounterClass,
        ];
    }

    /**
     * Captura permitida en PENDIENTE o EN_ATENCION. Otros estados: mensaje claro (sin formulario).
     *
     * @throws ApiException cuando el estado del turno no permite capturar
     */
    private static function validarTurnoCapturable(Turno $turno): void
    {
        $estado = (string) $turno->estado;
        if ($estado === Turno::ESTADO_PENDIENTE || $estado === Turno::ESTADO_EN_ATENCION) {
            return;
        }

        Yii::warning(
            'validarPermisoAtencion: turno no capturable, parentId=' . $turno->id_turnos . ' estado=' . $estado
        );

        $errorCode = match ($estado) {
            Turno::ESTADO_ATENDIDO => 'capture_appointment_already_attended',
            Turno::ESTADO_CANCELADO => 'capture_appointment_cancelled',
            Turno::ESTADO_SIN_ATENDER => 'capture_appointment_unattended',
            Turno::ESTADO_EN_RESOLUCION => 'capture_appointment_resolving',
            default => 'capture_appointment_unavailable',
        };
        throw new ApiException($errorCode, ['status' => $estado]);
    }

    /**
     * Internación activa del paciente en el efector (prioriza parent_id de la URL de captura).
     */
    private static function resolveInternacionActivaParaCaptura(
        Persona $paciente,
        $parentId,
        int $idEfector
    ): ?InpatientStay {
        $parentIdInt = (int) $parentId;
        if ($parentIdInt > 0) {
            $internacion = InpatientStay::findOne($parentIdInt);
            if (
                !$internacion instanceof InpatientStay
                || (int) $internacion->id_persona !== (int) $paciente->id_persona
            ) {
                return null;
            }
            if ($internacion->internacionConAlta()) {
                return null;
            }
            $idEfectorInternacion = $internacion->resolveIdEfectorContextForPes();
            if ($idEfectorInternacion !== null && (int) $idEfectorInternacion !== $idEfector) {
                return null;
            }

            return $internacion;
        }

        $idSeg = InpatientStay::personaInternadaEnEfector(
            $paciente->id_persona,
            $idEfector
        );
        if (!$idSeg) {
            return null;
        }
        $internacion = InpatientStay::findOne((int) $idSeg);

        return $internacion instanceof InpatientStay ? $internacion : null;
    }

    /**
     * Servicio institucional para documentar IMP: sesión → PES del episodio → encounter abierto.
     */
    private static function resolveServicioParaInternacion(InpatientStay $internacion): int
    {
        $idServicio = (int) (Yii::$app->user->getServicioActual() ?? 0);
        if ($idServicio > 0) {
            return $idServicio;
        }

        $pesId = (int) ($internacion->id_profesional_efector_servicio ?? 0);
        if ($pesId > 0) {
            $pes = ProfesionalEfectorServicio::find()
                ->andWhere(['id' => $pesId])
                ->andWhere(['deleted_at' => null])
                ->one();
            if ($pes instanceof ProfesionalEfectorServicio && (int) $pes->id_servicio > 0) {
                return (int) $pes->id_servicio;
            }
        }

        $open = InpatientClinicalContext::findOpenInpatientEncounter((int) $internacion->id);
        if ($open !== null && (int) ($open->service_id ?? 0) > 0) {
            return (int) $open->service_id;
        }

        return 0;
    }
}
