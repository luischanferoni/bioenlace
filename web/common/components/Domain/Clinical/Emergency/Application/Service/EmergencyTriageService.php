<?php

namespace common\components\Domain\Clinical\Emergency\Application\Service;

use common\components\Domain\Clinical\Emergency\Domain\BoardState;
use common\components\Domain\Clinical\Emergency\Domain\BoardEventType;
use common\components\Domain\Clinical\Encounter\Application\Presentation\EpisodioDateTimePresenter;
use common\components\Domain\Clinical\Emergency\Domain\TriageScale;
use common\models\Clinical\Emergency\EmergencyTriage;
use common\models\Clinical\Emergency\EmergencyEpisode;
use Yii;

final class EmergencyTriageService
{
    /** @var EmergencyBoardService */
    private $circuito;

    public function __construct(?EmergencyBoardService $circuito = null)
    {
        $this->circuito = $circuito ?? new EmergencyBoardService();
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function registrar(int $guardiaId, array $body, int $idEfector): array
    {
        $guardia = EmergencyEpisode::findOne($guardiaId);
        if ($guardia === null) {
            throw new \InvalidArgumentException('Guardia no encontrada.');
        }
        EmergencyEfectorAccess::assertGuardiaEnEfector($guardia, $idEfector);
        $existing = EmergencyTriage::findOne(['guardia_id' => $guardiaId]);
        $isUpdate = $existing !== null;
        $previousLevel = $isUpdate ? (int) $existing->level : null;
        $this->circuito->assertCanRegisterTriage($guardia, $isUpdate);

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
        $vitals = EmergencyTriageVitalsService::normalizeFromBody($body);

        $pesId = EmergencyEfectorAccess::resolvePesId(
            isset($body['id_profesional_efector_servicio'])
                ? (int) $body['id_profesional_efector_servicio']
                : null
        );

        $now = date('Y-m-d H:i:s');
        $row = $existing;
        if ($row === null) {
            $row = new EmergencyTriage();
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
        if ($isUpdate) {
            $this->circuito->recordEvent($guardiaId, BoardEventType::RE_TRIAGE, $pesId, [
                'previous_level' => $previousLevel,
                'level' => $level,
            ]);
        }

        $guardia = EmergencyEpisode::find()->where(['id' => $guardiaId])->with('paciente')->one() ?? $guardia;
        (new EmergencyPushService())->notifyCriticalTriage($guardia, $level, $pesId);

        return [
            'guardia_id' => $guardiaId,
            'triage' => $this->serializeTriage($row),
            'circuito_estado' => BoardState::ESPERA_MEDICO,
            'prioridad_triage' => $level,
            're_triage' => $isUpdate,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeTriage(EmergencyTriage $row): array
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
            'triaged_at' => EpisodioDateTimePresenter::display((string) ($row->triaged_at ?? '')),
            'id_profesional_efector_servicio' => $row->id_profesional_efector_servicio,
        ];
    }
}
