<?php

namespace common\components\Domain\Clinical\Emergency\Service;

use common\components\Domain\Clinical\Emergency\Enum\CircuitoEstado;
use common\components\Domain\Clinical\PatientHistoriaUrl;
use common\models\Clinical\DiagnosticReport;
use common\models\Clinical\ServiceRequest;
use common\models\Clinical\Encounter;
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
        $items = [];
        foreach ($rows as $guardia) {
            $items[] = $this->serializeBoardRow($guardia);
        }
        usort($items, static function (array $a, array $b): int {
            $pa = $a['prioridad_triage'];
            $pb = $b['prioridad_triage'];
            if ($pa === null && $pb !== null) {
                return 1;
            }
            if ($pa !== null && $pb === null) {
                return -1;
            }
            if ($pa !== null && $pb !== null && $pa !== $pb) {
                return $pa <=> $pb;
            }

            return strcmp((string) $a['ingreso_at'], (string) $b['ingreso_at']);
        });

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
            ->where(['id' => $guardiaId, 'id_efector' => $idEfector])
            ->with(['paciente.tipoDocumento', 'profesionalEfectorServicio.persona'])
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
            ->where(['g.id_efector' => $idEfector])
            ->with(['paciente.tipoDocumento', 'profesionalEfectorServicio.persona']);

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
     * @return array<string, mixed>
     */
    private function serializeBoardRow(Guardia $guardia): array
    {
        $paciente = $guardia->paciente;
        $circuito = $this->circuito->effectiveEstado($guardia);
        $ingresoAt = $guardia->ingreso_at
            ?: ($guardia->created_at ?: ($guardia->fecha . ' ' . ($guardia->hora ?? '00:00:00')));
        $minutos = max(0, (int) floor((time() - strtotime((string) $ingresoAt)) / 60));

        $triage = GuardiaTriage::findOne(['guardia_id' => (int) $guardia->id]);
        $triagePayload = null;
        $prioridad = $guardia->prioridad_triage !== null ? (int) $guardia->prioridad_triage : null;
        if ($triage !== null) {
            $triagePayload = $this->triageSerializer->serializeTriage($triage);
            $prioridad = (int) $triage->level;
        }

        $pesNombre = null;
        if ($guardia->profesionalEfectorServicio && $guardia->profesionalEfectorServicio->persona) {
            $pesNombre = $guardia->profesionalEfectorServicio->persona->getNombreCompleto(
                Persona::FORMATO_NOMBRE_A_OA_N_ON
            );
        }

        $sla = $this->sla->evaluate($guardia, $minutos, $circuito, $prioridad);
        $internacion = $this->internacion->serializePendiente($guardia);
        $clinical = $this->serializeClinicalCompact($guardia);

        return [
            'id' => (int) $guardia->id,
            'id_persona' => (int) $guardia->id_persona,
            'id_efector' => (int) $guardia->id_efector,
            'estado' => $guardia->estado,
            'circuito_estado' => $circuito,
            'circuito_estado_label' => CircuitoEstado::label($circuito),
            'prioridad_triage' => $prioridad,
            'fecha' => $guardia->fecha,
            'hora' => $guardia->hora,
            'ingreso_at' => $ingresoAt,
            'minutos_espera' => $minutos,
            'id_profesional_efector_servicio' => $guardia->id_profesional_efector_servicio,
            'profesional_asignado' => $pesNombre,
            'sla_violado' => $sla['sla_violado'],
            'sla_tipo' => $sla['sla_tipo'],
            'sla_umbral_minutos' => $sla['sla_umbral_minutos'],
            'internacion_pendiente' => $internacion['internacion_pendiente'],
            'internacion_ingreso_url' => $internacion['internacion_ingreso_url'],
            'clinical' => $clinical,
            'paciente' => [
                'id' => $paciente ? (int) $paciente->id_persona : null,
                'nombre_completo' => $paciente
                    ? $paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N)
                    : 'Sin nombre',
                'documento' => $paciente ? $paciente->documento : null,
                'tipo_documento' => $paciente && $paciente->tipoDocumento
                    ? $paciente->tipoDocumento->nombre
                    : null,
            ],
            'triage' => $triagePayload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeClinicalCompact(Guardia $guardia): array
    {
        $capturaUrl = PatientHistoriaUrl::captura(
            (int) $guardia->id_persona,
            Encounter::PARENT_GUARDIA,
            (int) $guardia->id
        );
        $encounter = $this->encounterResolver->findLatestForGuardia((int) $guardia->id);
        if ($encounter === null) {
            return [
                'encounter_id' => null,
                'captura_url' => $capturaUrl,
                'orders_count' => 0,
                'orders_lab_pending' => 0,
                'laboratory_reports_count' => 0,
            ];
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

        return [
            'encounter_id' => $encounterId,
            'captura_url' => $capturaUrl,
            'orders_count' => $ordersCount,
            'orders_lab_pending' => $labPending,
            'laboratory_reports_count' => $labReportsCount,
        ];
    }
}
