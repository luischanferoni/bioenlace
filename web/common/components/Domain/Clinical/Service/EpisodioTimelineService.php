<?php

namespace common\components\Domain\Clinical\Service;

use common\components\Domain\Clinical\Presentation\EpisodioDateTimeFormatter;
use common\components\Domain\Clinical\Emergency\Enum\CircuitoEventType;
use common\components\Domain\Clinical\Emergency\Service\GuardiaTriageService;
use common\models\Clinical\Encounter;
use common\models\Clinical\MedicationAdministration;
use common\models\Clinical\MedicationRequest;
use common\models\Clinical\ServiceRequest;
use common\models\ConsultaAtencionesEnfermeria;
use common\models\Emergency\GuardiaCircuitoEvent;
use common\models\Emergency\GuardiaTriage;
use common\models\Guardia;
use common\models\Person\Persona;
use common\models\ProfesionalEfectorServicio;
use common\models\SegNivelInternacion;
use common\components\Domain\Clinical\Laboratory\Service\LaboratoryResultQueryService;

/**
 * Feed cronológico unificado del episodio (guardia / internación) para HC.
 * Ensambla encounters, circuito, triage, pedidos, lab, medicación y enfermería.
 */
final class EpisodioTimelineService
{
    private const LIMIT_ITEMS = 120;

    /** @var GuardiaTriageService */
    private $triageSerializer;

    /** @var LaboratoryResultQueryService */
    private $labQuery;

    /** @var array<int, string> */
    private $pesNombreCache = [];

    public function __construct(
        ?GuardiaTriageService $triageSerializer = null,
        ?LaboratoryResultQueryService $labQuery = null
    ) {
        $this->triageSerializer = $triageSerializer ?? new GuardiaTriageService();
        $this->labQuery = $labQuery ?? new LaboratoryResultQueryService();
    }

    /**
     * @return array{parent_type: string, episodio_id: int, items: list<array<string, mixed>>}|null
     */
    public function buildForGuardia(int $personaId, int $guardiaId, int $idEfector): ?array
    {
        $guardia = Guardia::findOne(['id' => $guardiaId, 'id_efector' => $idEfector]);
        if ($guardia === null || (int) $guardia->id_persona !== $personaId) {
            return null;
        }

        $encounters = $this->listEncountersForParent(Encounter::PARENT_GUARDIA, $guardiaId, $personaId);
        $items = [];
        $this->appendCircuitoItems($items, $guardiaId);
        $this->appendTriageItem($items, $guardiaId);
        $this->appendEncounterBoundItems($items, $encounters);

        return $this->finalize(Encounter::PARENT_GUARDIA, $guardiaId, $items);
    }

    /**
     * @return array{parent_type: string, episodio_id: int, items: list<array<string, mixed>>}|null
     */
    public function buildForInternacion(int $personaId, int $internacionId): ?array
    {
        $internacion = SegNivelInternacion::findOne($internacionId);
        if (
            !$internacion instanceof SegNivelInternacion
            || (int) $internacion->id_persona !== $personaId
        ) {
            return null;
        }

        $encounters = $this->listEncountersForParent(Encounter::PARENT_INTERNACION, $internacionId, $personaId);
        $items = [];
        $this->appendEncounterBoundItems($items, $encounters);

        return $this->finalize(Encounter::PARENT_INTERNACION, $internacionId, $items);
    }

