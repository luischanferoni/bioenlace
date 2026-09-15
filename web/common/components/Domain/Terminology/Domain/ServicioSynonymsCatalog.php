<?php

namespace common\components\Domain\Terminology\Domain;

/**
 * Catálogo de dominio (ex metadata/bioenlace/terminology/servicio-synonyms.yaml).
 */
final class ServicioSynonymsCatalog
{
    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'version' => 1,
        'synonyms' => [
            'odontologia' => [
                0 => 'dentista',
                1 => 'diente',
                2 => 'dientes',
                3 => 'muela',
                4 => 'muelas',
                5 => 'dolor de muela',
                6 => 'caries',
            ],
            'oftalmologia' => [
                0 => 'oculista',
                1 => 'ojos',
                2 => 'vista',
                3 => 'vision',
                4 => 'lentes',
                5 => 'anteojos',
            ],
            'traumatologia' => [
                0 => 'traumatologo',
                1 => 'fractura',
                2 => 'huesos',
                3 => 'esguince',
                4 => 'yeso',
            ],
            'kinesiologia' => [
                0 => 'kinesiologo',
                1 => 'kinesio',
                2 => 'fisioterapia',
                3 => 'fisioterapeuta',
                4 => 'rehabilitacion',
            ],
            'dermatologia' => [
                0 => 'dermatologo',
                1 => 'piel',
                2 => 'derma',
            ],
            'cardiologia' => [
                0 => 'cardiologo',
                1 => 'corazon',
            ],
            'ginecologia' => [
                0 => 'ginecologo',
                1 => 'gineco',
            ],
            'obstetricia' => [
                0 => 'obstetra',
                1 => 'embarazo',
                2 => 'parto',
                3 => 'prenatal',
            ],
            'pediatria' => [
                0 => 'pediatra',
            ],
            'urologia' => [
                0 => 'urologo',
            ],
            'neurologia' => [
                0 => 'neurologo',
                1 => 'nervios',
            ],
            'endocrinologia' => [
                0 => 'endocrinologo',
                1 => 'tiroides',
                2 => 'diabetes',
            ],
            'gastroenterologia' => [
                0 => 'gastroenterologo',
                1 => 'gastro',
                2 => 'estomago',
                3 => 'digestivo',
            ],
            'otorrinolaringologia' => [
                0 => 'otorrino',
                1 => 'oido',
                2 => 'garganta',
                3 => 'nariz',
            ],
            'neumonologia' => [
                0 => 'neumonologo',
                1 => 'pulmon',
                2 => 'pulmones',
            ],
            'psiquiatria' => [
                0 => 'psiquiatra',
            ],
            'psicologia' => [
                0 => 'psicologo',
                1 => 'psicologa',
                2 => 'terapia',
            ],
            'nutricion' => [
                0 => 'nutricionista',
                1 => 'dieta',
                2 => 'dietista',
            ],
            'fonoaudiologia' => [
                0 => 'fonoaudiologo',
                1 => 'fonoaudiologa',
                2 => 'fono',
            ],
            'enfermeria' => [
                0 => 'enfermero',
                1 => 'enfermera',
            ],
            'med general' => [
                0 => 'clinico',
                1 => 'clinica',
                2 => 'medico clinico',
                3 => 'medicina general',
                4 => 'generalista',
                5 => 'medico de cabecera',
                6 => 'medico general',
            ],
            'med clinica' => [
                0 => 'clinico',
                1 => 'clinica',
                2 => 'medicina clinica',
                3 => 'medico clinico',
            ],
            'cirugia' => [
                0 => 'cirujano',
                1 => 'operacion',
                2 => 'quirofano',
            ],
            'cirugia general' => [
                0 => 'cirujano general',
            ],
            'nefrologia' => [
                0 => 'nefrologo',
                1 => 'rinon',
                2 => 'rinones',
                3 => 'dialisis',
            ],
            'reumatologia' => [
                0 => 'reumatologo',
                1 => 'reuma',
                2 => 'artritis',
            ],
            'oncologia' => [
                0 => 'oncologo',
                1 => 'cancer',
                2 => 'tumor',
            ],
            'hematologia' => [
                0 => 'hematologo',
                1 => 'sangre',
                2 => 'anemia',
            ],
            'infectologia' => [
                0 => 'infectologo',
            ],
            'alergologia' => [
                0 => 'alergologo',
                1 => 'alergista',
                2 => 'alergia',
                3 => 'alergias',
            ],
            'diabetologia' => [
                0 => 'diabetologo',
                1 => 'diabetes',
            ],
            'proctologia' => [
                0 => 'proctologo',
                1 => 'hemorroides',
            ],
            'mastologia' => [
                0 => 'mastologo',
                1 => 'mama',
                2 => 'mamas',
                3 => 'seno',
            ],
            'neonatologia' => [
                0 => 'neonatologo',
            ],
            'geriatria' => [
                0 => 'geriatra',
                1 => 'adulto mayor',
                2 => 'tercera edad',
            ],
            'toxicologia' => [
                0 => 'toxicologo',
            ],
            'flebologia' => [
                0 => 'flebologo',
                1 => 'varices',
            ],
            'medicina laboral' => [
                0 => 'laboral',
                1 => 'apto medico',
                2 => 'apto fisico',
            ],
            'medicina del trabajo' => [
                0 => 'laboral',
                1 => 'apto medico',
            ],
            'ecografia' => [
                0 => 'ecografo',
                1 => 'eco',
            ],
            'laboratorio' => [
                0 => 'analisis',
                1 => 'analisis de sangre',
                2 => 'hemograma',
            ],
            'radiologia' => [
                0 => 'radiologo',
                1 => 'radiografia',
                2 => 'rayos',
                3 => 'rayos x',
                4 => 'rx',
            ],
            'tomografia' => [
                0 => 'tomografo',
                1 => 'tac',
                2 => 'scanner',
            ],
            'resonancia' => [
                0 => 'resonancia magnetica',
                1 => 'rmn',
            ],
            'farmacia' => [
                0 => 'farmaceutico',
                1 => 'medicamentos',
                2 => 'remedios',
            ],
            'vacunatorio' => [
                0 => 'vacuna',
                1 => 'vacunas',
                2 => 'vacunacion',
            ],
        ],
    ];
    }
}
