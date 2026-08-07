<?php

namespace common\components\Domain\Clinical\Service;

use common\components\Domain\Clinical\Presentation\EpisodioDateTimeFormatter;
use common\components\Domain\Clinical\Emergency\Enum\CircuitoEstado;
use common\components\Domain\Clinical\Emergency\Service\GuardiaCircuitoService;
use common\components\Domain\Clinical\Emergency\Service\GuardiaTriageService;
use common\models\Clinical\Encounter;
use common\models\Emergency\GuardiaTriage;
use common\models\Guardia;
use common\models\SegNivelInternacion;

/**
 * Banner de episodio para HC / captura EMER e IMP (triaje, estado, motivo, ingreso).
 * No sustituye el estado longitudinal del paciente (alergias, crónicos).
 * Ubicación y «médico a cargo» no forman parte del banner (operación en tablero / cama).
 */
final class EpisodioHistoriaBannerService
{
    /** @var GuardiaCircuitoService */
    private $circuito;

    /** @var GuardiaTriageService */
    private $triageSerializer;

    public function __construct(
        ?GuardiaCircuitoService $circuito = null,
        ?GuardiaTriageService $triageSerializer = null
    ) {
        $this->circuito = $circuito ?? new GuardiaCircuitoService();
        $this->triageSerializer = $triageSerializer ?? new GuardiaTriageService();
    }

    /**
     * @return array{
     *   contexto_episodio: array<string, mixed>,
     *   contexto_internacion: null
     * }|null
     */
    public function buildForGuardia(int $personaId, int $guardiaId, int $idEfector): ?array
    {
        $guardia = Guardia::find()
            ->where(['id' => $guardiaId, 'id_efector' => $idEfector])
            ->one();
        if ($guardia === null || (int) $guardia->id_persona !== $personaId) {
            return null;
        }

        $estado = $this->circuito->effectiveEstado($guardia);
        $triageRow = GuardiaTriage::findOne(['guardia_id' => $guardiaId]);
        $triage = $triageRow !== null ? $this->triageSerializer->serializeTriage($triageRow) : null;

        $motivo = null;
        if (is_array($triage)) {
            $rt = trim((string) ($triage['reason_text'] ?? ''));
            if ($rt !== '') {
                $motivo = $rt;
            }
        }
        if ($motivo === null) {
            $sit = trim((string) ($guardia->situacion_al_ingresar ?? ''));
            $motivo = $sit !== '' ? $sit : null;
        }

        $ingresoAt = trim((string) ($guardia->ingreso_at ?? ''));
        if ($ingresoAt === '') {
            $ingresoAt = EpisodioDateTimeFormatter::displayFromParts(
                $guardia->fecha ?? null,
                $guardia->hora ?? null
            );
        } else {
            $ingresoAt = EpisodioDateTimeFormatter::display($ingresoAt);
        }

        $banner = $this->baseBanner(
            Encounter::PARENT_GUARDIA,
            $guardiaId,
            $estado,
            CircuitoEstado::label($estado),
            $motivo,
            $ingresoAt !== '' ? $ingresoAt : null
        );
        $banner['triage'] = $triage;
        if (is_array($banner['triage']) && isset($banner['triage']['triaged_at'])) {
            $banner['triage']['triaged_at'] = EpisodioDateTimeFormatter::display(
                (string) $banner['triage']['triaged_at']
            );
        }

        $cerrado = in_array($estado, [
            CircuitoEstado::FINALIZADO,
            CircuitoEstado::DERIVADO,
        ], true);
        $banner['acciones'] = $cerrado
            ? []
            : [
                [
                    'id' => 'editar_triage',
                    'label' => $triageRow !== null ? 'Editar triage' : 'Registrar triage',
                    'api_route' => '/clinical/emergency-guardia/registrar-triage-formulario'
                        . '?guardia_id=' . $guardiaId,
                ],
            ];

        return [
            'contexto_episodio' => $banner,
            'contexto_internacion' => null,
        ];
    }

    /**
     * @return array{
     *   contexto_episodio: array<string, mixed>,
     *   contexto_internacion: array<string, mixed>
     * }|null
     */
    public function buildForInternacion(int $personaId, int $internacionId): ?array
    {
        $internacion = SegNivelInternacion::find()
            ->where(['id' => $internacionId])
            ->one();
        if (
            !$internacion instanceof SegNivelInternacion
            || (int) $internacion->id_persona !== $personaId
        ) {
            return null;
        }

        $fechaInicio = '';
        if (!empty($internacion->fecha_inicio)) {
            $fechaInicio = EpisodioDateTimeFormatter::displayFromParts(
                (string) $internacion->fecha_inicio,
                !empty($internacion->hora_inicio) ? (string) $internacion->hora_inicio : null
            );
        }

        $motivo = trim((string) ($internacion->situacion_al_ingresar ?? ''));
        $motivo = $motivo !== '' ? $motivo : null;

        $evoluciones = $this->listEvolucionesInternacion($personaId, $internacionId);

        $banner = $this->baseBanner(
            Encounter::PARENT_INTERNACION,
            $internacionId,
            null,
            null,
            $motivo,
            $fechaInicio !== '' ? $fechaInicio : null
        );
        $banner['triage'] = null;
        $banner['acciones'] = [];

        $contextoInternacion = [
            'internacion_id' => $internacionId,
            'fecha_inicio' => $fechaInicio,
            'evoluciones' => $evoluciones,
            'motivo' => $motivo,
        ];

        return [
            'contexto_episodio' => $banner,
            'contexto_internacion' => $contextoInternacion,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function baseBanner(
        string $tipo,
        int $episodioId,
        ?string $estado,
        ?string $estadoLabel,
        ?string $motivo,
        ?string $ingresoAt
    ): array {
        return [
            'tipo' => $tipo,
            'episodio_id' => $episodioId,
            'estado' => $estado,
            'estado_label' => $estadoLabel,
            'motivo' => $motivo,
            'ingreso_at' => $ingresoAt,
        ];
    }

    /**
     * @return list<array{encounter_id: int, fecha: string, texto: string, status: string}>
     */
    private function listEvolucionesInternacion(int $personaId, int $internacionId): array
    {
        $parentType = Encounter::PARENT_CLASSES[Encounter::PARENT_INTERNACION];
        $rows = Encounter::find()
            ->andWhere([
                'parent_type' => $parentType,
                'parent_id' => $internacionId,
                'subject_persona_id' => $personaId,
            ])
            ->andWhere(['deleted_at' => null])
            ->orderBy(['period_start' => SORT_DESC, 'id' => SORT_DESC])
            ->limit(25)
            ->all();

        $evoluciones = [];
        foreach ($rows as $enc) {
            if (!$enc instanceof Encounter) {
                continue;
            }
            $texto = trim((string) ($enc->note ?? ''));
            if ($texto === '') {
                $texto = trim((string) ($enc->reason_text ?? ''));
            }
            if ($texto === '' || stripos($texto, 'Internación #') === 0) {
                continue;
            }
            $fecha = EpisodioDateTimeFormatter::display(
                (string) ($enc->period_start ?? $enc->created_at ?? '')
            );
            $evoluciones[] = [
                'encounter_id' => (int) $enc->id,
                'fecha' => $fecha,
                'texto' => $texto,
                'status' => (string) ($enc->status ?? ''),
            ];
        }

        return $evoluciones;
    }
}
