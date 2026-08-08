<?php

namespace common\components\Domain\Clinical\Emergency\Service;

use common\components\Domain\Clinical\Emergency\Enum\CircuitoEstado;
use common\models\Clinical\DiagnosticReport;
use common\models\Clinical\ServiceRequest;
use common\models\Efector;
use common\models\Emergency\GuardiaTriage;
use common\models\Guardia;
use common\models\Person\Persona;
use yii\db\ActiveQuery;

final class GuardiaQueueService
{
    /** @var GuardiaCircuitoService */
    private $circuito;

    /** @var GuardiaTriageService */
    private $triageSerializer;

    /** @var GuardiaSlaService */
    private $sla;

    /** @var GuardiaInternacionService */
    private $internacion;

    /** @var GuardiaEncounterResolver */
    private $encounterResolver;

    public function __construct(
        ?GuardiaCircuitoService $circuito = null,
        ?GuardiaTriageService $triageSerializer = null,
        ?GuardiaSlaService $sla = null,
        ?GuardiaInternacionService $internacion = null,
        ?GuardiaEncounterResolver $encounterResolver = null
    ) {
        $this->circuito = $circuito ?? new GuardiaCircuitoService();
        $this->triageSerializer = $triageSerializer ?? new GuardiaTriageService();
        $this->sla = $sla ?? new GuardiaSlaService();
        $this->internacion = $internacion ?? new GuardiaInternacionService();
        $this->encounterResolver = $encounterResolver ?? new GuardiaEncounterResolver();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function tablero(int $idEfector, array $filters = []): array
    {
        $query = $this->baseActiveQuery($idEfector, $filters);
        $rows = $query->all();
        usort($rows, function (Guardia $a, Guardia $b): int {
            $pa = $this->prioridadTriageForSort($a);
            $pb = $this->prioridadTriageForSort($b);
            if ($pa === null && $pb !== null) {
                return 1;
            }
            if ($pa !== null && $pb === null) {
                return -1;
            }
            if ($pa !== null && $pb !== null && $pa !== $pb) {
                return $pa <=> $pb;
            }

            return strcmp($this->ingresoAt($a), $this->ingresoAt($b));
        });

        $items = [];
        foreach ($rows as $guardia) {
            $items[] = $this->serializeBoardRow($guardia);
        }

        return ['items' => $items, 'total' => count($items)];
    }

    /**
     * @return array<int, array{id_efector: int, nombre: string}>
     */
    public function listarEfectoresDerivacion(): array
    {
        $rows = Efector::getTodosLosEfectores();
        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id_efector'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[] = [
                'id_efector' => $id,
                'nombre' => (string) ($row['nombre'] ?? ''),
            ];
        }

