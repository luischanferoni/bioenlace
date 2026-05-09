<?php

namespace common\components\Services\SesionOperativa;

use common\models\ConsultasConfiguracion;
use common\models\ProfesionalEfectorServicio;
use common\models\ProfesionalEfectorServicioAgenda;
use common\models\ProfesionalEfectorServicioCondicionLaboral;
use common\models\Persona;
use common\models\Efector;
use common\models\Servicio;
use common\models\ServiciosEfector;
use Yii;
use yii\base\Component;

/**
 * Valida completitud RRHH por efector y arma el árbol efectores → servicios para el wizard de sesión operativa.
 */
class SesionOperativaProfesionalHabilitacionService extends Component
{
    /** {@see Servicio::item_name} del servicio que otorga rol AdminEfector (excluido del listado clínico del wizard). */
    private const ITEM_NAME_SERVICIO_ADMIN_EFECTOR = 'AdminEfector';

    /**
     * En cada ítem de `servicios`, `id_profesional_efector_servicio` es canónico; `id_rrhh_servicio` repite el mismo id (alias en payload).
     *
     * @return array{
     *   encounter_classes: list<array{code:string,label:string}>,
     *   efectores: list<array{id_efector:int,id:int,nombre:string,servicios:list<array{id_servicio:int,nombre:string,id_profesional_efector_servicio:int,id_rrhh_servicio:int}>}>,
     *   efectores_con_problemas: list<array{id_efector:?int,nombre:?string,message:string,contact:mixed}>
     * }
     */
    public function buildOpcionesIniciales(int $idPersona): array
    {
        $encounterClasses = [];
        foreach (ConsultasConfiguracion::ENCOUNTER_CLASS as $code => $label) {
            $encounterClasses[] = [
                'code' => (string) $code,
                'label' => (string) $label,
            ];
        }

        $idEfectoresPes = ProfesionalEfectorServicio::find()
            ->select(['id_efector'])
            ->distinct()
            ->where(['id_persona' => $idPersona, 'deleted_at' => null])
            ->column();

        if ($idEfectoresPes === []) {
            return [
                'encounter_classes' => $encounterClasses,
                'efectores' => [],
                'efectores_con_problemas' => [[
                    'id_efector' => null,
                    'nombre' => null,
                    'message' => 'No tiene asignaciones profesionales (PES) en ningún efector. Solicite al administrador que lo asigne.',
                    'contact' => null,
                ]],
            ];
        }

        $efectoresOk = [];
        $problemas = [];

        foreach ($idEfectoresPes as $idEfectorRaw) {
            $idEfector = (int) $idEfectorRaw;
            $efectorRow = Efector::findOne($idEfector);
            $nombreEfector = $efectorRow !== null ? (string) $efectorRow->nombre : '';
            $contact = $this->formatContactList($this->getContactosAdministradorEfector($idEfector));

            $tieneLaboralPes = ProfesionalEfectorServicioCondicionLaboral::existeAlgunaActivaParaPersonaEfector(
                $idPersona,
                $idEfector
            );
            if (!$tieneLaboralPes) {
                $problemas[] = [
                    'id_efector' => $idEfector,
                    'nombre' => $nombreEfector,
                    'message' => 'Falta registrar al menos una condición laboral vigente para este efector. El administrador debe completar el alta de RRHH.',
                    'contact' => $contact,
                ];
                continue;
            }

            $candidatosClinicos = [];
            $candidatosAdmin = [];
            $pesRows = ProfesionalEfectorServicio::find()
                ->where([
                    'id_persona' => $idPersona,
                    'id_efector' => $idEfector,
                    'deleted_at' => null,
                ])
                ->with(['servicio'])
                ->all();
            foreach ($pesRows as $pes) {
                if ($pes->servicio === null) {
                    continue;
                }
                if (!$this->pasaFiltroPesEnEfector($pes, $idEfector)) {
                    continue;
                }
                if ($this->isServicioAdministracionEfector($pes->servicio)) {
                    $candidatosAdmin[] = $pes;
                    continue;
                }
                $candidatosClinicos[] = $pes;
            }

            $validPes = [];
            $agendasParaValidar = [];

            if ($candidatosClinicos !== []) {
                foreach ($candidatosClinicos as $pes) {
                    $ag = ProfesionalEfectorServicioAgenda::findActivaPorProfesionalEfectorServicio((int) $pes->id);
                    if ($ag === null) {
                        continue;
                    }
                    if (!$this->agendaCompletaParaServicio($pes->servicio, $ag)) {
                        continue;
                    }
                    $agendasParaValidar[] = $ag;
                    $validPes[] = $pes;
                }

                if ($agendasParaValidar !== [] && !ProfesionalEfectorServicioAgenda::validarGrupoSinSolapamientoEntreAgendas($agendasParaValidar)) {
                    $problemas[] = [
                        'id_efector' => $idEfector,
                        'nombre' => $nombreEfector,
                        'message' => 'La agenda tiene días solapados entre varios servicios en este efector. Corrija la configuración de agendas.',
                        'contact' => $contact,
                    ];
                    continue;
                }

                if ($validPes === []) {
                    if ($candidatosAdmin !== []) {
                        $validPes = $candidatosAdmin;
                    } else {
                        $problemas[] = [
                            'id_efector' => $idEfector,
                            'nombre' => $nombreEfector,
                            'message' => 'Falta configuración de agenda o cupos para los servicios asignados (mismos requisitos que el alta de RRHH).',
                            'contact' => $contact,
                        ];
                        continue;
                    }
                }
            } elseif ($candidatosAdmin !== []) {
                $validPes = $candidatosAdmin;
            } else {
                $problemas[] = [
                    'id_efector' => $idEfector,
                    'nombre' => $nombreEfector,
                    'message' => 'No hay servicios habilitados para su usuario en este efector, o el servicio no está activo en el efector.',
                    'contact' => $contact,
                ];
                continue;
            }

            $serviciosPayload = [];
            foreach ($validPes as $pes) {
                $serviciosPayload[] = [
                    'id_servicio' => (int) $pes->id_servicio,
                    'nombre' => (string) $pes->servicio->nombre,
                    'id_profesional_efector_servicio' => (int) $pes->id,
                    'id_rrhh_servicio' => (int) $pes->id,
                ];
            }

            $efectoresOk[] = [
                'id_efector' => $idEfector,
                'id' => $idEfector,
                'nombre' => $nombreEfector,
                'servicios' => $serviciosPayload,
            ];
        }

        return [
            'encounter_classes' => $encounterClasses,
            'efectores' => $efectoresOk,
            'efectores_con_problemas' => $problemas,
        ];
    }

