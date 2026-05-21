<?php

namespace common\components\Clinical\Legacy;

use common\components\Clinical\Specialty\Inpatient\InpatientClinicalContext;
use common\components\Clinical\Specialty\Inpatient\InpatientOrderService;
use common\models\SegNivelInternacion;
use Yii;

/**
 * Puente Yii web: persiste medicación/prácticas/diagnósticos de internación en tablas FHIR.
 *
 * Reemplaza escrituras a `seg_nivel_internacion_medicamento` / `_practica` / `_diagnostico` (retiradas).
 */
final class InternacionClinicalBridge
{
    private InpatientOrderService $orders;

    public function __construct(?InpatientOrderService $orders = null)
    {
        $this->orders = $orders ?? new InpatientOrderService();
    }

    /**
     * @param SegNivelInternacionMedicamento[] $models
     */
    public function persistMedicamentos(int $internacionId, array $models): void
    {
        $internacion = $this->requireInternacion($internacionId);
        $ctx = InpatientClinicalContext::ensure($internacion);
        $this->orders->persistMedicationRows($ctx, $models);
    }

    /**
     * @param SegNivelInternacionPractica[] $models
     */
    public function persistPracticas(int $internacionId, array $models): void
    {
        $internacion = $this->requireInternacion($internacionId);
        $ctx = InpatientClinicalContext::ensure($internacion);
        $this->orders->persistPracticeRows($ctx, $models);
    }

    /**
     * @param SegNivelInternacionDiagnostico[] $models
     */
    public function persistDiagnosticos(int $internacionId, array $models): void
    {
        $internacion = $this->requireInternacion($internacionId);
        $ctx = InpatientClinicalContext::ensure($internacion);
        $this->orders->persistDiagnosisRows($ctx, $models);
    }

    private function requireInternacion(int $internacionId): SegNivelInternacion
    {
        $internacion = SegNivelInternacion::findOne($internacionId);
        if ($internacion === null) {
            throw new \InvalidArgumentException('Internación no encontrada: ' . $internacionId);
        }
        if (empty($internacion->fecha_fin)) {
            return $internacion;
        }
        Yii::warning(
            'Persistencia clínica en internación ya dada de alta #' . $internacionId,
            __METHOD__
        );

        return $internacion;
    }
}
