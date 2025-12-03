<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Migración para enriquecer el diccionario ortográfico con términos médicos
 * organizados por especialidad y categoría, incluyendo correcciones ortográficas comunes
 */
class m240101_000008_enriquecer_diccionario_terminos_medicos extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $terminos = $this->getTerminosMedicos();
        
        // Insertar términos uno por uno verificando duplicados
        // La clave única compuesta es: ['termino', 'tipo', 'especialidad']
        foreach ($terminos as $terminoData) {
            $termino = $terminoData['termino'];
            $tipo = $terminoData['tipo'];
            $especialidad = $terminoData['especialidad'] ?? null;
            
            // Verificar si ya existe el registro
            $exists = (new Query())
                ->from('{{%diccionario_ortografico}}')
                ->where([
                    'termino' => $termino,
                    'tipo' => $tipo,
                    'especialidad' => $especialidad
                ])
                ->exists($this->db);
            
            // Solo insertar si no existe
            if (!$exists) {
                $this->insert('{{%diccionario_ortografico}}', [
                    'termino' => $termino,
                    'correccion' => $terminoData['correccion'] ?? null,
                    'tipo' => $tipo,
                    'categoria' => $terminoData['categoria'],
                    'especialidad' => $especialidad,
                    'frecuencia' => $terminoData['frecuencia'] ?? 500,
                    'peso' => $terminoData['peso'] ?? 1.00,
                    'activo' => 1
                ]);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Eliminar solo los términos insertados por esta migración
        // Se puede hacer más específico si es necesario
        $this->delete('{{%diccionario_ortografico}}', [
            'tipo' => ['termino', 'error'],
            'categoria' => [
                'anatomia', 'sintoma', 'diagnostico', 'procedimiento', 
                'medicamento', 'examen', 'patologia', 'termino_medico',
                'lesion', 'signo', 'condicion', 'medicion'
            ]
        ]);
    }

    /**
     * Obtener todos los términos médicos organizados por especialidad
     * @return array
     */
    private function getTerminosMedicos()
    {
        $terminos = [];

        // ============================================
        // ERRORES ORTOGRÁFICOS COMUNES (deben ir primero para tener prioridad)
        // ============================================
        $erroresComunes = [
            ['termino' => 'laseracion', 'correccion' => 'laceración', 'tipo' => 'error', 'categoria' => 'termino_medico', 'frecuencia' => 100],
            ['termino' => 'diabetis', 'correccion' => 'diabetes', 'tipo' => 'error', 'categoria' => 'diagnostico', 'frecuencia' => 100],
            ['termino' => 'hipertencion', 'correccion' => 'hipertensión', 'tipo' => 'error', 'categoria' => 'diagnostico', 'frecuencia' => 100],
            ['termino' => 'hipotencion', 'correccion' => 'hipotensión', 'tipo' => 'error', 'categoria' => 'diagnostico', 'frecuencia' => 80],
            ['termino' => 'prescrivir', 'correccion' => 'prescribir', 'tipo' => 'error', 'categoria' => 'medicamento', 'frecuencia' => 80],
            ['termino' => 'sintomas', 'correccion' => 'síntomas', 'tipo' => 'error', 'categoria' => 'sintoma', 'frecuencia' => 100],
            ['termino' => 'diagnostico', 'correccion' => 'diagnóstico', 'tipo' => 'error', 'categoria' => 'diagnostico', 'frecuencia' => 100],
            ['termino' => 'prescripcion', 'correccion' => 'prescripción', 'tipo' => 'error', 'categoria' => 'medicamento', 'frecuencia' => 100],
            ['termino' => 'clinica', 'correccion' => 'clínica', 'tipo' => 'error', 'categoria' => 'termino_medico', 'frecuencia' => 100],
            ['termino' => 'medico', 'correccion' => 'médico', 'tipo' => 'error', 'categoria' => 'termino_medico', 'frecuencia' => 100],
        ];

        // ============================================
        // TÉRMINOS GENERALES (sin especialidad)
        // ============================================
        $terminosGenerales = [
            // Anatomía general
            ['termino' => 'abdomen', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 800],
            ['termino' => 'brazo', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 700],
            ['termino' => 'cabeza', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 900],
            ['termino' => 'corazon', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 850],
            ['termino' => 'corazón', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 850],
            ['termino' => 'estomago', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 750],
            ['termino' => 'estómago', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 750],
            ['termino' => 'hígado', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 700],
            ['termino' => 'higado', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 700],
            ['termino' => 'pulmon', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 800],
            ['termino' => 'pulmón', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 800],
            ['termino' => 'rinon', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 700],
            ['termino' => 'riñón', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 700],
            ['termino' => 'musculo', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 750],
            ['termino' => 'músculo', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 750],
            ['termino' => 'hueso', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 800],
            ['termino' => 'articulacion', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 700],
            ['termino' => 'articulación', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 700],
            ['termino' => 'nervio', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 750],
            ['termino' => 'vaso', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 700],
            ['termino' => 'arteria', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 750],
            ['termino' => 'vena', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 750],
            ['termino' => 'piel', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 800],
            ['termino' => 'ojo', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 850],
            ['termino' => 'oído', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 700],
            ['termino' => 'oido', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 700],
            ['termino' => 'nariz', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 750],
            ['termino' => 'boca', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 800],
            ['termino' => 'garganta', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 750],
            ['termino' => 'cuello', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 750],
            ['termino' => 'pecho', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 800],
            ['termino' => 'espalda', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 750],
            ['termino' => 'columna', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 700],
            ['termino' => 'rodilla', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 750],
            ['termino' => 'tobillo', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 700],
            ['termino' => 'muñeca', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 700],
            ['termino' => 'muneca', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 700],
            ['termino' => 'hombro', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 750],
            ['termino' => 'cadera', 'tipo' => 'termino', 'categoria' => 'anatomia', 'frecuencia' => 700],

            // Síntomas generales
            ['termino' => 'dolor', 'tipo' => 'termino', 'categoria' => 'sintoma', 'frecuencia' => 1000],
            ['termino' => 'fiebre', 'tipo' => 'termino', 'categoria' => 'sintoma', 'frecuencia' => 900],
            ['termino' => 'malestar', 'tipo' => 'termino', 'categoria' => 'sintoma', 'frecuencia' => 800],
            ['termino' => 'nauseas', 'tipo' => 'termino', 'categoria' => 'sintoma', 'frecuencia' => 750],
            ['termino' => 'náuseas', 'tipo' => 'termino', 'categoria' => 'sintoma', 'frecuencia' => 750],
            ['termino' => 'vomito', 'tipo' => 'termino', 'categoria' => 'sintoma', 'frecuencia' => 800],
            ['termino' => 'vómito', 'tipo' => 'termino', 'categoria' => 'sintoma', 'frecuencia' => 800],
            ['termino' => 'mareo', 'tipo' => 'termino', 'categoria' => 'sintoma', 'frecuencia' => 750],
            ['termino' => 'cansancio', 'tipo' => 'termino', 'categoria' => 'sintoma', 'frecuencia' => 800],
            ['termino' => 'fatiga', 'tipo' => 'termino', 'categoria' => 'sintoma', 'frecuencia' => 800],
            ['termino' => 'debilidad', 'tipo' => 'termino', 'categoria' => 'sintoma', 'frecuencia' => 750],
            ['termino' => 'inflamacion', 'tipo' => 'termino', 'categoria' => 'sintoma', 'frecuencia' => 800],
            ['termino' => 'inflamación', 'tipo' => 'termino', 'categoria' => 'sintoma', 'frecuencia' => 800],
            ['termino' => 'hinchazon', 'tipo' => 'termino', 'categoria' => 'sintoma', 'frecuencia' => 750],
            ['termino' => 'hinchazón', 'tipo' => 'termino', 'categoria' => 'sintoma', 'frecuencia' => 750],
            ['termino' => 'enrojecimiento', 'tipo' => 'termino', 'categoria' => 'sintoma', 'frecuencia' => 700],
            ['termino' => 'picazon', 'tipo' => 'termino', 'categoria' => 'sintoma', 'frecuencia' => 750],
            ['termino' => 'picazón', 'tipo' => 'termino', 'categoria' => 'sintoma', 'frecuencia' => 750],
            ['termino' => 'ardor', 'tipo' => 'termino', 'categoria' => 'sintoma', 'frecuencia' => 700],
            ['termino' => 'quemazon', 'tipo' => 'termino', 'categoria' => 'sintoma', 'frecuencia' => 700],
            ['termino' => 'quemazón', 'tipo' => 'termino', 'categoria' => 'sintoma', 'frecuencia' => 700],
            ['termino' => 'sangrado', 'tipo' => 'termino', 'categoria' => 'sintoma', 'frecuencia' => 800],
            ['termino' => 'hemorragia', 'tipo' => 'termino', 'categoria' => 'sintoma', 'frecuencia' => 750],
            ['termino' => 'tos', 'tipo' => 'termino', 'categoria' => 'sintoma', 'frecuencia' => 900],
            ['termino' => 'congestion', 'tipo' => 'termino', 'categoria' => 'sintoma', 'frecuencia' => 750],
            ['termino' => 'congestión', 'tipo' => 'termino', 'categoria' => 'sintoma', 'frecuencia' => 750],
            ['termino' => 'dificultad', 'tipo' => 'termino', 'categoria' => 'sintoma', 'frecuencia' => 800],
            ['termino' => 'dificultad respiratoria', 'tipo' => 'termino', 'categoria' => 'sintoma', 'frecuencia' => 750],

            // Diagnósticos generales
            ['termino' => 'diagnostico', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 1000],
            ['termino' => 'diagnóstico', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 1000],
            ['termino' => 'enfermedad', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 950],
            ['termino' => 'patologia', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 800],
            ['termino' => 'patología', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 800],
            ['termino' => 'condicion', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 850],
            ['termino' => 'condición', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 850],
            ['termino' => 'trastorno', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 800],
            ['termino' => 'sindrome', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 750],
            ['termino' => 'síndrome', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 750],
            ['termino' => 'infeccion', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 900],
            ['termino' => 'infección', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 900],
            ['termino' => 'trauma', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 850],
            ['termino' => 'lesion', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 900],
            ['termino' => 'lesión', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 900],
            ['termino' => 'laceracion', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 750],
            ['termino' => 'laceración', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 750],
            ['termino' => 'fractura', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 800],
            ['termino' => 'luxacion', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 700],
            ['termino' => 'luxación', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 700],
            ['termino' => 'esguince', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 750],
            ['termino' => 'contusion', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 700],
            ['termino' => 'contusión', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 700],
            ['termino' => 'herida', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 850],
            ['termino' => 'ulcera', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 700],
            ['termino' => 'úlcera', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 700],
            ['termino' => 'tumor', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 800],
            ['termino' => 'cancer', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 850],
            ['termino' => 'cáncer', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 850],
            ['termino' => 'diabetes', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 900],
            ['termino' => 'hipertension', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 900],
            ['termino' => 'hipertensión', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 900],
            ['termino' => 'hipotension', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 750],
            ['termino' => 'hipotensión', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'frecuencia' => 750],

            // Procedimientos generales
            ['termino' => 'cirugia', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'frecuencia' => 850],
            ['termino' => 'cirugía', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'frecuencia' => 850],
            ['termino' => 'operacion', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'frecuencia' => 800],
            ['termino' => 'operación', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'frecuencia' => 800],
            ['termino' => 'intervencion', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'frecuencia' => 750],
            ['termino' => 'intervención', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'frecuencia' => 750],
            ['termino' => 'sutura', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'frecuencia' => 750],
            ['termino' => 'biopsia', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'frecuencia' => 800],
            ['termino' => 'puncion', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'frecuencia' => 700],
            ['termino' => 'punción', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'frecuencia' => 700],
            ['termino' => 'inyeccion', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'frecuencia' => 850],
            ['termino' => 'inyección', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'frecuencia' => 850],
            ['termino' => 'infiltracion', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'frecuencia' => 700],
            ['termino' => 'infiltración', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'frecuencia' => 700],
            ['termino' => 'drenaje', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'frecuencia' => 700],
            ['termino' => 'curacion', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'frecuencia' => 800],
            ['termino' => 'curación', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'frecuencia' => 800],
            ['termino' => 'vendaje', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'frecuencia' => 800],
            ['termino' => 'inmovilizacion', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'frecuencia' => 700],
            ['termino' => 'inmovilización', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'frecuencia' => 700],

            // Exámenes generales
            ['termino' => 'examen', 'tipo' => 'termino', 'categoria' => 'examen', 'frecuencia' => 950],
            ['termino' => 'analisis', 'tipo' => 'termino', 'categoria' => 'examen', 'frecuencia' => 900],
            ['termino' => 'análisis', 'tipo' => 'termino', 'categoria' => 'examen', 'frecuencia' => 900],
            ['termino' => 'estudio', 'tipo' => 'termino', 'categoria' => 'examen', 'frecuencia' => 900],
            ['termino' => 'radiografia', 'tipo' => 'termino', 'categoria' => 'examen', 'frecuencia' => 850],
            ['termino' => 'radiografía', 'tipo' => 'termino', 'categoria' => 'examen', 'frecuencia' => 850],
            ['termino' => 'ecografia', 'tipo' => 'termino', 'categoria' => 'examen', 'frecuencia' => 800],
            ['termino' => 'ecografía', 'tipo' => 'termino', 'categoria' => 'examen', 'frecuencia' => 800],
            ['termino' => 'tomografia', 'tipo' => 'termino', 'categoria' => 'examen', 'frecuencia' => 750],
            ['termino' => 'tomografía', 'tipo' => 'termino', 'categoria' => 'examen', 'frecuencia' => 750],
            ['termino' => 'resonancia', 'tipo' => 'termino', 'categoria' => 'examen', 'frecuencia' => 750],
            ['termino' => 'laboratorio', 'tipo' => 'termino', 'categoria' => 'examen', 'frecuencia' => 900],
            ['termino' => 'sangre', 'tipo' => 'termino', 'categoria' => 'examen', 'frecuencia' => 950],
            ['termino' => 'orina', 'tipo' => 'termino', 'categoria' => 'examen', 'frecuencia' => 850],
            ['termino' => 'heces', 'tipo' => 'termino', 'categoria' => 'examen', 'frecuencia' => 700],

            // Medicamentos generales
            ['termino' => 'medicamento', 'tipo' => 'termino', 'categoria' => 'medicamento', 'frecuencia' => 1000],
            ['termino' => 'farmaco', 'tipo' => 'termino', 'categoria' => 'medicamento', 'frecuencia' => 800],
            ['termino' => 'fármaco', 'tipo' => 'termino', 'categoria' => 'medicamento', 'frecuencia' => 800],
            ['termino' => 'droga', 'tipo' => 'termino', 'categoria' => 'medicamento', 'frecuencia' => 750],
            ['termino' => 'antibiotico', 'tipo' => 'termino', 'categoria' => 'medicamento', 'frecuencia' => 900],
            ['termino' => 'antibiótico', 'tipo' => 'termino', 'categoria' => 'medicamento', 'frecuencia' => 900],
            ['termino' => 'analgesico', 'tipo' => 'termino', 'categoria' => 'medicamento', 'frecuencia' => 850],
            ['termino' => 'analgésico', 'tipo' => 'termino', 'categoria' => 'medicamento', 'frecuencia' => 850],
            ['termino' => 'antiinflamatorio', 'tipo' => 'termino', 'categoria' => 'medicamento', 'frecuencia' => 850],
            ['termino' => 'antipiretico', 'tipo' => 'termino', 'categoria' => 'medicamento', 'frecuencia' => 750],
            ['termino' => 'antipirético', 'tipo' => 'termino', 'categoria' => 'medicamento', 'frecuencia' => 750],
            ['termino' => 'dosis', 'tipo' => 'termino', 'categoria' => 'medicamento', 'frecuencia' => 900],
            ['termino' => 'posologia', 'tipo' => 'termino', 'categoria' => 'medicamento', 'frecuencia' => 700],
            ['termino' => 'posología', 'tipo' => 'termino', 'categoria' => 'medicamento', 'frecuencia' => 700],
            ['termino' => 'prescripcion', 'tipo' => 'termino', 'categoria' => 'medicamento', 'frecuencia' => 850],
            ['termino' => 'prescripción', 'tipo' => 'termino', 'categoria' => 'medicamento', 'frecuencia' => 850],
        ];

        // ============================================
        // OFTALMOLOGÍA
        // ============================================
        $oftalmologia = [
            // Anatomía ocular
            ['termino' => 'cornea', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'oftalmologia', 'frecuencia' => 900],
            ['termino' => 'córnea', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'oftalmologia', 'frecuencia' => 900],
            ['termino' => 'iris', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'oftalmologia', 'frecuencia' => 850],
            ['termino' => 'pupila', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'oftalmologia', 'frecuencia' => 900],
            ['termino' => 'retina', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'oftalmologia', 'frecuencia' => 900],
            ['termino' => 'cristalino', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'oftalmologia', 'frecuencia' => 850],
            ['termino' => 'humor', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'oftalmologia', 'frecuencia' => 800],
            ['termino' => 'vitreo', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'oftalmologia', 'frecuencia' => 800],
            ['termino' => 'vítreo', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'oftalmologia', 'frecuencia' => 800],
            ['termino' => 'conjuntiva', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'oftalmologia', 'frecuencia' => 850],
            ['termino' => 'esclera', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'oftalmologia', 'frecuencia' => 800],
            ['termino' => 'macula', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'oftalmologia', 'frecuencia' => 850],
            ['termino' => 'mácula', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'oftalmologia', 'frecuencia' => 850],
            ['termino' => 'nervio optico', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'oftalmologia', 'frecuencia' => 850],
            ['termino' => 'nervio óptico', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'oftalmologia', 'frecuencia' => 850],
            ['termino' => 'parpado', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'oftalmologia', 'frecuencia' => 800],
            ['termino' => 'párpado', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'oftalmologia', 'frecuencia' => 800],
            ['termino' => 'lagrima', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'oftalmologia', 'frecuencia' => 750],
            ['termino' => 'lágrima', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'oftalmologia', 'frecuencia' => 750],

            // Síntomas oftalmológicos
            ['termino' => 'vision', 'tipo' => 'termino', 'categoria' => 'sintoma', 'especialidad' => 'oftalmologia', 'frecuencia' => 950],
            ['termino' => 'visión', 'tipo' => 'termino', 'categoria' => 'sintoma', 'especialidad' => 'oftalmologia', 'frecuencia' => 950],
            ['termino' => 'agudeza visual', 'tipo' => 'termino', 'categoria' => 'sintoma', 'especialidad' => 'oftalmologia', 'frecuencia' => 900],
            ['termino' => 'ceguera', 'tipo' => 'termino', 'categoria' => 'sintoma', 'especialidad' => 'oftalmologia', 'frecuencia' => 800],
            ['termino' => 'vision borrosa', 'tipo' => 'termino', 'categoria' => 'sintoma', 'especialidad' => 'oftalmologia', 'frecuencia' => 900],
            ['termino' => 'visión borrosa', 'tipo' => 'termino', 'categoria' => 'sintoma', 'especialidad' => 'oftalmologia', 'frecuencia' => 900],
            ['termino' => 'vision doble', 'tipo' => 'termino', 'categoria' => 'sintoma', 'especialidad' => 'oftalmologia', 'frecuencia' => 800],
            ['termino' => 'visión doble', 'tipo' => 'termino', 'categoria' => 'sintoma', 'especialidad' => 'oftalmologia', 'frecuencia' => 800],
            ['termino' => 'diplopia', 'tipo' => 'termino', 'categoria' => 'sintoma', 'especialidad' => 'oftalmologia', 'frecuencia' => 750],
            ['termino' => 'fotofobia', 'tipo' => 'termino', 'categoria' => 'sintoma', 'especialidad' => 'oftalmologia', 'frecuencia' => 750],
            ['termino' => 'lagrimeo', 'tipo' => 'termino', 'categoria' => 'sintoma', 'especialidad' => 'oftalmologia', 'frecuencia' => 800],
            ['termino' => 'secrecion', 'tipo' => 'termino', 'categoria' => 'sintoma', 'especialidad' => 'oftalmologia', 'frecuencia' => 750],
            ['termino' => 'secreción', 'tipo' => 'termino', 'categoria' => 'sintoma', 'especialidad' => 'oftalmologia', 'frecuencia' => 750],
            ['termino' => 'ojo rojo', 'tipo' => 'termino', 'categoria' => 'sintoma', 'especialidad' => 'oftalmologia', 'frecuencia' => 850],
            ['termino' => 'inyeccion conjuntival', 'tipo' => 'termino', 'categoria' => 'signo', 'especialidad' => 'oftalmologia', 'frecuencia' => 800],
            ['termino' => 'inyección conjuntival', 'tipo' => 'termino', 'categoria' => 'signo', 'especialidad' => 'oftalmologia', 'frecuencia' => 800],
            ['termino' => 'dolor ocular', 'tipo' => 'termino', 'categoria' => 'sintoma', 'especialidad' => 'oftalmologia', 'frecuencia' => 850],

            // Diagnósticos oftalmológicos
            ['termino' => 'catarata', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'oftalmologia', 'frecuencia' => 900],
            ['termino' => 'glaucoma', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'oftalmologia', 'frecuencia' => 900],
            ['termino' => 'conjuntivitis', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'oftalmologia', 'frecuencia' => 950],
            ['termino' => 'queratitis', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'oftalmologia', 'frecuencia' => 800],
            ['termino' => 'uveitis', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'oftalmologia', 'frecuencia' => 750],
            ['termino' => 'retinopatia', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'oftalmologia', 'frecuencia' => 800],
            ['termino' => 'retinopatía', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'oftalmologia', 'frecuencia' => 800],
            ['termino' => 'desprendimiento retina', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'oftalmologia', 'frecuencia' => 800],
            ['termino' => 'desprendimiento de retina', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'oftalmologia', 'frecuencia' => 800],
            ['termino' => 'degeneracion macular', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'oftalmologia', 'frecuencia' => 800],
            ['termino' => 'degeneración macular', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'oftalmologia', 'frecuencia' => 800],
            ['termino' => 'estrabismo', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'oftalmologia', 'frecuencia' => 750],
            ['termino' => 'ambliopia', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'oftalmologia', 'frecuencia' => 700],
            ['termino' => 'ambliopía', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'oftalmologia', 'frecuencia' => 700],
            ['termino' => 'miopia', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'oftalmologia', 'frecuencia' => 900],
            ['termino' => 'miopía', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'oftalmologia', 'frecuencia' => 900],
            ['termino' => 'hipermetropia', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'oftalmologia', 'frecuencia' => 850],
            ['termino' => 'hipermetropía', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'oftalmologia', 'frecuencia' => 850],
            ['termino' => 'astigmatismo', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'oftalmologia', 'frecuencia' => 900],
            ['termino' => 'presbicia', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'oftalmologia', 'frecuencia' => 850],
            ['termino' => 'laceracion corneal', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'oftalmologia', 'frecuencia' => 750],
            ['termino' => 'laceración corneal', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'oftalmologia', 'frecuencia' => 750],
            ['termino' => 'ulcera corneal', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'oftalmologia', 'frecuencia' => 800],
            ['termino' => 'úlcera corneal', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'oftalmologia', 'frecuencia' => 800],
            ['termino' => 'erosion corneal', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'oftalmologia', 'frecuencia' => 750],
            ['termino' => 'erosión corneal', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'oftalmologia', 'frecuencia' => 750],
            ['termino' => 'cuerpo extraño', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'oftalmologia', 'frecuencia' => 800],
            ['termino' => 'trauma ocular', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'oftalmologia', 'frecuencia' => 850],
            ['termino' => 'hifema', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'oftalmologia', 'frecuencia' => 700],
            ['termino' => 'hipema', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'oftalmologia', 'frecuencia' => 700],

            // Procedimientos oftalmológicos
            ['termino' => 'oftalmoscopia', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'oftalmologia', 'frecuencia' => 850],
            ['termino' => 'tonometria', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'oftalmologia', 'frecuencia' => 800],
            ['termino' => 'tonometría', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'oftalmologia', 'frecuencia' => 800],
            ['termino' => 'campimetria', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'oftalmologia', 'frecuencia' => 750],
            ['termino' => 'campimetría', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'oftalmologia', 'frecuencia' => 750],
            ['termino' => 'refraccion', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'oftalmologia', 'frecuencia' => 900],
            ['termino' => 'refracción', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'oftalmologia', 'frecuencia' => 900],
            ['termino' => 'biomicroscopia', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'oftalmologia', 'frecuencia' => 800],
            ['termino' => 'fluoresceinografia', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'oftalmologia', 'frecuencia' => 700],
            ['termino' => 'fluoresceinografía', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'oftalmologia', 'frecuencia' => 700],
            ['termino' => 'facoemulsificacion', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'oftalmologia', 'frecuencia' => 750],
            ['termino' => 'facoemulsificación', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'oftalmologia', 'frecuencia' => 750],
            ['termino' => 'vitrectomia', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'oftalmologia', 'frecuencia' => 700],
            ['termino' => 'vitrectomía', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'oftalmologia', 'frecuencia' => 700],
            ['termino' => 'laser', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'oftalmologia', 'frecuencia' => 850],
            ['termino' => 'láser', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'oftalmologia', 'frecuencia' => 850],
            ['termino' => 'fotocoagulacion', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'oftalmologia', 'frecuencia' => 750],
            ['termino' => 'fotocoagulación', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'oftalmologia', 'frecuencia' => 750],

            // Signos y mediciones oftalmológicas
            ['termino' => 'tyndall', 'tipo' => 'termino', 'categoria' => 'signo', 'especialidad' => 'oftalmologia', 'frecuencia' => 800],
            ['termino' => 'caf', 'tipo' => 'termino', 'categoria' => 'signo', 'especialidad' => 'oftalmologia', 'frecuencia' => 800],
            ['termino' => 'camara anterior', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'oftalmologia', 'frecuencia' => 850],
            ['termino' => 'cámara anterior', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'oftalmologia', 'frecuencia' => 850],
            ['termino' => 'pupila isocorica', 'tipo' => 'termino', 'categoria' => 'signo', 'especialidad' => 'oftalmologia', 'frecuencia' => 800],
            ['termino' => 'isocorica', 'tipo' => 'termino', 'categoria' => 'signo', 'especialidad' => 'oftalmologia', 'frecuencia' => 750],
            ['termino' => 'reactiva', 'tipo' => 'termino', 'categoria' => 'signo', 'especialidad' => 'oftalmologia', 'frecuencia' => 850],
            ['termino' => 'pio', 'tipo' => 'termino', 'categoria' => 'medicion', 'especialidad' => 'oftalmologia', 'frecuencia' => 900],
            ['termino' => 'presion intraocular', 'tipo' => 'termino', 'categoria' => 'medicion', 'especialidad' => 'oftalmologia', 'frecuencia' => 900],
            ['termino' => 'presión intraocular', 'tipo' => 'termino', 'categoria' => 'medicion', 'especialidad' => 'oftalmologia', 'frecuencia' => 900],
            ['termino' => 'av', 'tipo' => 'termino', 'categoria' => 'medicion', 'especialidad' => 'oftalmologia', 'frecuencia' => 950],
            ['termino' => 'agudeza visual', 'tipo' => 'termino', 'categoria' => 'medicion', 'especialidad' => 'oftalmologia', 'frecuencia' => 950],
            ['termino' => 'od', 'tipo' => 'termino', 'categoria' => 'termino_medico', 'especialidad' => 'oftalmologia', 'frecuencia' => 1000],
            ['termino' => 'oi', 'tipo' => 'termino', 'categoria' => 'termino_medico', 'especialidad' => 'oftalmologia', 'frecuencia' => 1000],
            ['termino' => 'ojo derecho', 'tipo' => 'termino', 'categoria' => 'termino_medico', 'especialidad' => 'oftalmologia', 'frecuencia' => 950],
            ['termino' => 'ojo izquierdo', 'tipo' => 'termino', 'categoria' => 'termino_medico', 'especialidad' => 'oftalmologia', 'frecuencia' => 950],
        ];

        // ============================================
        // CARDIOLOGÍA
        // ============================================
        $cardiologia = [
            // Anatomía cardiovascular
            ['termino' => 'auricula', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'cardiologia', 'frecuencia' => 850],
            ['termino' => 'aurícula', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'cardiologia', 'frecuencia' => 850],
            ['termino' => 'ventriculo', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'cardiologia', 'frecuencia' => 850],
            ['termino' => 'ventrículo', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'cardiologia', 'frecuencia' => 850],
            ['termino' => 'valvula', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'cardiologia', 'frecuencia' => 900],
            ['termino' => 'válvula', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'cardiologia', 'frecuencia' => 900],
            ['termino' => 'valvula mitral', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'cardiologia', 'frecuencia' => 800],
            ['termino' => 'válvula mitral', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'cardiologia', 'frecuencia' => 800],
            ['termino' => 'valvula aortica', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'cardiologia', 'frecuencia' => 800],
            ['termino' => 'válvula aórtica', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'cardiologia', 'frecuencia' => 800],
            ['termino' => 'miocardio', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'cardiologia', 'frecuencia' => 850],
            ['termino' => 'pericardio', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'cardiologia', 'frecuencia' => 800],
            ['termino' => 'endocardio', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'cardiologia', 'frecuencia' => 750],
            ['termino' => 'aorta', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'cardiologia', 'frecuencia' => 850],
            ['termino' => 'vena cava', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'cardiologia', 'frecuencia' => 800],

            // Síntomas cardiovasculares
            ['termino' => 'dolor toracico', 'tipo' => 'termino', 'categoria' => 'sintoma', 'especialidad' => 'cardiologia', 'frecuencia' => 950],
            ['termino' => 'dolor torácico', 'tipo' => 'termino', 'categoria' => 'sintoma', 'especialidad' => 'cardiologia', 'frecuencia' => 950],
            ['termino' => 'angina', 'tipo' => 'termino', 'categoria' => 'sintoma', 'especialidad' => 'cardiologia', 'frecuencia' => 900],
            ['termino' => 'angina de pecho', 'tipo' => 'termino', 'categoria' => 'sintoma', 'especialidad' => 'cardiologia', 'frecuencia' => 900],
            ['termino' => 'palpitaciones', 'tipo' => 'termino', 'categoria' => 'sintoma', 'especialidad' => 'cardiologia', 'frecuencia' => 900],
            ['termino' => 'taquicardia', 'tipo' => 'termino', 'categoria' => 'sintoma', 'especialidad' => 'cardiologia', 'frecuencia' => 900],
            ['termino' => 'bradicardia', 'tipo' => 'termino', 'categoria' => 'sintoma', 'especialidad' => 'cardiologia', 'frecuencia' => 800],
            ['termino' => 'arritmia', 'tipo' => 'termino', 'categoria' => 'sintoma', 'especialidad' => 'cardiologia', 'frecuencia' => 900],
            ['termino' => 'disnea', 'tipo' => 'termino', 'categoria' => 'sintoma', 'especialidad' => 'cardiologia', 'frecuencia' => 900],
            ['termino' => 'disnea de esfuerzo', 'tipo' => 'termino', 'categoria' => 'sintoma', 'especialidad' => 'cardiologia', 'frecuencia' => 850],
            ['termino' => 'ortopnea', 'tipo' => 'termino', 'categoria' => 'sintoma', 'especialidad' => 'cardiologia', 'frecuencia' => 750],
            ['termino' => 'edema', 'tipo' => 'termino', 'categoria' => 'sintoma', 'especialidad' => 'cardiologia', 'frecuencia' => 900],
            ['termino' => 'edema periferico', 'tipo' => 'termino', 'categoria' => 'sintoma', 'especialidad' => 'cardiologia', 'frecuencia' => 850],
            ['termino' => 'edema periférico', 'tipo' => 'termino', 'categoria' => 'sintoma', 'especialidad' => 'cardiologia', 'frecuencia' => 850],
            ['termino' => 'sincope', 'tipo' => 'termino', 'categoria' => 'sintoma', 'especialidad' => 'cardiologia', 'frecuencia' => 800],
            ['termino' => 'síncope', 'tipo' => 'termino', 'categoria' => 'sintoma', 'especialidad' => 'cardiologia', 'frecuencia' => 800],

            // Diagnósticos cardiovasculares
            ['termino' => 'infarto', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'cardiologia', 'frecuencia' => 950],
            ['termino' => 'infarto agudo de miocardio', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'cardiologia', 'frecuencia' => 900],
            ['termino' => 'iam', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'cardiologia', 'frecuencia' => 900],
            ['termino' => 'insuficiencia cardiaca', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'cardiologia', 'frecuencia' => 900],
            ['termino' => 'insuficiencia cardíaca', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'cardiologia', 'frecuencia' => 900],
            ['termino' => 'cardiopatia', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'cardiologia', 'frecuencia' => 850],
            ['termino' => 'cardiopatía', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'cardiologia', 'frecuencia' => 850],
            ['termino' => 'cardiopatia isquemica', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'cardiologia', 'frecuencia' => 850],
            ['termino' => 'cardiopatía isquémica', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'cardiologia', 'frecuencia' => 850],
            ['termino' => 'fibrilacion auricular', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'cardiologia', 'frecuencia' => 850],
            ['termino' => 'fibrilación auricular', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'cardiologia', 'frecuencia' => 850],
            ['termino' => 'valvulopatia', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'cardiologia', 'frecuencia' => 800],
            ['termino' => 'valvulopatía', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'cardiologia', 'frecuencia' => 800],
            ['termino' => 'estenosis', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'cardiologia', 'frecuencia' => 800],
            ['termino' => 'insuficiencia valvular', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'cardiologia', 'frecuencia' => 800],
            ['termino' => 'miocardiopatia', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'cardiologia', 'frecuencia' => 750],
            ['termino' => 'miocardiopatía', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'cardiologia', 'frecuencia' => 750],
            ['termino' => 'pericarditis', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'cardiologia', 'frecuencia' => 750],
            ['termino' => 'endocarditis', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'cardiologia', 'frecuencia' => 750],

            // Procedimientos cardiovasculares
            ['termino' => 'electrocardiograma', 'tipo' => 'termino', 'categoria' => 'examen', 'especialidad' => 'cardiologia', 'frecuencia' => 950],
            ['termino' => 'ecg', 'tipo' => 'termino', 'categoria' => 'examen', 'especialidad' => 'cardiologia', 'frecuencia' => 950],
            ['termino' => 'ecocardiograma', 'tipo' => 'termino', 'categoria' => 'examen', 'especialidad' => 'cardiologia', 'frecuencia' => 900],
            ['termino' => 'eco', 'tipo' => 'termino', 'categoria' => 'examen', 'especialidad' => 'cardiologia', 'frecuencia' => 900],
            ['termino' => 'holter', 'tipo' => 'termino', 'categoria' => 'examen', 'especialidad' => 'cardiologia', 'frecuencia' => 850],
            ['termino' => 'ergometria', 'tipo' => 'termino', 'categoria' => 'examen', 'especialidad' => 'cardiologia', 'frecuencia' => 800],
            ['termino' => 'ergometría', 'tipo' => 'termino', 'categoria' => 'examen', 'especialidad' => 'cardiologia', 'frecuencia' => 800],
            ['termino' => 'cateterismo', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'cardiologia', 'frecuencia' => 850],
            ['termino' => 'cateterismo cardiaco', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'cardiologia', 'frecuencia' => 850],
            ['termino' => 'cateterismo cardíaco', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'cardiologia', 'frecuencia' => 850],
            ['termino' => 'angioplastia', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'cardiologia', 'frecuencia' => 850],
            ['termino' => 'bypass', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'cardiologia', 'frecuencia' => 800],
            ['termino' => 'marcapasos', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'cardiologia', 'frecuencia' => 800],
        ];

        // ============================================
        // ORTOPEDIA Y TRAUMATOLOGÍA
        // ============================================
        $ortopedia = [
            // Anatomía musculoesquelética
            ['termino' => 'ligamento', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'ortopedia', 'frecuencia' => 850],
            ['termino' => 'tendon', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'ortopedia', 'frecuencia' => 850],
            ['termino' => 'tendón', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'ortopedia', 'frecuencia' => 850],
            ['termino' => 'cartilago', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'ortopedia', 'frecuencia' => 800],
            ['termino' => 'cartílago', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'ortopedia', 'frecuencia' => 800],
            ['termino' => 'menisco', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'ortopedia', 'frecuencia' => 800],
            ['termino' => 'vertebra', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'ortopedia', 'frecuencia' => 850],
            ['termino' => 'vértebra', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'ortopedia', 'frecuencia' => 850],
            ['termino' => 'disco intervertebral', 'tipo' => 'termino', 'categoria' => 'anatomia', 'especialidad' => 'ortopedia', 'frecuencia' => 800],

            // Diagnósticos ortopédicos
            ['termino' => 'fractura desplazada', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'ortopedia', 'frecuencia' => 850],
            ['termino' => 'fractura no desplazada', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'ortopedia', 'frecuencia' => 850],
            ['termino' => 'fractura conminuta', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'ortopedia', 'frecuencia' => 750],
            ['termino' => 'fractura conminuta', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'ortopedia', 'frecuencia' => 750],
            ['termino' => 'subluxacion', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'ortopedia', 'frecuencia' => 750],
            ['termino' => 'subluxación', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'ortopedia', 'frecuencia' => 750],
            ['termino' => 'esguince grado', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'ortopedia', 'frecuencia' => 850],
            ['termino' => 'tendinitis', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'ortopedia', 'frecuencia' => 850],
            ['termino' => 'tendinosis', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'ortopedia', 'frecuencia' => 750],
            ['termino' => 'bursitis', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'ortopedia', 'frecuencia' => 800],
            ['termino' => 'artrosis', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'ortopedia', 'frecuencia' => 900],
            ['termino' => 'artritis', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'ortopedia', 'frecuencia' => 900],
            ['termino' => 'artritis reumatoide', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'ortopedia', 'frecuencia' => 850],
            ['termino' => 'hernia discal', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'ortopedia', 'frecuencia' => 850],
            ['termino' => 'hernia de disco', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'ortopedia', 'frecuencia' => 850],
            ['termino' => 'ciatica', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'ortopedia', 'frecuencia' => 800],
            ['termino' => 'ciática', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'ortopedia', 'frecuencia' => 800],
            ['termino' => 'lumbalgia', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'ortopedia', 'frecuencia' => 900],
            ['termino' => 'cervicalgia', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'ortopedia', 'frecuencia' => 850],
            ['termino' => 'dorsalgia', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'ortopedia', 'frecuencia' => 800],
            ['termino' => 'rotura', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'ortopedia', 'frecuencia' => 850],
            ['termino' => 'rotura de menisco', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'ortopedia', 'frecuencia' => 800],
            ['termino' => 'rotura de ligamento', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'ortopedia', 'frecuencia' => 800],
            ['termino' => 'rotura de tendon', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'ortopedia', 'frecuencia' => 800],
            ['termino' => 'rotura de tendón', 'tipo' => 'termino', 'categoria' => 'diagnostico', 'especialidad' => 'ortopedia', 'frecuencia' => 800],

            // Procedimientos ortopédicos
            ['termino' => 'reduccion', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'ortopedia', 'frecuencia' => 850],
            ['termino' => 'reducción', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'ortopedia', 'frecuencia' => 850],
            ['termino' => 'yeso', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'ortopedia', 'frecuencia' => 900],
            ['termino' => 'ferula', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'ortopedia', 'frecuencia' => 850],
            ['termino' => 'férula', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'ortopedia', 'frecuencia' => 850],
            ['termino' => 'artroscopia', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'ortopedia', 'frecuencia' => 800],
            ['termino' => 'osteosintesis', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'ortopedia', 'frecuencia' => 800],
            ['termino' => 'osteosíntesis', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'ortopedia', 'frecuencia' => 800],
            ['termino' => 'artroplastia', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'ortopedia', 'frecuencia' => 800],
            ['termino' => 'protesis', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'ortopedia', 'frecuencia' => 800],
            ['termino' => 'prótesis', 'tipo' => 'termino', 'categoria' => 'procedimiento', 'especialidad' => 'ortopedia', 'frecuencia' => 800],
        ];

        // Combinar todos los términos (errores primero para tener prioridad)
        return array_merge(
            $erroresComunes,
            $terminosGenerales,
            $oftalmologia,
            $cardiologia,
            $ortopedia
        );
    }
}