    /**
     * Encounters del episodio aceptando parent_type corto o FQCN.
     *
     * @return Encounter[]
     */
    public function listEncountersForParent(string $parentKey, int $parentId, ?int $personaId = null): array
    {
        $types = [$parentKey];
        $fqcn = Encounter::PARENT_CLASSES[$parentKey] ?? null;
        if (is_string($fqcn) && $fqcn !== '' && $fqcn !== $parentKey) {
            $types[] = $fqcn;
            $types[] = ltrim($fqcn, '\\');
        }

        $q = Encounter::find()
            ->andWhere(['parent_type' => $types, 'parent_id' => $parentId])
            ->andWhere(['deleted_at' => null])
            ->orderBy(['period_start' => SORT_DESC, 'id' => SORT_DESC])
            ->limit(80);

        if ($personaId !== null && $personaId > 0) {
            $q->andWhere(['subject_persona_id' => $personaId]);
        }

        /** @var Encounter[] $rows */
        $rows = $q->all();

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param Encounter[] $encounters
     */
    private function appendEncounterBoundItems(array &$items, array $encounters): void
    {
        if ($encounters === []) {
            return;
        }

        $encounterIds = [];
        foreach ($encounters as $enc) {
            $encounterIds[] = (int) $enc->id;
            $this->appendEvolucionMedica($items, $enc);
        }

        $this->appendEnfermeriaItems($items, $encounterIds);
        $this->appendServiceRequestItems($items, $encounterIds);
        $this->appendLabResultItems($items, $encounterIds);
        $this->appendMedicationItems($items, $encounterIds);
        $this->appendAdministracionItems($items, $encounterIds);
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function appendCircuitoItems(array &$items, int $guardiaId): void
    {
        $rows = GuardiaCircuitoEvent::find()
            ->where(['guardia_id' => $guardiaId])
            ->orderBy(['occurred_at' => SORT_DESC, 'id' => SORT_DESC])
            ->limit(60)
            ->all();

        foreach ($rows as $row) {
            if (!$row instanceof GuardiaCircuitoEvent) {
                continue;
            }
            $tipo = (string) $row->tipo;
            // El triage clínico se muestra como ítem `triage` (más rico); el evento queda implícito.
            if ($tipo === CircuitoEventType::TRIAGE || $tipo === CircuitoEventType::RE_TRIAGE) {
                continue;
            }
            $payload = [];
            if (!empty($row->payload_json)) {
                $decoded = json_decode((string) $row->payload_json, true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }
            $items[] = $this->item(
                'circuito',
                'circuito:' . (int) $row->id,
                (string) $row->occurred_at,
                CircuitoEventType::label($tipo),
                [
                    'event_type' => $tipo,
                    'payload' => $payload,
                ],
                null,
                $row->id_profesional_efector_servicio !== null
                    ? (int) $row->id_profesional_efector_servicio
                    : null
            );
        }
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function appendTriageItem(array &$items, int $guardiaId): void
    {
        $triage = GuardiaTriage::findOne(['guardia_id' => $guardiaId]);
        if ($triage === null) {
            return;
        }
        $ser = $this->triageSerializer->serializeTriage($triage);
        $levelLabel = (string) ($ser['level_label'] ?? ('Nivel ' . (int) ($ser['level'] ?? 0)));
        $reason = trim((string) ($ser['reason_text'] ?? ''));
        $summary = 'Triage ' . $levelLabel;
        if ($reason !== '') {
            $summary .= ': ' . $reason;
        }
        $occurred = (string) ($ser['triaged_at'] ?? $triage->triaged_at ?? '');
        $items[] = $this->item(
            'triage',
            'triage:' . (int) $triage->id,
            $occurred !== '' ? $occurred : date('Y-m-d H:i:s'),
            $summary,
            $ser,
            null,
            $triage->id_profesional_efector_servicio !== null
                ? (int) $triage->id_profesional_efector_servicio
                : null
        );
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function appendEvolucionMedica(array &$items, Encounter $enc): void
    {
        $texto = trim((string) ($enc->note ?? ''));
        if ($texto === '') {
            $texto = trim((string) ($enc->reason_text ?? ''));
        }
        if ($texto === '') {
            return;
        }
        if (stripos($texto, 'Internación #') === 0 || stripos($texto, 'Guardia #') === 0) {
            return;
        }
        $fecha = (string) ($enc->period_start ?? $enc->created_at ?? '');
        $preview = $this->preview($texto, 220);
        $items[] = $this->item(
            'evolucion_medica',
            'encounter:' . (int) $enc->id,
            $fecha !== '' ? $fecha : date('Y-m-d H:i:s'),
            $preview,
            [
                'status' => (string) ($enc->status ?? ''),
                'texto' => $texto,
                'encounter_class' => (string) ($enc->encounter_class ?? ''),
            ],
            (int) $enc->id,
            $enc->id_profesional_efector_servicio !== null
                ? (int) $enc->id_profesional_efector_servicio
                : null
        );
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<int> $encounterIds
     */
    private function appendEnfermeriaItems(array &$items, array $encounterIds): void
    {
        $rows = ConsultaAtencionesEnfermeria::find()
            ->where(['encounter_id' => $encounterIds])
            ->orderBy(['id' => SORT_DESC])
            ->limit(40)
            ->all();

        foreach ($rows as $row) {
            if (!$row instanceof ConsultaAtencionesEnfermeria) {
                continue;
            }
            $datos = $row->datos;
            $preview = 'Atención de enfermería';
            if (is_string($datos) && $datos !== '') {
                $decoded = json_decode($datos, true);
                if (is_array($decoded)) {
                    $preview = $this->previewEnfermeria($decoded);
                } else {
                    $preview = $this->preview($datos, 160);
                }
            } elseif (is_array($datos)) {
                $preview = $this->previewEnfermeria($datos);
            }
            $fecha = '';
            if (!empty($row->fecha_creacion)) {
                $fecha = (string) $row->fecha_creacion;
                if (!empty($row->hora_creacion)) {
                    $hora = substr((string) $row->hora_creacion, 0, 8);
                    if (strlen($fecha) <= 10 && $hora !== '') {
                        $fecha = trim($fecha) . ' ' . $hora;
                    }
                }
            }
            $items[] = $this->item(
                'atencion_enfermeria',
                'enfermeria:' . (int) $row->id,
                $fecha !== '' ? $fecha : date('Y-m-d H:i:s'),
                $preview,
                ['id' => (int) $row->id],
                $row->encounter_id !== null ? (int) $row->encounter_id : null,
                $row->id_profesional_efector_servicio !== null
                    ? (int) $row->id_profesional_efector_servicio
                    : null
            );
        }
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<int> $encounterIds
     */
    private function appendServiceRequestItems(array &$items, array $encounterIds): void
    {
        $rows = ServiceRequest::find()
            ->where(['encounter_id' => $encounterIds])
            ->andWhere(['deleted_at' => null])
            ->orderBy(['id' => SORT_DESC])
            ->limit(60)
            ->all();

        foreach ($rows as $sr) {
            if (!$sr instanceof ServiceRequest) {
                continue;
            }
            $category = strtolower(trim((string) ($sr->category ?? '')));
            $isReferral = $category === 'referral'
                || strtolower((string) ($sr->referral_kind ?? '')) !== '';
            $type = $isReferral ? 'interconsulta' : 'pedido';
            $display = trim((string) ($sr->display ?? ''));
            if ($display === '') {
                $display = $isReferral ? 'Interconsulta' : 'Pedido';
            }
            $status = (string) ($sr->status ?? '');
            $summary = $display . ($status !== '' ? ' · ' . $status : '');
            $occurred = (string) ($sr->occurrence_datetime ?? $sr->created_at ?? '');
            $items[] = $this->item(
                $type,
                'sr:' . (int) $sr->id,
                $occurred !== '' ? $occurred : date('Y-m-d H:i:s'),
                $summary,
                [
                    'category' => (string) ($sr->category ?? ''),
                    'status' => $status,
                    'code' => $sr->code,
                    'display' => $display,
                    'referral_status' => $sr->referral_status,
                ],
                (int) $sr->encounter_id,
                $sr->id_profesional_efector_servicio !== null
                    ? (int) $sr->id_profesional_efector_servicio
                    : null
            );
        }
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<int> $encounterIds
     */
    private function appendLabResultItems(array &$items, array $encounterIds): void
    {
        foreach ($encounterIds as $eid) {
            foreach ($this->labQuery->listForEncounter((int) $eid) as $report) {
                if (!is_array($report)) {
                    continue;
                }
                $id = (int) ($report['id'] ?? 0);
                $display = trim((string) ($report['display'] ?? 'Informe de laboratorio'));
                $issued = (string) ($report['issuedAt'] ?? $report['issued_at'] ?? '');
                $items[] = $this->item(
                    'resultado_lab',
                    'lab:' . $id,
                    $issued !== '' ? $issued : date('Y-m-d H:i:s'),
                    $display,
                    [
                        'observations_count' => is_array($report['observations'] ?? null)
                            ? count($report['observations'])
                            : 0,
                    ],
                    $eid,
                    null
                );
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<int> $encounterIds
     */
    private function appendMedicationItems(array &$items, array $encounterIds): void
    {
        $rows = MedicationRequest::find()
            ->where(['encounter_id' => $encounterIds])
            ->andWhere(['deleted_at' => null])
            ->orderBy(['id' => SORT_DESC])
            ->limit(60)
            ->all();

        foreach ($rows as $mr) {
            if (!$mr instanceof MedicationRequest) {
                continue;
            }
            $display = trim((string) ($mr->medication_display ?? ''));
            if ($display === '') {
                $display = 'Medicación';
            }
            $dosage = trim((string) ($mr->dosage_text ?? ''));
            $summary = $display;
            if ($dosage !== '') {
                $summary .= ' · ' . $dosage;
            }
            $status = (string) ($mr->status ?? '');
            if ($status !== '') {
                $summary .= ' · ' . $status;
            }
            $occurred = (string) ($mr->authored_on ?? $mr->created_at ?? '');
            $items[] = $this->item(
                'medicacion',
                'med:' . (int) $mr->id,
                $occurred !== '' ? $occurred : date('Y-m-d H:i:s'),
                $summary,
                [
                    'status' => $status,
                    'medication_display' => $display,
                    'dosage_text' => $dosage !== '' ? $dosage : null,
                ],
                (int) $mr->encounter_id,
                $mr->id_profesional_efector_servicio !== null
                    ? (int) $mr->id_profesional_efector_servicio
                    : null
            );
        }
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<int> $encounterIds
     */
    private function appendAdministracionItems(array &$items, array $encounterIds): void
    {
        $rows = MedicationAdministration::find()
            ->where(['encounter_id' => $encounterIds])
            ->andWhere(['deleted_at' => null])
            ->orderBy(['effective_datetime' => SORT_DESC, 'id' => SORT_DESC])
            ->limit(40)
            ->all();

        foreach ($rows as $row) {
            if (!$row instanceof MedicationAdministration) {
                continue;
            }
            $status = (string) ($row->status ?? '');
            $summary = 'Administración de fármaco';
            if ($status !== '') {
                $summary .= ' · ' . $status;
            }
            $occurred = (string) ($row->effective_datetime ?? $row->created_at ?? '');
            $items[] = $this->item(
                'administracion',
                'admin:' . (int) $row->id,
                $occurred !== '' ? $occurred : date('Y-m-d H:i:s'),
                $summary,
                [
                    'status' => $status,
                    'medication_request_id' => $row->medication_request_id !== null
                        ? (int) $row->medication_request_id
                        : null,
                ],
                (int) $row->encounter_id,
                null
            );
        }
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return array{parent_type: string, episodio_id: int, items: list<array<string, mixed>>}
     */
    private function finalize(string $parentType, int $episodioId, array $items): array
    {
        usort($items, static function (array $a, array $b): int {
            $ta = (string) ($a['occurred_at'] ?? '');
            $tb = (string) ($b['occurred_at'] ?? '');
            if ($ta === $tb) {
                return strcmp((string) ($b['id'] ?? ''), (string) ($a['id'] ?? ''));
            }

            return strcmp($tb, $ta);
        });

        if (count($items) > self::LIMIT_ITEMS) {
            $items = array_slice($items, 0, self::LIMIT_ITEMS);
        }

        foreach ($items as $idx => $it) {
            if (!is_array($it)) {
                continue;
            }
            $it['occurred_at'] = EpisodioDateTimeFormatter::display((string) ($it['occurred_at'] ?? ''));
            if (isset($it['payload']) && is_array($it['payload'])) {
                if (isset($it['payload']['triaged_at'])) {
                    $it['payload']['triaged_at'] = EpisodioDateTimeFormatter::display(
                        (string) $it['payload']['triaged_at']
                    );
                }
            }
            $items[$idx] = $it;
        }

        return [
            'parent_type' => $parentType,
            'episodio_id' => $episodioId,
            'items' => array_values($items),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function item(
        string $type,
        string $id,
        string $occurredAt,
        string $summary,
        array $payload,
        ?int $encounterId,
        ?int $pesId
    ): array {
        $row = [
            'type' => $type,
            'id' => $id,
            'occurred_at' => $occurredAt,
            'summary' => $summary,
            'payload' => $payload,
            'encounter_id' => $encounterId,
            'actor' => $this->actorFromPes($pesId),
        ];

        return $row;
    }

    /**
     * @return array{pes_id: int, nombre: string}|null
     */
    private function actorFromPes(?int $pesId): ?array
    {
        if ($pesId === null || $pesId <= 0) {
            return null;
        }
        if (!isset($this->pesNombreCache[$pesId])) {
            $nombre = '';
            $pes = ProfesionalEfectorServicio::find()
                ->where(['id' => $pesId])
                ->with(['persona'])
                ->one();
            if ($pes instanceof ProfesionalEfectorServicio && $pes->persona instanceof Persona) {
                $nombre = trim((string) $pes->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON));
            }
            $this->pesNombreCache[$pesId] = $nombre !== '' ? $nombre : ('PES #' . $pesId);
        }

        return [
            'pes_id' => $pesId,
            'nombre' => $this->pesNombreCache[$pesId],
        ];
    }

    private function preview(string $texto, int $max): string
    {
        $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? $texto);
        if (mb_strlen($texto) <= $max) {
            return $texto;
        }

        return rtrim(mb_substr($texto, 0, $max - 1)) . '…';
    }

    /**
     * @param array<string, mixed> $datos
     */
    private function previewEnfermeria(array $datos): string
    {
        $keys = ['nota', 'observaciones', 'comentario', 'evolucion', 'texto'];
        foreach ($keys as $k) {
            if (!empty($datos[$k]) && is_string($datos[$k])) {
                return $this->preview('Enfermería: ' . $datos[$k], 180);
            }
        }
        $flat = [];
        foreach ($datos as $k => $v) {
            if (is_scalar($v) && (string) $v !== '') {
                $flat[] = $k . '=' . $v;
            }
            if (count($flat) >= 3) {
                break;
            }
        }
        if ($flat !== []) {
            return $this->preview('Enfermería: ' . implode(', ', $flat), 180);
        }

        return 'Atención de enfermería';
    }
}
