<?php

namespace console\controllers;

use Yii;
use yii\console\Controller;
use yii\helpers\Console;
use common\models\ConsultaMotivosMessage;
use common\models\Consulta;

/**
 * Proceso separado: agregar mensajes de motivos de consulta y actualizar Consulta.motivo_consulta.
 * Luego se puede extender con codificación SNOMED, corrección ortográfica y estructuración.
 * Al insertar en consultas_motivos desde este proceso, usar ConsultaMotivos::ORIGEN_PACIENTE
 * para diferenciar de los motivos cargados por el médico (ORIGEN_MEDICO).
 *
 * Uso:
 *   php yii motivos-consulta/procesar              # Todas las consultas con mensajes pendientes
 *   php yii motivos-consulta/procesar --consultaId=123
 */
class MotivosConsultaController extends Controller
{
    /**
     * @var int|null Si se indica, solo se procesa esta consulta.
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
     * Agrega los mensajes de motivos en texto y actualiza motivo_consulta.
     * Por ahora solo concatena texto; audio/imagen se marcan como [audio]/[imagen].
     * En el futuro: transcribir audio, describir imágenes, codificar SNOMED, corregir ortografía.
     */
    public function actionProcesar()
    {
        $this->stdout("Procesando motivos de consulta...\n", Console::FG_CYAN);

        $query = ConsultaMotivosMessage::find()
            ->select('consulta_id')
            ->groupBy('consulta_id');

        if ($this->consultaId !== null) {
            $query->andWhere(['consulta_id' => (int) $this->consultaId]);
        }

        $consultaIds = $query->column();
        if (empty($consultaIds)) {
            $this->stdout("No hay mensajes de motivos para procesar.\n", Console::FG_YELLOW);
            return;
        }

        $count = 0;
        foreach ($consultaIds as $consultaId) {
            $messages = ConsultaMotivosMessage::find()
                ->where(['consulta_id' => $consultaId])
                ->orderBy(['created_at' => SORT_ASC])
                ->all();

            $parts = [];
            foreach ($messages as $msg) {
                if ($msg->message_type === 'texto') {
                    $parts[] = trim($msg->content);
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

            $consulta = Consulta::findOne($consultaId);
            if (!$consulta) {
                $this->stdout("  Consulta $consultaId no encontrada, omitiendo.\n", Console::FG_RED);
                continue;
            }

            $consulta->motivo_consulta = $texto;
            // Si más adelante se codifica a SNOMED y se inserta ConsultaMotivos, usar origen = ConsultaMotivos::ORIGEN_PACIENTE
            if ($consulta->save(false)) {
                $count++;
                $this->stdout("  Consulta $consultaId: motivo_consulta actualizado.\n", Console::FG_GREEN);
            } else {
                $this->stdout("  Consulta $consultaId: error al guardar.\n", Console::FG_RED);
            }
        }

        $this->stdout("Listo. Actualizadas $count consulta(s).\n", Console::FG_CYAN);
        $this->stdout("Próximo paso: añadir codificación SNOMED, corrección ortográfica y estructuración según necesidad.\n", Console::FG_YELLOW);
    }
}
