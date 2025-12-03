<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%abreviaturas_medicas}}`.
 */
class m240101_000001_create_abreviaturas_medicas_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%abreviaturas_medicas}}', [
            'id' => $this->primaryKey(),
            'abreviatura' => $this->string(50)->notNull()->comment('Abreviatura médica'),
            'expansion_completa' => $this->string(255)->notNull()->comment('Expansión completa de la abreviatura'),
            'categoria' => $this->string(100)->comment('Categoría médica (síntoma, medicamento, procedimiento, etc.)'),
            'especialidad' => $this->string(100)->comment('Especialidad médica específica'),
            'contexto' => $this->text()->comment('Contexto de uso de la abreviatura'),
            'sinonimos' => $this->text()->comment('Sinónimos o variaciones de la abreviatura'),
            'frecuencia_uso' => $this->integer()->defaultValue(0)->comment('Frecuencia de uso para priorizar'),
            'activo' => $this->tinyInteger(1)->defaultValue(1)->comment('Si la abreviatura está activa'),
            'fecha_creacion' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'fecha_actualizacion' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
        ]);

        // Índices para optimizar búsquedas
        $this->createIndex('idx_abreviaturas_medicas_abreviatura', '{{%abreviaturas_medicas}}', 'abreviatura');
        $this->createIndex('idx_abreviaturas_medicas_especialidad', '{{%abreviaturas_medicas}}', 'especialidad');
        $this->createIndex('idx_abreviaturas_medicas_categoria', '{{%abreviaturas_medicas}}', 'categoria');
        $this->createIndex('idx_abreviaturas_medicas_activo', '{{%abreviaturas_medicas}}', 'activo');
        $this->createIndex('idx_abreviaturas_medicas_frecuencia', '{{%abreviaturas_medicas}}', 'frecuencia_uso');

        // Insertar abreviaturas comunes de oftalmología
        $this->insertAbreviaturasComunes();
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%abreviaturas_medicas}}');
    }

    /**
     * Insertar abreviaturas médicas comunes
     */
    private function insertAbreviaturasComunes()
    {
        $abreviaturas = [
            // Oftalmología
            ['AV', 'Agudeza Visual', 'medicion', 'oftalmologia', 'Medición de la capacidad visual del paciente'],
            ['PIO', 'Presión Intraocular', 'medicion', 'oftalmologia', 'Medición de la presión dentro del ojo'],
            ['FO', 'Fondo de Ojo', 'examen', 'oftalmologia', 'Examen del interior del ojo'],
            ['CV', 'Campo Visual', 'examen', 'oftalmologia', 'Evaluación del campo de visión'],
            ['OD', 'Ojo Derecho', 'anatomia', 'oftalmologia', 'Referencia al ojo derecho'],
            ['OI', 'Ojo Izquierdo', 'anatomia', 'oftalmologia', 'Referencia al ojo izquierdo'],
            ['AO', 'Ambos Ojos', 'anatomia', 'oftalmologia', 'Referencia a ambos ojos'],
            ['MGP', 'Meibomio Glándula Disfunción', 'condicion', 'oftalmologia', 'Disfunción de las glándulas de Meibomio'],
            ['OJO SECO', 'Síndrome de Ojo Seco', 'condicion', 'oftalmologia', 'Condición de sequedad ocular'],
            ['CAT', 'Catarata', 'condicion', 'oftalmologia', 'Opacidad del cristalino'],
            ['GLAUCOMA', 'Glaucoma', 'condicion', 'oftalmologia', 'Enfermedad del nervio óptico'],
            ['DMRE', 'Degeneración Macular Relacionada con la Edad', 'condicion', 'oftalmologia', 'Degeneración de la mácula'],
            ['RETINOPATIA', 'Retinopatía', 'condicion', 'oftalmologia', 'Enfermedad de la retina'],
            ['MIOPIA', 'Miopía', 'refraccion', 'oftalmologia', 'Error refractivo'],
            ['HIPERMETROPIA', 'Hipermetropía', 'refraccion', 'oftalmologia', 'Error refractivo'],
            ['ASTIGMATISMO', 'Astigmatismo', 'refraccion', 'oftalmologia', 'Error refractivo'],
            ['PRESBICIA', 'Presbicia', 'refraccion', 'oftalmologia', 'Pérdida de enfoque cercano'],
            
            // Medicamentos oftalmológicos
            ['LATANOPROST', 'Latanoprost', 'medicamento', 'oftalmologia', 'Medicamento para glaucoma'],
            ['TIMOLOL', 'Timolol', 'medicamento', 'oftalmologia', 'Beta bloqueador para glaucoma'],
            ['DORZOLAMIDA', 'Dorzolamida', 'medicamento', 'oftalmologia', 'Inhibidor de anhidrasa carbónica'],
            ['BRIMONIDINA', 'Brimonidina', 'medicamento', 'oftalmologia', 'Agonista alfa-2'],
            ['TRAVOPROST', 'Travoprost', 'medicamento', 'oftalmologia', 'Análogo de prostaglandina'],
            ['CICLOSPORINA', 'Ciclosporina', 'medicamento', 'oftalmologia', 'Inmunosupresor tópico'],
            ['LUBRICANTE', 'Lágrimas Artificiales', 'medicamento', 'oftalmologia', 'Tratamiento para ojo seco'],
            
            // Procedimientos
            ['FA', 'Fluoresceinografía', 'procedimiento', 'oftalmologia', 'Estudio con contraste'],
            ['OCT', 'Tomografía de Coherencia Óptica', 'procedimiento', 'oftalmologia', 'Imagen de alta resolución'],
            ['CIRUGIA CATARATA', 'Cirugía de Catarata', 'procedimiento', 'oftalmologia', 'Extracción del cristalino opaco'],
            ['LASIK', 'LASIK', 'procedimiento', 'oftalmologia', 'Cirugía refractiva'],
            ['PRK', 'Queratectomía Fotorrefractiva', 'procedimiento', 'oftalmologia', 'Cirugía refractiva'],
            
            // Síntomas comunes
            ['DOLOR OCULAR', 'Dolor Ocular', 'sintoma', 'oftalmologia', 'Dolor en el ojo'],
            ['VISION BORROSA', 'Visión Borrosa', 'sintoma', 'oftalmologia', 'Pérdida de claridad visual'],
            ['FOTOFOBIA', 'Fotofobia', 'sintoma', 'oftalmologia', 'Sensibilidad a la luz'],
            ['DIPLOPIA', 'Diplopía', 'sintoma', 'oftalmologia', 'Visión doble'],
            ['FLOTADORES', 'Miodesopsias', 'sintoma', 'oftalmologia', 'Manchas flotantes en la visión'],
            ['DESTELLOS', 'Fotopsias', 'sintoma', 'oftalmologia', 'Destellos de luz'],
            
            // Medidas y valores
            ['mmHg', 'milímetros de mercurio', 'unidad', 'oftalmologia', 'Unidad de presión'],
            ['20/20', 'Visión Normal', 'medicion', 'oftalmologia', 'Agudeza visual estándar'],
            ['6/6', 'Visión Normal', 'medicion', 'oftalmologia', 'Agudeza visual métrica'],
            
            // General médico
            ['HTA', 'Hipertensión Arterial', 'condicion', 'general', 'Presión arterial elevada'],
            ['DM', 'Diabetes Mellitus', 'condicion', 'general', 'Enfermedad metabólica'],
            ['HISTORIA CLINICA', 'Historia Clínica', 'documento', 'general', 'Registro médico del paciente'],
            ['ANTECEDENTES', 'Antecedentes Médicos', 'documento', 'general', 'Historia médica previa'],
            ['ALERGIA', 'Alergia', 'condicion', 'general', 'Reacción alérgica'],
            ['MEDICAMENTO', 'Medicamento', 'tratamiento', 'general', 'Fármaco prescrito'],
            ['DOSIS', 'Dosis', 'tratamiento', 'general', 'Cantidad de medicamento'],
            ['FRECUENCIA', 'Frecuencia', 'tratamiento', 'general', 'Con qué frecuencia tomar'],
            ['CONTROL', 'Control Médico', 'seguimiento', 'general', 'Cita de seguimiento'],
        ];

        foreach ($abreviaturas as $abrev) {
            $this->insert('{{%abreviaturas_medicas}}', [
                'abreviatura' => $abrev[0],
                'expansion_completa' => $abrev[1],
                'categoria' => $abrev[2],
                'especialidad' => $abrev[3],
                'contexto' => $abrev[4],
                'frecuencia_uso' => 0,
                'activo' => 1,
            ]);
        }
    }
}
