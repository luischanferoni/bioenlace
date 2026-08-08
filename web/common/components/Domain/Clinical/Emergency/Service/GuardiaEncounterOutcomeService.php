<?php

namespace common\components\Domain\Clinical\Emergency\Service;

use common\components\Domain\Clinical\Emergency\Enum\CircuitoEstado;
use common\models\Clinical\Encounter;
use common\models\Clinical\ServiceRequest;
use common\models\Guardia;
use Yii;

/**
 * Deducciones de conducta de guardia a partir de la captura del encounter (EMER).
 *
 * - Internación / UCI → pedido de cama (staff completa en tablero); no cierra a atendido.
 * - Derivación institucional → circuito derivado.
 * - Resto (alta, control, documentación clínica) → circuito atendido.
 */
final class GuardiaEncounterOutcomeService
{
    /** @var GuardiaInternacionService */
    private $internacion;

    /** @var GuardiaCircuitoService */
    private $circuito;

    public function __construct(
        ?GuardiaInternacionService $internacion = null,
        ?GuardiaCircuitoService $circuito = null
    ) {
        $this->internacion = $internacion ?? new GuardiaInternacionService();
        $this->circuito = $circuito ?? new GuardiaCircuitoService();
    }

    /**
     * @param array<string, mixed> $datosExtraidos categorías → filas de extracción
     * @return array<string, mixed>
     */
    public function applyAfterDocumentation(Encounter $encounter, array $datosExtraidos = []): array
    {
        $out = [
            'applied' => false,
            'internacion_solicitada' => false,
            'circuito_estado' => null,
        ];

        if (!$this->isGuardiaEncounter($encounter)) {
            return $out;
        }

        $guardiaId = (int) ($encounter->parent_id ?? 0);
        if ($guardiaId <= 0) {
            return $out;
        }

        $guardia = Guardia::findOne($guardiaId);
        if ($guardia === null) {
            return $out;
        }

        $estadoActual = $this->circuito->effectiveEstado($guardia);
        if (in_array($estadoActual, [CircuitoEstado::FINALIZADO, CircuitoEstado::DERIVADO], true)) {
            $out['applied'] = true;
            $out['circuito_estado'] = $estadoActual;

            return $out;
        }

        $pesId = (int) ($encounter->id_profesional_efector_servicio ?? $guardia->id_profesional_efector_servicio ?? 0);
        $pesId = $pesId > 0 ? $pesId : null;
        $payloadBase = [
            'source' => 'encounter_documentation',
            'encounter_id' => (int) $encounter->id,
        ];

        // Internación ya pedida o resuelta: no pasar a atendido (staff / cama).
        if ($this->internacion->isPendienteInternacion($guardia)
            || $this->internacion->internacionResuelta($guardiaId)
        ) {
            $out['applied'] = true;
            $out['circuito_estado'] = $estadoActual;

            return $out;
        }

        if ($this->signalsInternacion($encounter, $datosExtraidos)) {
            $idEfector = (int) ($guardia->id_efector ?? $encounter->id_efector ?? 0);
            if ($idEfector > 0) {
                try {
                    $this->internacion->solicitarInternacion($guardiaId, $idEfector, $idEfector);
                    $out['applied'] = true;
                    $out['internacion_solicitada'] = true;
                    $out['circuito_estado'] = $this->circuito->effectiveEstado($guardia);

                    return $out;
                } catch (\Throwable $e) {
                    Yii::warning(
                        'GuardiaEncounterOutcome: no se pudo marcar internación: ' . $e->getMessage(),
                        'emergency-guardia'
                    );
                }
            }
        }

        if ($this->signalsDerivacionInstitucional($encounter, $datosExtraidos)) {
            $this->circuito->afterDocumentacionDerivacion($guardia, $pesId, $payloadBase);
            $out['applied'] = true;
            $out['circuito_estado'] = CircuitoEstado::DERIVADO;

            return $out;
        }

        $this->circuito->afterDocumentacionClinica($guardia, $pesId, $payloadBase);
        $out['applied'] = true;
        $out['circuito_estado'] = CircuitoEstado::ATENDIDO;

        return $out;
    }

    private function isGuardiaEncounter(Encounter $encounter): bool
    {
        $parent = strtoupper(trim((string) ($encounter->parent_type ?? '')));

        return $parent === Encounter::PARENT_GUARDIA || $parent === 'GUARDIA';
    }

    /**
     * @param array<string, mixed> $datosExtraidos
     */
    private function signalsInternacion(Encounter $encounter, array $datosExtraidos): bool
    {
        $haystack = $this->buildHaystack($encounter, $datosExtraidos);
        if ($haystack === '') {
            return false;
        }

        foreach ([
            'internacion',
            'internación',
            'internar',
            'pase a intern',
            'ingreso a intern',
            'unidad de cuidados intensivos',
            ' uci',
            'uci ',
            'uti ',
            ' uti',
            'cama de intern',
        ] as $needle) {
            if (mb_strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $datosExtraidos
     */
    private function signalsDerivacionInstitucional(Encounter $encounter, array $datosExtraidos): bool
    {
        $srs = ServiceRequest::find()
            ->where(['encounter_id' => (int) $encounter->id, 'deleted_at' => null])
            ->andWhere(['category' => ['referral', 'derivacion', 'derivación']])
            ->limit(1)
            ->exists();
        if ($srs) {
            return true;
        }

        $haystack = $this->buildHaystack($encounter, $datosExtraidos);
        if ($haystack === '') {
            return false;
        }

        foreach ([
            'derivación al hospital',
            'derivacion al hospital',
            'derivación a hospital',
            'derivacion a hospital',
            'hospital de referencia',
            'otro efector',
            'centro con hemodinamia',
            'derivación inmediata',
            'derivacion inmediata',
            'traslado a',
            'derivar a',
        ] as $needle) {
            if (mb_strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $datosExtraidos
     */
    private function buildHaystack(Encounter $encounter, array $datosExtraidos): string
    {
        $blobs = [];

        foreach (['ConsultaDerivaciones', 'ConsultaIndicaciones', 'ConsultaPracticas'] as $cat) {
            $payload = $datosExtraidos[$cat] ?? null;
            if (!is_array($payload)) {
                continue;
            }
            $blobs[] = $this->flattenToText($payload);
        }

        $reason = trim((string) ($encounter->reason_text ?? ''));
        if ($reason !== '') {
            $blobs[] = $reason;
        }
        $note = trim((string) ($encounter->note ?? ''));
        if ($note !== '') {
            $blobs[] = $note;
        }

        $srs = ServiceRequest::find()
            ->where(['encounter_id' => (int) $encounter->id, 'deleted_at' => null])
            ->limit(50)
            ->all();
        foreach ($srs as $sr) {
            if (!$sr instanceof ServiceRequest) {
                continue;
            }
            $blobs[] = trim((string) ($sr->display ?? ''));
            $blobs[] = trim((string) ($sr->note ?? ''));
            $blobs[] = trim((string) ($sr->category ?? ''));
            $blobs[] = trim((string) ($sr->code ?? ''));
        }

        return mb_strtolower(implode(' ', array_filter($blobs)));
    }

    /**
     * @param mixed $payload
     */
    private function flattenToText($payload): string
    {
        if (is_string($payload)) {
            return $payload;
        }
        if (!is_array($payload)) {
            return '';
        }
        $parts = [];
        array_walk_recursive($payload, static function ($v) use (&$parts): void {
            if (is_string($v) || is_numeric($v)) {
                $parts[] = (string) $v;
            }
        });

        return implode(' ', $parts);
    }
}
