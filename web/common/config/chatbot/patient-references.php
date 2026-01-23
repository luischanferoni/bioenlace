<?php
/**
 * Configuración genérica de referencias del paciente
 * 
 * Mapea texto del usuario a configuraciones de resolución de referencias.
 * Permite resolver automáticamente referencias como "odontólogo de siempre",
 * "último profesional", etc.
 */

return [
    // Referencias a profesionales
    'odontologo de siempre' => [
        'type' => 'professional',
        'strategy' => 'most_frequent',
        'filter' => ['servicio' => 'ODONTOLOGIA'],
        'cache_key' => 'frequent_professional_odontologia',
        'fallback_query' => 'getLastTurnoByServicio'
    ],
    'ultimo odontologo' => [
        'type' => 'professional',
        'strategy' => 'last_used',
        'filter' => ['servicio' => 'ODONTOLOGIA'],
        'cache_key' => 'last_odontologo',
        'fallback_query' => 'getLastTurnoByServicio'
    ],
    'el odontologo' => [
        'type' => 'professional',
        'strategy' => 'most_frequent',
        'filter' => ['servicio' => 'ODONTOLOGIA'],
        'cache_key' => 'frequent_professional_odontologia'
    ],
    'mi odontologo' => [
        'type' => 'professional',
        'strategy' => 'most_frequent',
        'filter' => ['servicio' => 'ODONTOLOGIA'],
        'cache_key' => 'frequent_professional_odontologia'
    ],
    'pediatra de siempre' => [
        'type' => 'professional',
        'strategy' => 'most_frequent',
        'filter' => ['servicio' => 'PEDIATRIA'],
        'cache_key' => 'frequent_professional_pediatria',
        'fallback_query' => 'getLastTurnoByServicio'
    ],
    'ultimo pediatra' => [
        'type' => 'professional',
        'strategy' => 'last_used',
        'filter' => ['servicio' => 'PEDIATRIA'],
        'cache_key' => 'last_pediatra',
        'fallback_query' => 'getLastTurnoByServicio'
    ],
    'mi pediatra' => [
        'type' => 'professional',
        'strategy' => 'most_frequent',
        'filter' => ['servicio' => 'PEDIATRIA'],
        'cache_key' => 'frequent_professional_pediatria'
    ],
    'el pediatra' => [
        'type' => 'professional',
        'strategy' => 'most_frequent',
        'filter' => ['servicio' => 'PEDIATRIA'],
        'cache_key' => 'frequent_professional_pediatria'
    ],
    'medico de siempre' => [
        'type' => 'professional',
        'strategy' => 'most_frequent',
        'filter' => [],
        'cache_key' => 'frequent_professional_general'
    ],
    'ultimo medico' => [
        'type' => 'professional',
        'strategy' => 'last_used',
        'filter' => [],
        'cache_key' => 'last_professional',
        'fallback_query' => 'getLastTurno'
    ],
    'el mismo de antes' => [
        'type' => 'context',
        'strategy' => 'last_used',
        'use_conversation_context' => true,
        'fallback' => 'last_turno_servicio'
    ],
    'el mismo' => [
        'type' => 'context',
        'strategy' => 'last_used',
        'use_conversation_context' => true,
        'fallback' => 'last_turno_servicio'
    ],
    'el de antes' => [
        'type' => 'context',
        'strategy' => 'last_used',
        'use_conversation_context' => true,
        'fallback' => 'last_turno_servicio'
    ],
    
    // Referencias a efectores
    'centro de siempre' => [
        'type' => 'efector',
        'strategy' => 'most_frequent',
        'cache_key' => 'frequent_efector',
        'fallback_query' => 'getLastEfector'
    ],
    'el centro de siempre' => [
        'type' => 'efector',
        'strategy' => 'most_frequent',
        'cache_key' => 'frequent_efector'
    ],
    'ultimo centro' => [
        'type' => 'efector',
        'strategy' => 'last_used',
        'cache_key' => 'last_efector',
        'fallback_query' => 'getLastEfector'
    ],
    'el mismo centro' => [
        'type' => 'efector',
        'strategy' => 'last_used',
        'use_conversation_context' => true,
        'cache_key' => 'last_efector'
    ],
    
    // Referencias genéricas por servicio
    'profesional de siempre' => [
        'type' => 'professional',
        'strategy' => 'most_frequent',
        'filter' => [], // Se filtra por servicio mencionado en el contexto
        'cache_key' => 'frequent_professional_by_service'
    ],
    'ultimo profesional' => [
        'type' => 'professional',
        'strategy' => 'last_used',
        'filter' => [], // Se filtra por servicio mencionado en el contexto
        'cache_key' => 'last_professional_by_service',
        'fallback_query' => 'getLastTurnoByServicio'
    ]
];