        return $out;
    }

    public function detalle(int $guardiaId, int $idEfector): ?array
    {
        $guardia = Guardia::find()
            ->where(['id' => $guardiaId, 'id_efector' => $idEfector, 'deleted_at' => null])
            ->with(['paciente', 'profesionalEfectorServicio.persona'])
            ->one();
        if ($guardia === null) {
            return null;
        }

        $row = $this->serializeBoardRow($guardia);
        $triage = GuardiaTriage::findOne(['guardia_id' => $guardiaId]);
        if ($triage !== null) {
            $row['triage'] = $this->triageSerializer->serializeTriage($triage);
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function baseActiveQuery(int $idEfector, array $filters): ActiveQuery
    {
        $query = Guardia::find()
            ->alias('g')
            ->where(['g.id_efector' => $idEfector, 'g.deleted_at' => null])
            ->with(['paciente', 'profesionalEfectorServicio.persona']);

        $soloActivos = !isset($filters['incluir_finalizados']) || !$filters['incluir_finalizados'];
        if ($soloActivos || ($filters['solo_activos'] ?? false)) {
            $query->andWhere(['<>', 'g.estado', Guardia::ESTADO_FINALIZADA]);
            $query->andWhere([
                'or',
                ['g.circuito_estado' => null],
                ['<>', 'g.circuito_estado', CircuitoEstado::FINALIZADO],
            ]);
        }

        $circuito = isset($filters['circuito_estado']) ? (string) $filters['circuito_estado'] : '';
        if ($circuito !== '') {
            $query->andWhere(['g.circuito_estado' => $circuito]);
        }

        if (!empty($filters['sin_triage'])) {
            $query->leftJoin(['gt' => GuardiaTriage::tableName()], 'gt.guardia_id = g.id')
                ->andWhere(['gt.id' => null]);
        }

        return $query;
    }

    /**
     * Fila compacta del tablero / panel EMER (sin ruido ni nulls).
     *
     * @return array<string, mixed>
     */
    private function serializeBoardRow(Guardia $guardia): array
    {
        $paciente = $guardia->paciente;
        $circuito = $this->circuito->effectiveEstado($guardia);
        $ingresoAt = $this->ingresoAt($guardia);
        $minutos = max(0, (int) floor((time() - strtotime($ingresoAt)) / 60));

        $triage = GuardiaTriage::findOne(['guardia_id' => (int) $guardia->id]);
        $prioridad = $guardia->prioridad_triage !== null ? (int) $guardia->prioridad_triage : null;
        $reasonText = null;
        if ($triage !== null) {
            $prioridad = (int) $triage->level;
            $reason = trim((string) ($triage->reason_text ?? ''));
            $reasonText = $reason !== '' ? $reason : null;
        }

        $pesNombre = null;
        if ($guardia->profesionalEfectorServicio && $guardia->profesionalEfectorServicio->persona) {
            $pesNombre = $guardia->profesionalEfectorServicio->persona->getNombreCompleto(
                Persona::FORMATO_NOMBRE_A_OA_N_ON
            );
        }

        $sla = $this->sla->evaluate($guardia, $minutos, $circuito, $prioridad);
        $internacion = $this->internacion->serializePendiente($guardia);
        $clinical = $this->serializeClinicalBoardCounts($guardia);

        $row = [
            'id' => (int) $guardia->id,
            'id_persona' => (int) $guardia->id_persona,
            'nombre_completo' => $paciente
                ? $paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N)
                : 'Sin nombre',
            'circuito_estado' => $circuito,
            'circuito_estado_label' => CircuitoEstado::label($circuito),
            'minutos_espera' => $minutos,
        ];

        if ($prioridad !== null) {
            $row['prioridad_triage'] = $prioridad;
        }
        if ($pesNombre !== null && $pesNombre !== '') {
            $row['profesional_asignado'] = $pesNombre;
        }
        if (!empty($sla['triage_espera_nivel'])) {
            $row['triage_espera_nivel'] = $sla['triage_espera_nivel'];
        }
        if (!empty($sla['sla_violado']) && ($sla['sla_tipo'] ?? null) === 'medico') {
            $row['sla_violado'] = true;
            if ($sla['sla_umbral_minutos'] !== null) {
                $row['sla_umbral_minutos'] = (int) $sla['sla_umbral_minutos'];
            }
        }
        if (!empty($internacion['internacion_pendiente'])) {
            $row['internacion_pendiente'] = true;
        }
        if ($clinical !== null) {
            $row['clinical'] = $clinical;
        }
        if ($reasonText !== null) {
            $row['triage'] = ['reason_text' => $reasonText];
        }

        return $row;
    }

    private function ingresoAt(Guardia $guardia): string
    {
        return (string) ($guardia->ingreso_at
            ?: ($guardia->created_at ?: ($guardia->fecha . ' ' . ($guardia->hora ?? '00:00:00'))));
    }

    private function prioridadTriageForSort(Guardia $guardia): ?int
    {
        return $guardia->prioridad_triage !== null ? (int) $guardia->prioridad_triage : null;
    }

    /**
     * Contadores clínicos del listado; null si no hay nada que mostrar.
     *
     * @return array{orders_count: int, orders_lab_pending: int, laboratory_reports_count: int}|null
     */
    private function serializeClinicalBoardCounts(Guardia $guardia): ?array
    {
        $encounter = $this->encounterResolver->findLatestForGuardia((int) $guardia->id);
        if ($encounter === null) {
            return null;
        }

        $encounterId = (int) $encounter->id;
        $ordersCount = (int) ServiceRequest::find()
            ->where(['encounter_id' => $encounterId, 'deleted_at' => null])
            ->count();
        $hasLab = DiagnosticReport::find()
            ->where(['encounter_id' => $encounterId, 'deleted_at' => null])
            ->exists();
        $labOrders = (int) ServiceRequest::find()
            ->where([
                'encounter_id' => $encounterId,
                'deleted_at' => null,
            ])
            ->andWhere(['category' => ['procedure', 'laboratory', 'lab']])
            ->count();
        $labPending = $hasLab ? 0 : $labOrders;
        $labReportsCount = (int) DiagnosticReport::find()
            ->where(['encounter_id' => $encounterId, 'deleted_at' => null])
            ->count();

        if ($ordersCount === 0 && $labPending === 0 && $labReportsCount === 0) {
            return null;
        }

        // Solo claves con valor > 0 (clientes tratan ausente = 0).
        $out = [];
        if ($ordersCount > 0) {
            $out['orders_count'] = $ordersCount;
        }
        if ($labPending > 0) {
            $out['orders_lab_pending'] = $labPending;
        }
        if ($labReportsCount > 0) {
            $out['laboratory_reports_count'] = $labReportsCount;
        }

        return $out !== [] ? $out : null;
    }
}
