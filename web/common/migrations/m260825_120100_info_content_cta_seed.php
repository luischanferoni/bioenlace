<?php

use yii\db\Migration;

/**
 * Fase 03: CTA intent_ids + keywords más tolerantes + artículo pre_consulta (concepto).
 */
class m260825_120100_info_content_cta_seed extends Migration
{
    private const TABLE = '{{%info_content_article}}';

    public function safeUp(): void
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) === null) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        $this->update(self::TABLE, [
            'intent_ids' => 'personas.vincular-menor-flow,personas.designar-representante-flow',
            'keywords' => 'representacion,representar,representante,tutela,menor,hijo,hija,sobrino,sobrina,nieto,nieta,vincular,delegar,operar por mi,mi nene,mi nena,mi hijo,mi hija,familia,familiar',
            'updated_at' => $now,
        ], ['topic' => 'representacion', 'scope' => 'producto']);

        $this->update(self::TABLE, [
            'intent_ids' => 'turnos.crear-como-paciente,atencion.necesito-atencion',
            'updated_at' => $now,
        ], ['topic' => 'turnos', 'scope' => 'producto']);

        $this->update(self::TABLE, [
            'intent_ids' => 'turnos.crear-como-paciente',
            'updated_at' => $now,
        ], ['topic' => 'teleconsulta', 'scope' => 'producto']);

        $exists = (new \yii\db\Query())
            ->from(self::TABLE)
            ->where(['topic' => 'pre_consulta', 'scope' => 'producto'])
            ->exists($this->db);

        if (!$exists) {
            $this->insert(self::TABLE, [
                'topic' => 'pre_consulta',
                'title' => '¿Qué es la asistencia pre-consulta?',
                'body' => <<<'MD'
Antes de algunas atenciones, Bioenlace puede pedirte completar un cuestionario breve (asistencia pre-consulta).

**¿Para qué sirve?**
- Anticipar información útil para el profesional.
- Acortar tiempos el día de la atención.
- No reemplaza la consulta médica.

**¿Qué no es?**
No son las preguntas clínicas del pack de cuidado ni un diagnóstico. Si tenés un cuestionario pendiente, el asistente te lo va a ofrecer en el momento adecuado.
MD,
                'scope' => 'producto',
                'keywords' => 'pre consulta,preconsulta,cuestionario,asistencia pre consulta,formulario antes,pack,care pack',
                'intent_ids' => 'care-packs.asistencia-pre-consulta-flow',
                'activo' => 1,
                'priority' => 8,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function safeDown(): void
    {
        if ($this->db->schema->getTableSchema(self::TABLE, true) === null) {
            return;
        }

        $this->delete(self::TABLE, ['topic' => 'pre_consulta', 'scope' => 'producto']);
        foreach (['representacion', 'turnos', 'teleconsulta'] as $topic) {
            $this->update(self::TABLE, ['intent_ids' => null], [
                'topic' => $topic,
                'scope' => 'producto',
            ]);
        }
    }
}
