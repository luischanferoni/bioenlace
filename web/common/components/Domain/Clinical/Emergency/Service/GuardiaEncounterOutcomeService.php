<?php

namespace common\components\Domain\Clinical\Emergency\Service;

use common\models\Clinical\Encounter;
use common\models\Clinical\ServiceRequest;
use common\models\Guardia;
use Yii;

/**
 * Deducciones de conducta de guardia a partir de la captura del encounter (EMER).
 *
 * El médico no “solicita cama” en el tablero: si documenta pase a internación / UCI,
 * se marca el pedido de cama para que staff lo complete (ingresar cama).
 */
final class GuardiaEncounterOutcomeService
{
    /** @var GuardiaInternacionService */
    private $internacion;

    public function __construct(?GuardiaInternacionService $internacion = null)
    {
        $this->internacion = $internacion ?? new GuardiaInternacionService();
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

        if ($this->internacion->isPendienteInternacion($guardia)
            || $this->internacion->internacionResuelta($guardiaId)
        ) {
            $out['applied'] = true;

            return $out;
        }

        if (!$this->signalsInternacion($encounter, $datosExtraidos)) {
            return $out;
        }

        $idEfector = (int) ($guardia->id_efector ?? $encounter->id_efector ?? 0);
        if ($idEfector <= 0) {
            return $out;
        }

        try {
            $this->internacion->solicitarInternacion($guardiaId, $idEfector, $idEfector);
            $out['applied'] = true;
            $out['internacion_solicitada'] = true;
        } catch (\Throwable $e) {
            Yii::warning(
                'GuardiaEncounterOutcome: no se pudo marcar internación: ' . $e->getMessage(),
                'emergency-guardia'
            );
        }

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

        $haystack = mb_strtolower(implode(' ', array_filter($blobs)));
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
