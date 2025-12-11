<?php

use yii\db\Migration;

/**
 * Eliminar tabla abreviaturas_sugeridas si existe
 * Esta tabla ya no se usa en el nuevo flujo híbrido.
 * Las sugerencias ahora se guardan directamente en abreviaturas_medicas con activo=0
 */
class m240101_000012_drop_abreviaturas_sugeridas_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Verificar si la tabla existe antes de eliminarla
        $tableSchema = $this->db->schema->getTableSchema('{{%abreviaturas_sugeridas}}');
        
        if ($tableSchema !== null) {
            // Eliminar índices primero si existen
            try {
                $this->dropIndex('idx_abreviaturas_sugeridas_estado', '{{%abreviaturas_sugeridas}}');
            } catch (\Exception $e) {
                // El índice puede no existir
            }
            
            try {
                $this->dropIndex('idx_abreviaturas_sugeridas_frecuencia', '{{%abreviaturas_sugeridas}}');
            } catch (\Exception $e) {
                // El índice puede no existir
            }
            
            // Eliminar la tabla
            $this->dropTable('{{%abreviaturas_sugeridas}}');
            
            echo "Tabla abreviaturas_sugeridas eliminada correctamente.\n";
        } else {
            echo "La tabla abreviaturas_sugeridas no existe, no se requiere acción.\n";
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Recrear la tabla si se necesita hacer rollback
        // Nota: Esta tabla ya no se usa en el nuevo flujo, pero mantenemos el rollback por seguridad
        $tableSchema = $this->db->schema->getTableSchema('{{%abreviaturas_sugeridas}}');
        
        if ($tableSchema === null) {
            $this->createTable('{{%abreviaturas_sugeridas}}', [
                'id' => $this->primaryKey(),
                'abreviatura' => $this->string(50)->notNull()->comment('Abreviatura médica'),
                'expansion_propuesta' => $this->string(255)->comment('Expansión propuesta'),
                'contexto' => $this->text()->comment('Contexto de uso'),
                'texto_completo' => $this->text()->comment('Texto completo donde apareció'),
                'especialidad' => $this->string(100)->comment('Especialidad médica'),
                'usuario_id' => $this->integer()->comment('ID del usuario que reportó'),
                'frecuencia_reporte' => $this->integer()->defaultValue(1)->comment('Frecuencia de reporte'),
                'estado' => $this->string(20)->defaultValue('pendiente')->comment('Estado: pendiente, aprobada, rechazada'),
                'fecha_reporte' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
                'fecha_revision' => $this->timestamp()->null(),
                'revisado_por' => $this->integer()->comment('ID del usuario que revisó'),
                'comentarios' => $this->text()->comment('Comentarios de revisión'),
            ]);
            
            $this->createIndex('idx_abreviaturas_sugeridas_estado', '{{%abreviaturas_sugeridas}}', 'estado');
            $this->createIndex('idx_abreviaturas_sugeridas_frecuencia', '{{%abreviaturas_sugeridas}}', 'frecuencia_reporte');
        }
    }
}