    /**
     * Actualiza el mapa de efectores en sesión solo con los efectores operativos (wizard).
     *
     * @param list<array{id_efector:int,nombre:string}> $efectoresValidos
     */
    public function syncSessionEfectoresDesdeOpciones(array $efectoresValidos): void
    {
        $map = [];
        foreach ($efectoresValidos as $row) {
            $id = (int) ($row['id_efector'] ?? $row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $map[$id] = (string) ($row['nombre'] ?? '');
        }
        Yii::$app->user->setEfectores($map);
    }

    /**
     * @return list<array{nombre:string,apellido:string}>
     */
    public function getContactosAdministradorEfector(int $idEfector): array
    {
        $adminServicio = Servicio::find()->where(['item_name' => self::ITEM_NAME_SERVICIO_ADMIN_EFECTOR])->one();
        if ($adminServicio === null) {
            return [];
        }

        $sid = (int) $adminServicio->id_servicio;

        $raw = ProfesionalEfectorServicio::find()
            ->alias('pes')
            ->select(['p.id_persona', 'p.nombre', 'p.apellido'])
            ->innerJoin(['p' => Persona::tableName()], 'p.id_persona = pes.id_persona')
            ->andWhere([
                'pes.id_efector' => $idEfector,
                'pes.id_servicio' => $sid,
                'pes.deleted_at' => null,
            ])
            ->asArray()
            ->all();
        $seen = [];
        $out = [];
        foreach ($raw as $r) {
            $pid = (int) ($r['id_persona'] ?? 0);
            if ($pid <= 0 || isset($seen[$pid])) {
                continue;
            }
            $seen[$pid] = true;
            $out[] = ['nombre' => $r['nombre'] ?? '', 'apellido' => $r['apellido'] ?? ''];
        }

        return $out;
    }

    /**
     * @param list<array{nombre?:string,apellido?:string}> $rows
     * @return list<array{nombre_completo:string}>|null
     */
    public function formatContactList(array $rows): ?array
    {
        if ($rows === []) {
            return null;
        }
        $out = [];
        foreach ($rows as $r) {
            $nombre = trim((string) ($r['nombre'] ?? ''));
            $apellido = trim((string) ($r['apellido'] ?? ''));
            $full = trim($nombre . ' ' . $apellido);
            if ($full !== '') {
                $out[] = ['nombre_completo' => $full];
            }
        }

        return $out !== [] ? $out : null;
    }

    public function contactForEfectorPayload(int $idEfector): ?array
    {
        return $this->formatContactList($this->getContactosAdministradorEfector($idEfector));
    }

    private function isServicioAdministracionEfector(Servicio $s): bool
    {
        return (string) $s->item_name === self::ITEM_NAME_SERVICIO_ADMIN_EFECTOR;
    }

    private function pasaFiltroPesEnEfector(ProfesionalEfectorServicio $pes, int $idEfector): bool
    {
        $servicioEfector = ServiciosEfector::findActive()
            ->andWhere([
                'id_efector' => $idEfector,
                'id_servicio' => $pes->id_servicio,
            ])
            ->one();

        $esAdminEfector = $pes->servicio !== null
            && (string) $pes->servicio->item_name === self::ITEM_NAME_SERVICIO_ADMIN_EFECTOR;

        return ($servicioEfector !== null && $servicioEfector->deleted_at === null) || $esAdminEfector;
    }

    private function agendaCompletaParaServicio(Servicio $servicio, ProfesionalEfectorServicioAgenda $ag): bool
    {
        if ($ag->formas_atencion === null || $ag->formas_atencion === '') {
            return false;
        }
        if ($ag->cupo_pacientes === null || $ag->cupo_pacientes === '') {
            return false;
        }

        return $this->agendaTieneAlMenosUnDiaConHorario($ag);
    }

    private function agendaTieneAlMenosUnDiaConHorario(ProfesionalEfectorServicioAgenda $ag): bool
    {
        $cols = ['lunes_2', 'martes_2', 'miercoles_2', 'jueves_2', 'viernes_2', 'sabado_2', 'domingo_2'];
        foreach ($cols as $col) {
            $v = $ag->{$col} ?? null;
            if ($v !== null && $v !== '') {
                return true;
            }
        }

        return false;
    }
}
