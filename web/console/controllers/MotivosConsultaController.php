<?php

namespace console\controllers;

use common\components\Clinical\Service\AppointmentReasonBatchService;
use common\components\Clinical\Service\AppointmentReasonWindowService;
use common\models\Clinical\Encounter;
use common\models\ConsultaMotivosMessage;
use Yii;
use yii\console\Controller;
use yii\helpers\Console;

/**
 * Motivos de consulta (app paciente): procesamiento en lote con IA.
 *
 * Uso:
 *   php yii motivos-consulta/procesar              # Encounters con mensajes sin procesar
 *   php yii motivos-consulta/procesar --consultaId=123
 *   php yii motivos-consulta/procesar-vencidos     # Turnos que ya pasaron ventana de cierre (cron respaldo)
 */
class MotivosConsultaController extends Controller
{
    /**
     * @var int|null encounter_id (alias legacy consultaId).
     */
    public $consultaId;

    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['consultaId']);
    }

    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), ['c' => 'consultaId']);
    }

    /**
     * Procesa motivos con una sola llamada IA por encounter (texto + audios + referencia a imágenes).
     */
    public function actionProcesar()
    {
        $this->stdout("Procesando motivos de consulta (lote IA)...\n", Console::FG_CYAN);

        $encounterIds = $this->encounterIdsPendientes();
        if ($encounterIds === []) {
            $this->stdout("No hay encounters pendientes.\n", Console::FG_YELLOW);

            return;
        }

        $ok = 0;
        $fail = 0;
        foreach ($encounterIds as $encounterId) {
            $result = AppointmentReasonBatchService::process((int) $encounterId);
            if ($result['ok']) {
                $ok++;
                $this->stdout("  Encounter {$encounterId}: OK\n", Console::FG_GREEN);
            } else {
                $fail++;
                $this->stdout("  Encounter {$encounterId}: {$result['message']}\n", Console::FG_RED);
            }
        }

        $this->stdout("Listo. OK={$ok} fallos={$fail}\n", Console::FG_CYAN);
    }

    /**
     * Respaldo si falló turno-notificacion/run: encounters cuya ventana de carga ya cerró y aún no tienen resumen IA.
     */
    public function actionProcesarVencidos($limit = 50)
    {
        $limit = max(1, (int) $limit);
        $this->stdout("Procesando motivos vencidos (ventana cerrada)...\n", Console::FG_CYAN);

        $n = 0;
        foreach ($this->encounterIdsPendientes() as $encounterId) {
            if ($n >= $limit) {
                break;
            }
            $encounter = Encounter::findOne((int) $encounterId);
            if (!$encounter || AppointmentReasonWindowService::isInputOpenForEncounter($encounter)) {
                continue;
            }
            $result = AppointmentReasonBatchService::process((int) $encounterId);
            if ($result['ok']) {
                $n++;
                $this->stdout("  Encounter {$encounterId}: OK\n", Console::FG_GREEN);
            }
        }

        $this->stdout("Procesados: {$n}\n", Console::FG_CYAN);
    }

    /**
     * @return list<int>
     */
    private function encounterIdsPendientes(): array
    {
        $query = ConsultaMotivosMessage::find()
            ->select('encounter_id')
            ->groupBy('encounter_id');

        if ($this->consultaId !== null) {
            $query->andWhere(['encounter_id' => (int) $this->consultaId]);
        }

        $ids = array_map('intval', $query->column());
        if ($ids === []) {
            return [];
        }

        return Encounter::find()
            ->select('id')
            ->where(['id' => $ids])
            ->andWhere(['motivos_ia_processed_at' => null])
            ->column();
    }
}
