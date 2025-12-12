<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Migración para consolidar terminos_contexto_medico y diccionario_ortografico
 * en una sola tabla unificada: diccionario_medico
 */
class m240101_000013_consolidar_diccionarios_medicos extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // 1. Crear la nueva tabla unificada
        $this->createTable('{{%diccionario_medico}}', [
            'id' => $this->primaryKey(),
            'termino' => $this->string(150)->notNull()->comment('Término, palabra o patrón'),
            'correccion' => $this->string(150)->null()->comment('Corrección sugerida (solo para tipo=error)'),
            'tipo' => $this->string(30)->notNull()->defaultValue('termino')->comment('termino|error|stopword|regex_preservar'),
            'categoria' => $this->string(100)->null()->comment('Categoría semántica o clínica'),
            'especialidad' => $this->string(100)->null()->comment('Especialidad médica opcional'),
            'frecuencia' => $this->integer()->notNull()->defaultValue(0)->comment('Frecuencia de uso'),
            'peso' => $this->decimal(5,2)->notNull()->defaultValue(1.00)->comment('Peso para scoring'),
            'fuente' => $this->string(50)->null()->comment('Origen del término (manual, IA, importación, etc.)'),
            'metadata' => $this->json()->null()->comment('Información adicional'),
            'activo' => $this->boolean()->notNull()->defaultValue(true)->comment('Disponible para uso'),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP')->append('ON UPDATE CURRENT_TIMESTAMP'),
        ]);

        // Índices
        $this->createIndex('idx_diccionario_medico_termino', '{{%diccionario_medico}}', 'termino');
        $this->createIndex('idx_diccionario_medico_tipo', '{{%diccionario_medico}}', 'tipo');
        $this->createIndex('idx_diccionario_medico_especialidad', '{{%diccionario_medico}}', 'especialidad');
        $this->createIndex('idx_diccionario_medico_activo', '{{%diccionario_medico}}', 'activo');
        $this->createIndex('idx_diccionario_medico_compuesto', '{{%diccionario_medico}}', ['termino', 'tipo', 'especialidad'], true);

        // 2. Migrar datos de diccionario_ortografico (todos los tipos)
        $this->migrateDiccionarioOrtografico();

        // 3. Migrar datos de terminos_contexto_medico (solo regex para preservar)
        $this->migrateTerminosContextoMedico();

        // 4. Crear tablas de respaldo (backup) antes de eliminar las originales
        $this->createBackupTables();
    }

    /**
     * Migrar datos de diccionario_ortografico
     */
    private function migrateDiccionarioOrtografico()
    {
        $errores = (new Query())
            ->from('{{%diccionario_ortografico}}')
            ->where(['activo' => 1])
            ->all($this->db);

        foreach ($errores as $error) {
            // Mapear tipos: termino, error, stopword -> se mantienen igual
            $tipo = $error['tipo'];
            
            // Preparar metadata si existe
            $metadata = null;
            if (!empty($error['metadata'])) {
                $metadata = is_string($error['metadata']) ? json_decode($error['metadata'], true) : $error['metadata'];
            }

            // Insertar en la nueva tabla
            $this->insert('{{%diccionario_medico}}', [
                'termino' => $error['termino'],
                'correccion' => $error['correccion'],
                'tipo' => $tipo,
                'categoria' => $error['categoria'],
                'especialidad' => $error['especialidad'],
                'frecuencia' => $error['frecuencia'] ?? 0,
                'peso' => $error['peso'] ?? 1.00,
                'fuente' => isset($metadata['fuente']) ? $metadata['fuente'] : null,
                'metadata' => $metadata,
                'activo' => $error['activo'] ?? true,
                'created_at' => $error['created_at'] ?? date('Y-m-d H:i:s'),
                'updated_at' => $error['updated_at'] ?? date('Y-m-d H:i:s'),
            ]);
        }

        echo "Migrados " . count($errores) . " registros de diccionario_ortografico\n";
    }

    /**
     * Migrar datos de terminos_contexto_medico (solo regex para preservar)
     */
    private function migrateTerminosContextoMedico()
    {
        $terminos = (new Query())
            ->from('{{%terminos_contexto_medico}}')
            ->where([
                'tipo' => 'regex',
                'activo' => 1
            ])
            ->andWhere(['or', 
                ['categoria' => 'preservar'],
                ['categoria' => 'notacion_medica']
            ])
            ->all($this->db);

        foreach ($terminos as $termino) {
            // Mapear: tipo='regex' -> tipo='regex_preservar'
            $tipo = 'regex_preservar';
            
            // Preparar metadata
            $metadata = null;
            if (!empty($termino['metadata'])) {
                $metadata = is_string($termino['metadata']) ? json_decode($termino['metadata'], true) : $termino['metadata'];
            }
            
            // Agregar fuente a metadata si existe
            if (!empty($termino['fuente'])) {
                if ($metadata === null) {
                    $metadata = [];
                }
                $metadata['fuente_original'] = $termino['fuente'];
            }

            // Verificar si ya existe (por el índice único compuesto)
            $existe = (new Query())
                ->from('{{%diccionario_medico}}')
                ->where([
                    'termino' => $termino['termino'],
                    'tipo' => $tipo,
                    'especialidad' => $termino['especialidad']
                ])
                ->exists($this->db);

            if (!$existe) {
                $this->insert('{{%diccionario_medico}}', [
                    'termino' => $termino['termino'],
                    'correccion' => null, // Los regex no tienen corrección
                    'tipo' => $tipo,
                    'categoria' => $termino['categoria'],
                    'especialidad' => $termino['especialidad'],
                    'frecuencia' => $termino['frecuencia_uso'] ?? 0,
                    'peso' => $termino['peso'] ?? 1.00,
                    'fuente' => $termino['fuente'],
                    'metadata' => $metadata,
                    'activo' => $termino['activo'] ?? true,
                    'created_at' => $termino['created_at'] ?? date('Y-m-d H:i:s'),
                    'updated_at' => $termino['updated_at'] ?? date('Y-m-d H:i:s'),
                ]);
            }
        }

        echo "Migrados " . count($terminos) . " registros de terminos_contexto_medico\n";
    }

    /**
     * Crear tablas de respaldo antes de eliminar las originales
     */
    private function createBackupTables()
    {
        // Crear backup de diccionario_ortografico
        $this->execute("CREATE TABLE {{%diccionario_ortografico_backup}} LIKE {{%diccionario_ortografico}}");
        $this->execute("INSERT INTO {{%diccionario_ortografico_backup}} SELECT * FROM {{%diccionario_ortografico}}");
        
        // Crear backup de terminos_contexto_medico
        $this->execute("CREATE TABLE {{%terminos_contexto_medico_backup}} LIKE {{%terminos_contexto_medico}}");
        $this->execute("INSERT INTO {{%terminos_contexto_medico_backup}} SELECT * FROM {{%terminos_contexto_medico}}");
        
        echo "Tablas de respaldo creadas\n";
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Eliminar la tabla unificada
        $this->dropTable('{{%diccionario_medico}}');
        
        // Eliminar tablas de respaldo si existen
        $this->dropTableIfExists('{{%diccionario_ortografico_backup}}');
        $this->dropTableIfExists('{{%terminos_contexto_medico_backup}}');
    }
}

