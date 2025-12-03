<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * Migración para agregar stop words (palabras de parada) al diccionario ortográfico
 * Las stop words son palabras comunes que no deben ser corregidas ni expandidas
 */
class m240101_000009_agregar_stop_words_diccionario extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $stopWords = $this->getStopWords();
        
        // Insertar stop words una por una verificando duplicados
        foreach ($stopWords as $stopWordData) {
            $termino = $stopWordData['termino'];
            $tipo = $stopWordData['tipo'];
            
            // Verificar si ya existe el registro
            $exists = (new Query())
                ->from('{{%diccionario_ortografico}}')
                ->where([
                    'termino' => $termino,
                    'tipo' => $tipo
                ])
                ->exists($this->db);
            
            // Solo insertar si no existe
            if (!$exists) {
                $this->insert('{{%diccionario_ortografico}}', [
                    'termino' => $termino,
                    'correccion' => $stopWordData['correccion'] ?? null,
                    'tipo' => $tipo,
                    'categoria' => $stopWordData['categoria'],
                    'especialidad' => $stopWordData['especialidad'] ?? null,
                    'frecuencia' => $stopWordData['frecuencia'] ?? 10000,
                    'peso' => $stopWordData['peso'] ?? 1.00,
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
        // Eliminar solo las stop words insertadas por esta migración
        $this->delete('{{%diccionario_ortografico}}', [
            'tipo' => 'stopword',
            'categoria' => [
                'articulo', 'preposicion', 'conjuncion', 'pronombre',
                'adverbio', 'numero', 'termino_medico_comun', 'verbo_auxiliar'
            ]
        ]);
    }

    /**
     * Obtener todas las stop words organizadas por categoría
     * @return array
     */
    private function getStopWords()
    {
        return [
            // Artículos
            ['termino' => 'el', 'tipo' => 'stopword', 'categoria' => 'articulo', 'frecuencia' => 10000],
            ['termino' => 'la', 'tipo' => 'stopword', 'categoria' => 'articulo', 'frecuencia' => 10000],
            ['termino' => 'los', 'tipo' => 'stopword', 'categoria' => 'articulo', 'frecuencia' => 10000],
            ['termino' => 'las', 'tipo' => 'stopword', 'categoria' => 'articulo', 'frecuencia' => 10000],
            ['termino' => 'un', 'tipo' => 'stopword', 'categoria' => 'articulo', 'frecuencia' => 10000],
            ['termino' => 'una', 'tipo' => 'stopword', 'categoria' => 'articulo', 'frecuencia' => 10000],
            ['termino' => 'unos', 'tipo' => 'stopword', 'categoria' => 'articulo', 'frecuencia' => 10000],
            ['termino' => 'unas', 'tipo' => 'stopword', 'categoria' => 'articulo', 'frecuencia' => 10000],
            
            // Preposiciones
            ['termino' => 'de', 'tipo' => 'stopword', 'categoria' => 'preposicion', 'frecuencia' => 10000],
            ['termino' => 'del', 'tipo' => 'stopword', 'categoria' => 'preposicion', 'frecuencia' => 10000],
            ['termino' => 'a', 'tipo' => 'stopword', 'categoria' => 'preposicion', 'frecuencia' => 10000],
            ['termino' => 'al', 'tipo' => 'stopword', 'categoria' => 'preposicion', 'frecuencia' => 10000],
            ['termino' => 'en', 'tipo' => 'stopword', 'categoria' => 'preposicion', 'frecuencia' => 10000],
            ['termino' => 'con', 'tipo' => 'stopword', 'categoria' => 'preposicion', 'frecuencia' => 10000],
            ['termino' => 'por', 'tipo' => 'stopword', 'categoria' => 'preposicion', 'frecuencia' => 10000],
            ['termino' => 'para', 'tipo' => 'stopword', 'categoria' => 'preposicion', 'frecuencia' => 10000],
            ['termino' => 'sin', 'tipo' => 'stopword', 'categoria' => 'preposicion', 'frecuencia' => 10000],
            ['termino' => 'sobre', 'tipo' => 'stopword', 'categoria' => 'preposicion', 'frecuencia' => 10000],
            ['termino' => 'bajo', 'tipo' => 'stopword', 'categoria' => 'preposicion', 'frecuencia' => 10000],
            ['termino' => 'entre', 'tipo' => 'stopword', 'categoria' => 'preposicion', 'frecuencia' => 10000],
            
            // Conjunciones
            ['termino' => 'y', 'tipo' => 'stopword', 'categoria' => 'conjuncion', 'frecuencia' => 10000],
            ['termino' => 'o', 'tipo' => 'stopword', 'categoria' => 'conjuncion', 'frecuencia' => 10000],
            ['termino' => 'pero', 'tipo' => 'stopword', 'categoria' => 'conjuncion', 'frecuencia' => 10000],
            ['termino' => 'aunque', 'tipo' => 'stopword', 'categoria' => 'conjuncion', 'frecuencia' => 10000],
            ['termino' => 'mientras', 'tipo' => 'stopword', 'categoria' => 'conjuncion', 'frecuencia' => 10000],
            ['termino' => 'cuando', 'tipo' => 'stopword', 'categoria' => 'conjuncion', 'frecuencia' => 10000],
            ['termino' => 'donde', 'tipo' => 'stopword', 'categoria' => 'conjuncion', 'frecuencia' => 10000],
            ['termino' => 'como', 'tipo' => 'stopword', 'categoria' => 'conjuncion', 'frecuencia' => 10000],
            
            // Pronombres
            ['termino' => 'que', 'tipo' => 'stopword', 'categoria' => 'pronombre', 'frecuencia' => 10000],
            ['termino' => 'quien', 'tipo' => 'stopword', 'categoria' => 'pronombre', 'frecuencia' => 10000],
            ['termino' => 'cual', 'tipo' => 'stopword', 'categoria' => 'pronombre', 'frecuencia' => 10000],
            ['termino' => 'cuyo', 'tipo' => 'stopword', 'categoria' => 'pronombre', 'frecuencia' => 10000],
            ['termino' => 'cuya', 'tipo' => 'stopword', 'categoria' => 'pronombre', 'frecuencia' => 10000],
            ['termino' => 'cuyos', 'tipo' => 'stopword', 'categoria' => 'pronombre', 'frecuencia' => 10000],
            ['termino' => 'cuyas', 'tipo' => 'stopword', 'categoria' => 'pronombre', 'frecuencia' => 10000],
            
            // Adverbios comunes
            ['termino' => 'muy', 'tipo' => 'stopword', 'categoria' => 'adverbio', 'frecuencia' => 10000],
            ['termino' => 'más', 'tipo' => 'stopword', 'categoria' => 'adverbio', 'frecuencia' => 10000],
            ['termino' => 'menos', 'tipo' => 'stopword', 'categoria' => 'adverbio', 'frecuencia' => 10000],
            ['termino' => 'bien', 'tipo' => 'stopword', 'categoria' => 'adverbio', 'frecuencia' => 10000],
            ['termino' => 'mal', 'tipo' => 'stopword', 'categoria' => 'adverbio', 'frecuencia' => 10000],
            ['termino' => 'siempre', 'tipo' => 'stopword', 'categoria' => 'adverbio', 'frecuencia' => 10000],
            ['termino' => 'nunca', 'tipo' => 'stopword', 'categoria' => 'adverbio', 'frecuencia' => 10000],
            
            // Números
            ['termino' => 'uno', 'tipo' => 'stopword', 'categoria' => 'numero', 'frecuencia' => 10000],
            ['termino' => 'dos', 'tipo' => 'stopword', 'categoria' => 'numero', 'frecuencia' => 10000],
            ['termino' => 'tres', 'tipo' => 'stopword', 'categoria' => 'numero', 'frecuencia' => 10000],
            ['termino' => 'cuatro', 'tipo' => 'stopword', 'categoria' => 'numero', 'frecuencia' => 10000],
            ['termino' => 'cinco', 'tipo' => 'stopword', 'categoria' => 'numero', 'frecuencia' => 10000],
            ['termino' => 'seis', 'tipo' => 'stopword', 'categoria' => 'numero', 'frecuencia' => 10000],
            ['termino' => 'siete', 'tipo' => 'stopword', 'categoria' => 'numero', 'frecuencia' => 10000],
            ['termino' => 'ocho', 'tipo' => 'stopword', 'categoria' => 'numero', 'frecuencia' => 10000],
            ['termino' => 'nueve', 'tipo' => 'stopword', 'categoria' => 'numero', 'frecuencia' => 10000],
            ['termino' => 'diez', 'tipo' => 'stopword', 'categoria' => 'numero', 'frecuencia' => 10000],
            
            // Palabras médicas muy comunes que no son abreviaturas
            ['termino' => 'paciente', 'tipo' => 'stopword', 'categoria' => 'termino_medico_comun', 'frecuencia' => 10000],
            ['termino' => 'doctor', 'tipo' => 'stopword', 'categoria' => 'termino_medico_comun', 'frecuencia' => 10000],
            ['termino' => 'medico', 'tipo' => 'stopword', 'categoria' => 'termino_medico_comun', 'frecuencia' => 10000],
            ['termino' => 'médico', 'tipo' => 'stopword', 'categoria' => 'termino_medico_comun', 'frecuencia' => 10000],
            ['termino' => 'consulta', 'tipo' => 'stopword', 'categoria' => 'termino_medico_comun', 'frecuencia' => 10000],
            ['termino' => 'tratamiento', 'tipo' => 'stopword', 'categoria' => 'termino_medico_comun', 'frecuencia' => 10000],
            
            // Palabras muy cortas que pueden causar conflictos
            ['termino' => 'es', 'tipo' => 'stopword', 'categoria' => 'verbo_auxiliar', 'frecuencia' => 10000],
            ['termino' => 'se', 'tipo' => 'stopword', 'categoria' => 'pronombre', 'frecuencia' => 10000],
            ['termino' => 'le', 'tipo' => 'stopword', 'categoria' => 'pronombre', 'frecuencia' => 10000],
            ['termino' => 'te', 'tipo' => 'stopword', 'categoria' => 'pronombre', 'frecuencia' => 10000],
            ['termino' => 'me', 'tipo' => 'stopword', 'categoria' => 'pronombre', 'frecuencia' => 10000],
            ['termino' => 'nos', 'tipo' => 'stopword', 'categoria' => 'pronombre', 'frecuencia' => 10000],
            ['termino' => 'os', 'tipo' => 'stopword', 'categoria' => 'pronombre', 'frecuencia' => 10000],
            ['termino' => 'lo', 'tipo' => 'stopword', 'categoria' => 'pronombre', 'frecuencia' => 10000],
            ['termino' => 'les', 'tipo' => 'stopword', 'categoria' => 'pronombre', 'frecuencia' => 10000],
        ];
    }
}

