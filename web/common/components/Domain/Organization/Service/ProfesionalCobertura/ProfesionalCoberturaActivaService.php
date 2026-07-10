<?php

namespace common\components\Domain\Organization\Service\ProfesionalCobertura;

use common\components\Domain\Organization\Service\ProfesionalEfectorServicio\AgendaSlotEngine;
use common\components\Platform\Core\Product\AgendaByEncounterClassMetadata;
use common\models\Clinical\Encounter;
use common\models\Person\Persona;
use common\models\ProfesionalCobertura;
use common\models\ProfesionalEfectorServicio;
use common\models\ProfesionalEfectorServicioAgenda;
use common\models\ProfesionalEfectorServicioAgendaVersion;
use common\models\Servicio;

/**
 * Consultas de cobertura activa y conflictos vs grilla AMB.
 */
final class ProfesionalCoberturaActivaService
{
    /**
     * Coberturas vigentes en un instante (default: ahora) para un efector y clase.
     *
     * @return list<array<string, mixed>>
     */
    public static function listarActivas(
        int $idEfector,
        string $encounterClass,
        ?string $atDateTime = null,
        ?int $idServicio = null
    ): array {
        if ($idEfector <= 0 || !AgendaByEncounterClassMetadata::isCoberturaClass($encounterClass)) {
            return [];
        }

        $at = $atDateTime !== null && trim($atDateTime) !== ''
            ? date('Y-m-d H:i:s', strtotime($atDateTime) ?: time())
            : date('Y-m-d H:i:s');

        $q = ProfesionalCobertura::find()
            ->alias('c')
            ->andWhere([
                'c.id_efector' => $idEfector,
                'c.encounter_class' => $encounterClass,
                'c.deleted_at' => null,
            ])
            ->andWhere(['<=', 'c.inicio', $at])
            ->andWhere(['>', 'c.fin', $at])
            ->orderBy(['c.inicio' => SORT_ASC, 'c.id' => SORT_ASC]);

        if ($idServicio !== null && $idServicio > 0) {
            $q->andWhere([
                'or',
                ['c.id_servicio' => $idServicio],
                ['c.id_servicio' => null],
            ]);
        }

        /** @var list<ProfesionalCobertura> $rows */
        $rows = $q->with(['persona', 'servicio'])->all();
        $out = [];
        foreach ($rows as $row) {
            $out[] = self::serializeActiva($row);
        }

        return $out;
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, encounter_class: string, at: string}
     */
    public static function panelPayload(int $idEfector, string $encounterClass, ?string $atDateTime = null): array
    {
        $at = $atDateTime !== null && trim($atDateTime) !== ''
            ? date('Y-m-d H:i:s', strtotime($atDateTime) ?: time())
            : date('Y-m-d H:i:s');
        $items = self::listarActivas($idEfector, $encounterClass, $at);

        return [
            'title' => $encounterClass === Encounter::ENCOUNTER_CLASS_EMER
                ? 'Plantel de guardia'
                : 'Cobertura de piso',
            'encounter_class' => $encounterClass,
            'at' => $at,
            'items' => $items,
            'total' => count($items),
            'empty_message' => count($items) === 0
                ? 'Nadie con cobertura cargada en este momento.'
                : null,
        ];
    }

    /**
     * Solapes con cupos AMB de la misma persona en el efector (si metadata lo habilita).
     *
     * @return list<array<string, mixed>>
     */
    public static function detectAmbSlotConflicts(ProfesionalCobertura $model): array
    {
        $conflictsMeta = AgendaByEncounterClassMetadata::loadConfig()['conflicts'] ?? [];
        if (!(bool) ($conflictsMeta['cobertura_vs_amb_slots'] ?? false)) {
            return [];
        }

        $idPersona = (int) $model->id_persona;
        $idEfector = (int) $model->id_efector;
        $inicioTs = strtotime((string) $model->inicio);
        $finTs = strtotime((string) $model->fin);
        if ($idPersona <= 0 || $idEfector <= 0 || $inicioTs === false || $finTs === false) {
            return [];
        }

        $pesIds = ProfesionalEfectorServicio::find()
            ->select(['id'])
            ->where([
                'id_persona' => $idPersona,
                'id_efector' => $idEfector,
                'deleted_at' => null,
            ])
            ->column();
        if ($pesIds === []) {
            return [];
        }

        $out = [];
        $diaCursor = strtotime(date('Y-m-d', $inicioTs));
        $diaFin = strtotime(date('Y-m-d', $finTs));
        while ($diaCursor !== false && $diaFin !== false && $diaCursor <= $diaFin) {
            $diaYmd = date('Y-m-d', $diaCursor);
            foreach ($pesIds as $idPesRaw) {
                $idPes = (int) $idPesRaw;
                $version = ProfesionalEfectorServicioAgendaVersion::findVigenteParaPesEnFecha($idPes, $diaYmd);
                $agendaLike = $version;
                $intervalo = $version !== null ? $version->getIntervaloMinutosEfectivo() : null;
                if ($agendaLike === null) {
                    $agendaLike = ProfesionalEfectorServicioAgenda::findActivaPorProfesionalEfectorServicio($idPes);
                    $intervalo = $agendaLike !== null ? $agendaLike->resolveIntervaloMinutosParaSlots() : null;
                }
                if ($agendaLike === null || $intervalo === null) {
                    continue;
                }
                foreach (AgendaSlotEngine::slotsParaDia($agendaLike, $diaYmd, $intervalo) as $hhmm) {
                    $slotTs = strtotime($diaYmd . ' ' . substr($hhmm, 0, 5) . ':00');
                    if ($slotTs === false) {
                        continue;
                    }
                    if ($slotTs >= $inicioTs && $slotTs < $finTs) {
                        $out[] = [
                            'kind' => 'amb_slot',
                            'id_profesional_efector_servicio' => $idPes,
                            'fecha' => $diaYmd,
                            'hora' => substr($hhmm, 0, 5),
                            'message' => 'Solapa con cupo AMB ' . $diaYmd . ' ' . substr($hhmm, 0, 5),
                        ];
                        // Un hallazgo por PES/día basta para avisar
                        break;
                    }
                }
            }
            $diaCursor = strtotime('+1 day', $diaCursor);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function serializeActiva(ProfesionalCobertura $row): array
    {
        $base = ProfesionalCoberturaService::toApiArray($row);
        $persona = $row->persona;
        if ($persona instanceof Persona) {
            $base['persona'] = [
                'id' => (int) $persona->id_persona,
                'nombre_completo' => trim($persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N)),
            ];
        }
        $svc = $row->servicio;
        if ($svc instanceof Servicio) {
            $base['servicio'] = [
                'id' => (int) $svc->id_servicio,
                'nombre' => (string) $svc->nombre,
            ];
        }

        return $base;
    }
}
