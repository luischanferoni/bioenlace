<?php

namespace common\components\Services\Rrhh;

use Yii;
use yii\base\Component;
use common\models\Agenda_rrhh;
use common\models\ConsultasConfiguracion;
use common\models\Persona;
use common\models\RrhhEfector;
use common\models\RrhhLaboral;
use common\models\RrhhServicio;
use common\models\Servicio;
use common\models\ServiciosEfector;

/**
 * Valida completitud RRHH por efector (alineado al alta en RrhhEfectorController) y arma
 * el árbol efectores → servicios elegibles para el wizard de sesión operativa.
 */
class RrhhHabilitacionService extends Component
{
    /** Servicio de administración del efector (excluido del wizard clínico). */
    private const LEGACY_ADMIN_SERVICIO_ID = 62;

    /**
     * @return array{
     *   encounter_classes: list<array{code:string,label:string}>,
     *   efectores: list<array{id_efector:int,id:int,nombre:string,servicios:list<array{id_servicio:int,nombre:string,id_rrhh_servicio:int}>}>,
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

        $rrhhRows = RrhhEfector::findActive()
            ->where(['id_persona' => $idPersona])
            ->with(['efector'])
            ->all();

        if ($rrhhRows === []) {
            return [
                'encounter_classes' => $encounterClasses,
                'efectores' => [],
                'efectores_con_problemas' => [[
                    'id_efector' => null,
                    'nombre' => null,
                    'message' => 'No tiene vínculos de recurso humano con ningún efector. Solicite al administrador que lo asigne.',
                    'contact' => null,
                ]],
            ];
        }

        $efectoresOk = [];
        $problemas = [];

        foreach ($rrhhRows as $re) {
            $idEfector = (int) $re->id_efector;
            $nombreEfector = $re->efector !== null ? (string) $re->efector->nombre : '';
            $contact = $this->formatContactList($this->getContactosAdministradorEfector($idEfector));

            $nLab = (int) RrhhLaboral::findActive()->andWhere(['id_rr_hh' => $re->id_rr_hh])->count();
            if ($nLab < 1) {
                $problemas[] = [
                    'id_efector' => $idEfector,
                    'nombre' => $nombreEfector,
                    'message' => 'Falta registrar al menos una condición laboral vigente para este efector. El administrador debe completar el alta de RRHH.',
                    'contact' => $contact,
                ];
                continue;
            }

            $candidatos = [];
            foreach ($re->getRrhhServicio()->with(['servicio'])->all() as $rs) {
                if ($rs->servicio === null) {
                    continue;
                }
                if ($this->isServicioAdministracionEfector($rs->servicio)) {
                    continue;
                }
                if (!$this->pasaFiltroServicioEnEfector($rs, $idEfector)) {
                    continue;
                }
                $candidatos[] = $rs;
            }

            if ($candidatos === []) {
                $problemas[] = [
                    'id_efector' => $idEfector,
                    'nombre' => $nombreEfector,
                    'message' => 'No hay servicios clínicos habilitados para su usuario en este efector, o el servicio no está activo en el efector.',
                    'contact' => $contact,
                ];
                continue;
            }

            $validServicios = [];
            $agendasParaValidar = [];
            foreach ($candidatos as $rs) {
                $ag = Agenda_rrhh::findActive()
                    ->andWhere(['id_rrhh_servicio_asignado' => $rs->id])
                    ->one();
                if ($ag === null) {
                    continue;
                }
                if (!$this->agendaCompletaParaServicio($rs->servicio, $ag)) {
                    continue;
                }
                $agendasParaValidar[] = $ag;
                $validServicios[] = $rs;
            }

            if ($agendasParaValidar !== [] && !Agenda_rrhh::validarGrupodeAgendas($agendasParaValidar)) {
                $problemas[] = [
                    'id_efector' => $idEfector,
                    'nombre' => $nombreEfector,
                    'message' => 'La agenda tiene días solapados entre varios servicios en este efector. Corrija la configuración de agendas.',
                    'contact' => $contact,
                ];
                continue;
            }

            if ($validServicios === []) {
                $problemas[] = [
                    'id_efector' => $idEfector,
                    'nombre' => $nombreEfector,
                    'message' => 'Falta configuración de agenda o cupos para los servicios asignados (mismos requisitos que el alta de RRHH).',
                    'contact' => $contact,
                ];
                continue;
            }

            $serviciosPayload = [];
            foreach ($validServicios as $rs) {
                $serviciosPayload[] = [
                    'id_servicio' => (int) $rs->id_servicio,
                    'nombre' => (string) $rs->servicio->nombre,
                    'id_rrhh_servicio' => (int) $rs->id,
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
        $adminServicio = Servicio::find()->where(['item_name' => 'AdminEfector'])->one();
        if ($adminServicio === null) {
            $adminServicio = Servicio::find()->where(['nombre' => 'ADMINISTRAR EFECTOR'])->one();
        }
        if ($adminServicio === null) {
            return [];
        }

        $sid = (int) $adminServicio->id_servicio;

        // No usar findActive() aquí: añade `rrhh_efector.deleted_at`, incompatible con ->alias('re') en MySQL.
        return RrhhEfector::find()
            ->alias('re')
            ->andWhere(['re.deleted_at' => null])
            ->select(['p.nombre', 'p.apellido'])
            ->innerJoin(
                ['rs' => RrhhServicio::tableName()],
                'rs.id_rr_hh = re.id_rr_hh AND rs.id_servicio = :sid AND rs.deleted_at IS NULL',
                [':sid' => $sid]
            )
            ->innerJoin(['p' => Persona::tableName()], 'p.id_persona = re.id_persona')
            ->andWhere(['re.id_efector' => $idEfector])
            ->asArray()
            ->all();
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
        if ((int) $s->id_servicio === self::LEGACY_ADMIN_SERVICIO_ID) {
            return true;
        }
        if (strcasecmp((string) $s->nombre, 'ADMINISTRAR EFECTOR') === 0) {
            return true;
        }
        if (($s->item_name ?? '') === 'AdminEfector') {
            return true;
        }

        return false;
    }

    private function pasaFiltroServicioEnEfector(RrhhServicio $rs, int $idEfector): bool
    {
        $servicioEfector = ServiciosEfector::findActive()
            ->andWhere([
                'id_efector' => $idEfector,
                'id_servicio' => $rs->id_servicio,
            ])
            ->one();

        $nombreServicio = $rs->servicio !== null ? (string) $rs->servicio->nombre : '';

        return ($servicioEfector !== null && $servicioEfector->deleted_at === null)
            || $nombreServicio === 'ADMINISTRAR EFECTOR';
    }

    private function agendaCompletaParaServicio(Servicio $servicio, Agenda_rrhh $ag): bool
    {
        if ($ag->formas_atencion === null || $ag->formas_atencion === '') {
            return false;
        }
        if ($ag->cupo_pacientes === null || $ag->cupo_pacientes === '') {
            return false;
        }

        return $this->agendaTieneAlMenosUnDiaConHorario($ag);
    }

    private function agendaTieneAlMenosUnDiaConHorario(Agenda_rrhh $ag): bool
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
