<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Domain\Clinical\Enum\EncounterStatus;
use common\models\Clinical\Encounter;
use common\models\ConsultaChatMessage;
use common\models\ServiciosEfector;

/**
 * KPIs agregados de consultas async (ventana configurable, scope por servicios).
 */
final class ConsultaAsyncIndicadoresService
{
    /** @var list<string> */
    private const STATUS_ABIERTOS = [
        EncounterStatus::PLANNED,
        EncounterStatus::IN_PROGRESS,
        EncounterStatus::ON_HOLD,
    ];

    /**
     * @param list<int> $serviceIds
     * @return array<string, mixed>
     */
    public function resumen(array $serviceIds, ?int $idEfector = null): array
    {
        $catalog = new ConsultaAsyncIndicadoresCatalogService();
        $bandejaCatalog = new ConsultaAsyncBandejaCatalogService();
        $dias = $catalog->ventanaDias();
        $desde = date('Y-m-d H:i:s', strtotime('-' . $dias . ' days'));

        if ($serviceIds === []) {
            return $this->emptyResumen($catalog, $dias);
        }

        $baseQuery = Encounter::find()
            ->where([
                'parent_type' => Encounter::PARENT_SOLICITUD_ASYNC,
                'encounter_class' => Encounter::ENCOUNTER_CLASS_VR,
                'deleted_at' => null,
            ])
            ->andWhere(['service_id' => $serviceIds]);
        if ($idEfector !== null && $idEfector > 0) {
            $baseQuery->andWhere(['efector_id' => $idEfector]);
        }

        $pendientes = (clone $baseQuery)
            ->andWhere(['status' => self::STATUS_ABIERTOS])
            ->count();

        $slaIncumplidos = 0;
        $abiertos = (clone $baseQuery)
            ->andWhere(['status' => self::STATUS_ABIERTOS])
            ->all();
        foreach ($abiertos as $encounter) {
            $meta = $this->parseNote($encounter->note);
            $urgencyBand = isset($meta['urgency_band']) ? (string) $meta['urgency_band'] : null;
            $sla = $this->buildSla($encounter, $urgencyBand, $bandejaCatalog);
            if (!empty($sla['incumplido'])) {
                $slaIncumplidos++;
            }
        }

        $cerradas = (int) (clone $baseQuery)
            ->andWhere(['status' => EncounterStatus::FINISHED])
            ->andWhere(['>=', 'period_end', $desde])
            ->count();

        $canceladas = (int) (clone $baseQuery)
            ->andWhere(['status' => EncounterStatus::CANCELLED])
            ->andWhere(['>=', 'period_end', $desde])
            ->count();

        $tasaResolucion = $this->formatTasaResolucion($cerradas, $canceladas);
        $slaStats = $this->slaStatsPeriodo($baseQuery, $desde, $bandejaCatalog);

        return [
            'ventana_dias' => $dias,
            'pendientes' => (int) $pendientes,
            'sla_incumplidos' => $slaIncumplidos,
            'cerradas_periodo' => $cerradas,
            'canceladas_periodo' => $canceladas,
            'tasa_resolucion' => $tasaResolucion,
            'cumplimiento_sla_pct' => $slaStats['cumplimiento_pct'],
            'mediana_respuesta_min' => $slaStats['mediana_min'],
        ];
    }

    /**
     * @return list<int>
     */
    public function idServiciosEnEfector(int $idEfector): array
    {
        if ($idEfector <= 0) {
            return [];
        }
        $ids = ServiciosEfector::find()
            ->select('id_servicio')
            ->where(['id_efector' => $idEfector, 'deleted_at' => null])
            ->column();
        $out = [];
        foreach ($ids as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $out[] = $n;
            }
        }

