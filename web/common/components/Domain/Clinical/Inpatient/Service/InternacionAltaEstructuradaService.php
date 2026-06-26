<?php

namespace common\components\Domain\Clinical\Inpatient\Service;

use common\models\SegNivelInternacion;
use common\models\SegNivelInternacionRepository;
use common\models\SegNivelInternacionTipoAlta;

/**
 * Alta hospitalaria con epicrisis y checklist mínimo (staff).
 */
final class InternacionAltaEstructuradaService
{
    private InternacionEpicrisisPlantillaService $plantillas;

    public function __construct(?InternacionEpicrisisPlantillaService $plantillas = null)
    {
        $this->plantillas = $plantillas ?? new InternacionEpicrisisPlantillaService();
    }

    /**
     * @return array<string, mixed>
     */
    public function contextoAlta(SegNivelInternacion $internacion, int $idEfector): array
    {
        $paciente = $internacion->paciente;
        $nombre = $paciente && method_exists($paciente, 'getNombreCompleto')
            ? $paciente->getNombreCompleto(\common\models\Persona::FORMATO_NOMBRE_A_N)
            : 'Paciente';

        $pesId = (int) (\Yii::$app->user->getIdProfesionalEfectorServicio() ?? 0);
        $responsableNombre = $this->resolveNombreProfesionalSesion($pesId);

        return [
            'internacion_id' => (int) $internacion->id,
            'paciente_nombre' => $nombre,
            'plantillas' => $this->plantillas->listar($idEfector, $this->resolveIdServicioSesion()),
            'tipos_alta' => $this->tiposAltaOptions(),
            'responsable_pes_id' => $pesId > 0 ? $pesId : null,
            'responsable_nombre' => $responsableNombre,
        ];
    }

    public function previewPlantilla(int $plantillaId, int $internacionId, int $idEfector): string
    {
        $internacion = SegNivelInternacion::findOne($internacionId);
        if ($internacion === null) {
            throw new \InvalidArgumentException('Internación no encontrada.');
        }
        InternacionEfectorAccess::assertInternacionEnEfector($internacion, $idEfector);

        return $this->plantillas->render($plantillaId, $internacion);
    }
    /**
     * @return list<array{id: int, label: string}>
     */
    public function tiposAltaOptions(): array
    {
        $out = [];
        foreach (SegNivelInternacionTipoAlta::find()->orderBy(['tipo_alta' => SORT_ASC])->all() as $row) {
            $out[] = [
                'id' => (int) $row->id,
                'label' => (string) $row->tipo_alta,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public function registrarAlta(int $internacionId, int $idEfector, array $post): array
    {
        $internacion = SegNivelInternacion::findOne($internacionId);
        if ($internacion === null) {
            throw new \InvalidArgumentException('Internación no encontrada.');
        }
        if ($internacion->fecha_fin !== null && $internacion->fecha_fin !== '') {
            throw new \InvalidArgumentException('La internación ya tiene alta registrada.');
        }
        InternacionEfectorAccess::assertInternacionEnEfector($internacion, $idEfector);

        $plantillaId = (int) ($post['plantilla_id'] ?? 0);
        $epicrisis = trim((string) ($post['epicrisis'] ?? $post['observaciones_alta'] ?? ''));
        if ($epicrisis === '' && $plantillaId > 0) {
            $epicrisis = $this->plantillas->render($plantillaId, $internacion);
        }
        if (mb_strlen($epicrisis) < 20) {
            throw new \InvalidArgumentException('La epicrisis debe tener al menos 20 caracteres.');
        }

        $pesAlta = (int) ($post['id_profesional_responsable'] ?? 0);
        if ($pesAlta <= 0) {
            $pesAlta = (int) (\Yii::$app->user->getIdProfesionalEfectorServicio() ?? 0);
        }
        $responsableNombre = $this->resolveNombreProfesionalSesion($pesAlta);

        foreach (['checklist_medicacion', 'checklist_indicaciones', 'checklist_pedidos'] as $chk) {
            if (!$this->isTruthy($post[$chk] ?? null)) {
                throw new \InvalidArgumentException(
                    'Debe confirmar el checklist de alta (medicación, indicaciones y pedidos).'
                );
            }
        }

        $internacion->scenario = SegNivelInternacion::EGRESO_PACIENTE;
        $internacion->fecha_fin = trim((string) ($post['fecha_fin'] ?? '')) ?: date('d/m/Y');
        $internacion->hora_fin = trim((string) ($post['hora_fin'] ?? '')) ?: date('H:i');
        $internacion->id_tipo_alta = (int) ($post['id_tipo_alta'] ?? 0);
        if ($internacion->id_tipo_alta <= 0) {
            throw new \InvalidArgumentException('Se requiere el tipo de alta.');
        }

        $checklistMeta = [
            'medicacion' => true,
            'indicaciones' => true,
            'pedidos' => true,
            'responsable_pes_id' => $pesAlta > 0 ? $pesAlta : null,
            'responsable_nombre' => $responsableNombre,
            'plantilla_id' => $plantillaId > 0 ? $plantillaId : null,
            'registrado_at' => date('c'),
        ];
        $internacion->observaciones_alta = $epicrisis . "\n\n---\n[checklist_alta] "
            . json_encode($checklistMeta, JSON_UNESCAPED_UNICODE);

        if (!$internacion->validate()) {
            $first = reset($internacion->firstErrors);

            throw new \InvalidArgumentException($first !== false ? (string) $first : 'Datos de alta inválidos.');
        }

        SegNivelInternacionRepository::doExternacion($internacion);

        try {
            (new PostDischargeFollowupAgent())->onDischarge($internacion);
        } catch (\Throwable $e) {
            \Yii::warning(
                'PostDischargeFollowupAgent tras alta #' . (int) $internacion->id . ': ' . $e->getMessage(),
                'autonomous-agent'
            );
        }

        return [
            'id_internacion' => (int) $internacion->id,
            'fecha_fin' => $internacion->fecha_fin,
            'id_tipo_alta' => (int) $internacion->id_tipo_alta,
            'message' => 'Alta hospitalaria registrada.',
        ];
    }

    /**
     * @param mixed $value
     */
    private function isTruthy($value): bool
    {
        if ($value === true || $value === 1) {
            return true;
        }
        if (!is_string($value)) {
            return false;
        }
        $v = strtolower(trim($value));

        return in_array($v, ['1', 'true', 'yes', 'si', 'sí', 'on'], true);
    }

    private function resolveIdServicioSesion(): ?int
    {
        $raw = \Yii::$app->user->getServicioActual();

        return $raw !== null && $raw !== '' ? (int) $raw : null;
    }

    private function resolveNombreProfesionalSesion(int $pesId): ?string
    {
        if ($pesId <= 0) {
            return null;
        }
        $pes = \common\models\ProfesionalEfectorServicio::findOne(['id' => $pesId, 'deleted_at' => null]);
        if ($pes === null || $pes->persona === null) {
            return null;
        }

        return $pes->persona->getNombreCompleto(\common\models\Persona::FORMATO_NOMBRE_A_N);
    }
}
