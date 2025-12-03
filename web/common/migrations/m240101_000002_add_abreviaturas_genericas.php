<?php

use yii\db\Migration;

/**
 * Agregar abreviaturas genéricas del lenguaje común
 */
class m240101_000002_add_abreviaturas_genericas extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Insertar abreviaturas genéricas del lenguaje común
        $abreviaturasGenericas = [
            // Títulos y referencias personales
            ['Pte', 'Paciente', 'general', null, 'Referencia al paciente en contexto médico'],
            ['Dr', 'Doctor', 'general', null, 'Título médico masculino'],
            ['Dra', 'Doctora', 'general', null, 'Título médico femenino'],
            ['Sra', 'Señora', 'general', null, 'Título de cortesía femenino'],
            ['Sr', 'Señor', 'general', null, 'Título de cortesía masculino'],
            ['Prof', 'Profesor', 'general', null, 'Título académico'],
            ['Profa', 'Profesora', 'general', null, 'Título académico femenino'],
            ['Lic', 'Licenciado', 'general', null, 'Título universitario'],
            ['Licda', 'Licenciada', 'general', null, 'Título universitario femenino'],
            
            // Referencias y citas
            ['Ref', 'Referencia', 'general', null, 'Referencia o cita'],
            ['Cita', 'Cita Médica', 'general', null, 'Cita médica programada'],
            ['Ctrl', 'Control', 'general', null, 'Control médico'],
            ['Seguimiento', 'Seguimiento Médico', 'general', null, 'Seguimiento de tratamiento'],
            
            // Observaciones y notas
            ['Obs', 'Observación', 'general', null, 'Nota observacional'],
            ['Nota', 'Nota Médica', 'general', null, 'Nota clínica'],
            ['Comentario', 'Comentario Médico', 'general', null, 'Comentario clínico'],
            ['Aclaración', 'Aclaración', 'general', null, 'Aclaración médica'],
            
            // Diagnósticos y tratamientos
            ['Dx', 'Diagnóstico', 'general', null, 'Diagnóstico médico'],
            ['Dx Diferencial', 'Diagnóstico Diferencial', 'general', null, 'Diagnóstico diferencial'],
            ['Rx', 'Receta', 'general', null, 'Prescripción médica'],
            ['Trat', 'Tratamiento', 'general', null, 'Tratamiento médico'],
            ['Terapia', 'Terapia', 'general', null, 'Terapia médica'],
            
            // Tiempo y fechas
            ['Hoy', 'Hoy', 'general', null, 'Fecha actual'],
            ['Ayer', 'Ayer', 'general', null, 'Día anterior'],
            ['Mañana', 'Mañana', 'general', null, 'Día siguiente'],
            ['Sem', 'Semana', 'general', null, 'Período de una semana'],
            ['Mes', 'Mes', 'general', null, 'Período de un mes'],
            ['Año', 'Año', 'general', null, 'Período de un año'],
            
            // Ubicaciones y direcciones
            ['Dir', 'Dirección', 'general', null, 'Dirección física'],
            ['Tel', 'Teléfono', 'general', null, 'Número telefónico'],
            ['Email', 'Correo Electrónico', 'general', null, 'Dirección de correo'],
            ['Casa', 'Domicilio', 'general', null, 'Dirección domiciliaria'],
            ['Trabajo', 'Lugar de Trabajo', 'general', null, 'Dirección laboral'],
            
            // Estados y condiciones
            ['Estado', 'Estado', 'general', null, 'Estado actual'],
            ['Condición', 'Condición', 'general', null, 'Condición médica'],
            ['Situación', 'Situación', 'general', null, 'Situación actual'],
            ['Problema', 'Problema', 'general', null, 'Problema médico'],
            
            // Medidas y cantidades
            ['Cant', 'Cantidad', 'general', null, 'Cantidad o medida'],
            ['Unidad', 'Unidad', 'general', null, 'Unidad de medida'],
            ['Dosis', 'Dosis', 'general', null, 'Dosis de medicamento'],
            ['Frecuencia', 'Frecuencia', 'general', null, 'Frecuencia de administración'],
            
            // Documentos y registros
            ['Doc', 'Documento', 'general', null, 'Documento médico'],
            ['Reg', 'Registro', 'general', null, 'Registro médico'],
            ['Hist', 'Historia', 'general', null, 'Historia clínica'],
            ['Archivo', 'Archivo', 'general', null, 'Archivo médico'],
            
            // Comunicación
            ['Com', 'Comunicación', 'general', null, 'Comunicación médica'],
            ['Informe', 'Informe', 'general', null, 'Informe médico'],
            ['Reporte', 'Reporte', 'general', null, 'Reporte médico'],
            ['Resumen', 'Resumen', 'general', null, 'Resumen médico'],
            
            // Acciones médicas
            ['Eval', 'Evaluación', 'general', null, 'Evaluación médica'],
            ['Examen', 'Examen', 'general', null, 'Examen médico'],
            ['Prueba', 'Prueba', 'general', null, 'Prueba médica'],
            ['Test', 'Test', 'general', null, 'Test médico'],
            ['Análisis', 'Análisis', 'general', null, 'Análisis médico'],
            
            // Resultados
            ['Resultado', 'Resultado', 'general', null, 'Resultado médico'],
            ['Hallazgo', 'Hallazgo', 'general', null, 'Hallazgo médico'],
            ['Conclusión', 'Conclusión', 'general', null, 'Conclusión médica'],
            ['Recomendación', 'Recomendación', 'general', null, 'Recomendación médica'],
        ];

        foreach ($abreviaturasGenericas as $abrev) {
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

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Eliminar abreviaturas genéricas
        $this->delete('{{%abreviaturas_medicas}}', ['categoria' => 'general']);
    }
}
