<?php

namespace common\components\Domain\Clinical\Inpatient\Service;

use common\components\Platform\Agent\AgentRunRecorder;
use common\models\Guardia;
use common\models\Person\Persona;
use Yii;

/**
 * Agente F02 v1: sugiere ranking de camas al ingresar paciente.
 */
final class InternacionCamaSugerenciaAgent
{
    public const AGENT_ID = InternacionCamaSugerenciaService::AGENT_ID;

    public const TRIGGER_TYPE = 'internacion_ingreso_contexto';

    private InternacionCamaSugerenciaService $sugerencia;

    public function __construct(?InternacionCamaSugerenciaService $sugerencia = null)
    {
        $this->sugerencia = $sugerencia ?? new InternacionCamaSugerenciaService();
    }

    public function isEnabled(): bool
    {
        return (bool) (Yii::$app->params['autonomous_agent_internacion_cama_sugerencia_enabled'] ?? true);
    }

    /**
     * @return list<array{id_cama: int, score: int, label: string, reasons: list<string>}>
     */
    public function suggestForIngreso(
        int $idEfector,
        int $idPersona,
        ?int $idGuardia = null
    ): array {
        if (!$this->isEnabled() || $idEfector <= 0) {
            return [];
        }

        $guardia = $idGuardia !== null && $idGuardia > 0 ? Guardia::findOne($idGuardia) : null;
        $persona = $idPersona > 0 ? Persona::findOne($idPersona) : null;
        $requirements = $this->sugerencia->requirementsFromGuardia($guardia, $persona);
        $ranked = $this->sugerencia->rankCamas($idEfector, $requirements);

        if ($ranked !== []) {
            AgentRunRecorder::record(
                self::AGENT_ID,
                self::TRIGGER_TYPE,
                'ranked',
                $idGuardia,
                null,
                $idPersona > 0 ? $idPersona : null,
                null,
                $requirements,
                ['top' => array_slice($ranked, 0, 3)]
            );
        }

        return $ranked;
    }
}
