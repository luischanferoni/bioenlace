<?php

namespace console\controllers;

use common\models\Clinical\Encounter;
use common\models\ConsultaMotivosMessage;
use Yii;
use yii\console\Controller;
use yii\helpers\Console;

/**
 * Proceso separado: agregar mensajes de motivos de consulta y actualizar Encounter.reason_text.
 * Luego se puede extender con codificación SNOMED, corrección ortográfica y estructuración.
 * Al insertar en consultas_motivos desde este proceso, usar ConsultaMotivos::ORIGEN_PACIENTE
 * para diferenciar de los motivos cargados por el médico (ORIGEN_MEDICO).
 *
 * Uso:
 *   php yii motivos-consulta/procesar              # Todos los encounters con mensajes pendientes
 *   php yii motivos-consulta/procesar --consultaId=123
 */
class MotivosConsultaController extends Controller
{
    /**
     * @var int|null Si se indica, solo se procesa este encounter (alias legacy: consultaId).
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
     * Agrega los mensajes de motivos en texto y actualiza reason_text del encounter.
     * Por ahora solo concatena texto; audio/imagen se marcan como [audio]/[imagen].
     * En el futuro: transcribir audio, describir imágenes, codificar SNOMED, corregir ortografía.
     */
    public function actionProcesar()
    {
        $this->stdout("Procesando motivos de consulta...\n", Console::FG_CYAN);

        $query = ConsultaMotivosMessage::find()
            ->select('encounter_id')
            ->groupBy('encounter_id');

        if ($this->consultaId !== null) {
            $query->andWhere(['encounter_id' => (int) $this->consultaId]);
        }

        $encounterIds = $query->column();
        if (empty($encounterIds)) {
            $this->stdout("No hay mensajes de motivos para procesar.\n", Console::FG_YELLOW);
            return;
        }

        $count = 0;
        foreach ($encounterIds as $encounterId) {
            $messages = ConsultaMotivosMessage::find()
                ->where(['encounter_id' => $encounterId])
                ->orderBy(['created_at' => SORT_ASC])
                ->all();

            $parts = [];
            foreach ($messages as $msg) {
                if ($msg->message_type === 'texto') {
                    $parts[] = trim($msg->texto);
                } elseif ($msg->message_type === 'audio') {
                    $parts[] = '[Audio]'; // TODO: transcribir con API de speech-to-text
                } elseif ($msg->message_type === 'imagen') {
                    $parts[] = '[Imagen]'; // TODO: describir con IA si se desea
                }
            }

            $texto = implode("\n", array_filter($parts));
            if ($texto === '') {
                continue;
            }

            $encounter = Encounter::findOne((int) $encounterId);
            if (!$encounter) {
                $this->stdout("  Encounter $encounterId no encontrado, omitiendo.\n", Console::FG_RED);
                continue;
            }

            $encounter->reason_text = $texto;
            // Si más adelante se codifica a SNOMED y se inserta ConsultaMotivos, usar origen = ConsultaMotivos::ORIGEN_PACIENTE
            if ($encounter->save(false)) {
                $count++;
                $this->stdout("  Encounter $encounterId: reason_text actualizado.\n", Console::FG_GREEN);
            } else {
                $this->stdout("  Encounter $encounterId: error al guardar.\n", Console::FG_RED);
            }
        }

        $this->stdout("Listo. Actualizados $count encounter(s).\n", Console::FG_CYAN);
        $this->stdout("Próximo paso: añadir codificación SNOMED, corrección ortográfica y estructuración según necesidad.\n", Console::FG_YELLOW);
    }
}
