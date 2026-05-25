<?php

namespace common\components\Emergency;

use common\models\Emergency\GuardiaTriage;
use common\models\Guardia;
use Yii;

final class GuardiaTriageService
{
    /** @var GuardiaCircuitoService */
    private $circuito;

    public function __construct(?GuardiaCircuitoService $circuito = null)
    {
        $this->circuito = $circuito ?? new GuardiaCircuitoService();
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function registrar(int $guardiaId, array $body, int $idEfector): array
    {
        $guardia = Guardia::findOne($guardiaId);
        if ($guardia === null) {
            throw new \InvalidArgumentException('Guardia no encontrada.');
        }
        GuardiaEfectorAccess::assertGuardiaEnEfector($guardia, $idEfector);
        $this->circuito->assertCanRegisterTriage($guardia);

        $scale = (string) ($body['scale'] ?? TriageScale::MANCHESTER);
        if (!TriageScale::isValid($scale)) {
            throw new \InvalidArgumentException('Escala de triage no soportada.');
        }
        $level = (int) ($body['level'] ?? 0);
        if ($level < 1 || $level > 5) {
            throw new \InvalidArgumentException('level debe estar entre 1 y 5.');
        }
        $reasonText = trim((string) ($body['reason_text'] ?? ''));
        if ($reasonText === '') {
            throw new \InvalidArgumentException('Se requiere reason_text.');
        }
        $reasonCode = isset($body['reason_code']) ? trim((string) $body['reason_code']) : null;
        $vitals = $body['vitals'] ?? null;
        if ($vitals !== null && !is_array($vitals)) {
            throw new \InvalidArgumentException('vitals debe ser un objeto JSON.');
        }

        $pesId = GuardiaEfectorAccess::resolvePesId(
            isset($body['id_profesional_efector_servicio'])
                ? (int) $body['id_profesional_efector_servicio']
                : null
        );

        $now = date('Y-m-d H:i:s');
        $row = GuardiaTriage::findOne(['guardia_id' => $guardiaId]);
        if ($row === null) {
            $row = new GuardiaTriage();
            $row->guardia_id = $guardiaId;
            $row->created_at = $now;
        }
        $row->scale = $scale;
        $row->level = $level;
        $row->reason_code = $reasonCode !== '' ? $reasonCode : null;
        $row->reason_text = $reasonText;
        $row->vitals_json = $vitals !== null
            ? json_encode($vitals, JSON_UNESCAPED_UNICODE)
            : null;
        $row->triaged_at = $now;
        $row->id_profesional_efector_servicio = $pesId;
        $row->updated_at = $now;

        if (!$row->save()) {
            throw new \RuntimeException('No se pudo guardar triage: ' . json_encode($row->errors));
        }

        $this->circuito->afterTriage($guardia, $level, $pesId);

        return [
            'guardia_id' => $guardiaId,
            'triage' => $this->serializeTriage($row),
            'circuito_estado' => CircuitoEstado::ESPERA_MEDICO,
            'prioridad_triage' => $level,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeTriage(GuardiaTriage $row): array
    {
        $meta = TriageScale::levelMeta()[$row->level] ?? ['label' => '', 'color' => '#999'];

        return [
            'scale' => $row->scale,
            'level' => (int) $row->level,
            'level_label' => $meta['label'],
            'level_color' => $meta['color'],
            'reason_code' => $row->reason_code,
            'reason_text' => $row->reason_text,
            'vitals' => $row->getVitalsArray(),
            'triaged_at' => $row->triaged_at,
            'id_profesional_efector_servicio' => $row->id_profesional_efector_servicio,
        ];
    }
}