        return $out;
    }

    /**
     * @param ConsultaAsyncIndicadoresCatalogService $catalog
     * @return array<string, mixed>
     */
    private function emptyResumen(ConsultaAsyncIndicadoresCatalogService $catalog, int $dias): array
    {
        return [
            'ventana_dias' => $dias,
            'pendientes' => 0,
            'sla_incumplidos' => 0,
            'cerradas_periodo' => 0,
            'canceladas_periodo' => 0,
            'tasa_resolucion' => '—',
            'cumplimiento_sla_pct' => '—',
            'mediana_respuesta_min' => null,
        ];
    }

    private function formatTasaResolucion(int $cerradas, int $canceladas): string
    {
        $total = $cerradas + $canceladas;
        if ($total <= 0) {
            return '—';
        }

        return (string) (int) round(($cerradas / $total) * 100) . '%';
    }

    /**
     * @param \yii\db\ActiveQuery $baseQuery
     * @return array{cumplimiento_pct: string, mediana_min: int|null}
     */
    private function slaStatsPeriodo($baseQuery, string $desde, ConsultaAsyncBandejaCatalogService $bandejaCatalog): array
    {
        $encounters = (clone $baseQuery)
            ->andWhere(['status' => EncounterStatus::FINISHED])
            ->andWhere(['>=', 'period_end', $desde])
            ->all();

        $dentroSla = 0;
        $conRespuesta = 0;
        $minutosRespuesta = [];

        foreach ($encounters as $encounter) {
            $firstStaffAt = $this->primeraRespuestaStaffAt((int) $encounter->id);
            if ($firstStaffAt === null) {
                continue;
            }
            $conRespuesta++;
            $createdTs = strtotime((string) $encounter->created_at) ?: 0;
            $minutos = (int) round(max(0, $firstStaffAt - $createdTs) / 60);
            $minutosRespuesta[] = $minutos;

            $meta = $this->parseNote($encounter->note);
            $urgencyBand = isset($meta['urgency_band']) ? (string) $meta['urgency_band'] : null;
            $horasObj = $bandejaCatalog->horasSlaRespuesta($urgencyBand);
            if ($minutos <= ($horasObj * 60)) {
                $dentroSla++;
            }
        }

        $cumplimiento = $conRespuesta > 0
            ? (string) (int) round(($dentroSla / $conRespuesta) * 100) . '%'
            : '—';

        return [
            'cumplimiento_pct' => $cumplimiento,
            'mediana_min' => $this->mediana($minutosRespuesta),
        ];
    }

    private function primeraRespuestaStaffAt(int $encounterId): ?int
    {
        $row = ConsultaChatMessage::find()
            ->select('created_at')
            ->where(['encounter_id' => $encounterId])
            ->andWhere(['user_role' => ['medico', 'enfermeria']])
            ->orderBy(['created_at' => SORT_ASC])
            ->limit(1)
            ->one();
        if ($row === null || $row->created_at === null) {
            return null;
        }
        $ts = strtotime((string) $row->created_at);

        return $ts !== false ? $ts : null;
    }

    /**
     * @param list<int> $values
     */
    private function mediana(array $values): ?int
    {
        if ($values === []) {
            return null;
        }
        sort($values);
        $n = count($values);
        $mid = (int) floor($n / 2);
        if ($n % 2 === 1) {
            return $values[$mid];
        }

        return (int) round(($values[$mid - 1] + $values[$mid]) / 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSla(Encounter $encounter, ?string $urgencyBand, ConsultaAsyncBandejaCatalogService $catalog): array
    {
        $horas = $catalog->horasSlaRespuesta($urgencyBand);
        $createdTs = strtotime((string) $encounter->created_at) ?: time();
        $venceTs = $createdTs + ($horas * 3600);
        $respondido = $this->tieneRespuestaStaff((int) $encounter->id)
            || $encounter->status !== EncounterStatus::PLANNED;
        $incumplido = !$respondido && time() > $venceTs;

        return [
            'horas_objetivo' => $horas,
            'incumplido' => $incumplido,
            'respondido' => $respondido,
        ];
    }

    private function tieneRespuestaStaff(int $encounterId): bool
    {
        return ConsultaChatMessage::find()
            ->where(['encounter_id' => $encounterId])
            ->andWhere(['user_role' => ['medico', 'enfermeria']])
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function parseNote(?string $note): array
    {
        if ($note === null || trim($note) === '') {
            return [];
        }
        $decoded = json_decode($note, true);

        return is_array($decoded) ? $decoded : [];
    }
}
