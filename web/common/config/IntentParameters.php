<?php
/**
 * Configuración de parámetros por intent
 * 
 * Define qué parámetros son requeridos/opcionales para cada intent,
 * su lifetime, reglas de limpieza y uso del perfil del paciente.
 */

return [
    'crear_turno' => [
        'required_params' => ['servicio', 'fecha', 'hora'],
        'optional_params' => ['profesional', 'efector', 'observaciones'],
        'lifetime' => 600, // 10 minutos
        'cleanup_on' => ['intent_change', 'completed', 'timeout'],
        'patient_profile' => [
            'can_use' => ['professional', 'efector', 'service'],
            'resolve_references' => true,
            'update_on_complete' => [
                'type' => 'professional',
                'fields' => ['id_rr_hh', 'id_efector', 'servicio']
            ],
            'cache_ttl' => 3600
        ]
    ],
    'modificar_turno' => [
        'required_params' => ['turno_id'],
        'optional_params' => ['fecha', 'hora', 'profesional'],
        'lifetime' => 600,
        'cleanup_on' => ['intent_change', 'completed', 'timeout'],
        'patient_profile' => [
            'can_use' => ['professional'],
            'resolve_references' => false,
            'cache_ttl' => 3600
        ]
    ],
    'cancelar_turno' => [
        'required_params' => ['turno_id'],
        'optional_params' => [],
        'lifetime' => 300,
        'cleanup_on' => ['intent_change', 'completed'],
        'patient_profile' => [
            'can_use' => [],
            'resolve_references' => false
        ]
    ],
    'consultar_turnos' => [
        'required_params' => [],
        'optional_params' => ['fecha_desde', 'fecha_hasta', 'servicio'],
        'lifetime' => 300,
        'cleanup_on' => ['intent_change'],
        'patient_profile' => [
            'can_use' => [],
            'resolve_references' => false
        ]
    ],
    'consulta_medicamento' => [
        'required_params' => ['medicamento'],
        'optional_params' => ['sintoma', 'edad', 'condiciones', 'medicamentos_actuales'],
        'lifetime' => 300, // 5 minutos
        'cleanup_on' => ['intent_change'],
        'patient_profile' => [
            'can_use' => ['medications_history'], // Si existe en el futuro
            'resolve_references' => false,
            'cache_ttl' => 1800
        ]
    ],
    'consulta_sintomas' => [
        'required_params' => ['sintoma'],
        'optional_params' => ['edad', 'duracion', 'intensidad', 'otras_sintomas'],
        'lifetime' => 300,
        'cleanup_on' => ['intent_change'],
        'patient_profile' => [
            'can_use' => [],
            'resolve_references' => false
        ]
    ],
    'farmacias_turno' => [
        'required_params' => [],
        'optional_params' => ['ubicacion', 'medicamento'],
        'lifetime' => 300,
        'cleanup_on' => ['intent_change'],
        'patient_profile' => [
            'can_use' => ['efector'], // Para ubicación preferida
            'resolve_references' => false
        ]
    ],
    'buscar_farmacias' => [
        'required_params' => [],
        'optional_params' => ['ubicacion', 'medicamento'],
        'lifetime' => 300,
        'cleanup_on' => ['intent_change'],
        'patient_profile' => [
            'can_use' => ['efector'],
            'resolve_references' => false
        ]
    ],
    'emergencia_critica' => [
        'required_params' => [],
        'optional_params' => ['sintoma', 'ubicacion'],
        'lifetime' => 60, // 1 minuto - emergencias se limpian rápido
        'cleanup_on' => ['intent_change', 'completed'],
        'patient_profile' => [
            'can_use' => ['efector'], // Para ubicación
            'resolve_references' => false
        ],
        'requires_immediate_response' => true
    ],
    'ver_historia_clinica' => [
        'required_params' => [],
        'optional_params' => ['fecha_desde', 'fecha_hasta', 'tipo'],
        'lifetime' => 300,
        'cleanup_on' => ['intent_change'],
        'patient_profile' => [
            'can_use' => [],
            'resolve_references' => false
        ]
    ],
    'solicitar_practica' => [
        'required_params' => ['tipo_practica'],
        'optional_params' => ['servicio', 'profesional', 'fecha_preferida'],
        'lifetime' => 600,
        'cleanup_on' => ['intent_change', 'completed', 'timeout'],
        'patient_profile' => [
            'can_use' => ['professional', 'efector'],
            'resolve_references' => true,
            'cache_ttl' => 3600
        ]
    ],
    'resultados_practicas' => [
        'required_params' => [],
        'optional_params' => ['fecha_desde', 'fecha_hasta', 'tipo'],
        'lifetime' => 300,
        'cleanup_on' => ['intent_change'],
        'patient_profile' => [
            'can_use' => [],
            'resolve_references' => false
        ]
    ],
    'saludo' => [
        'required_params' => [],
        'optional_params' => [],
        'lifetime' => 60,
        'cleanup_on' => ['intent_change'],
        'patient_profile' => [
            'can_use' => [],
            'resolve_references' => false
        ]
    ],
    'ayuda' => [
        'required_params' => [],
        'optional_params' => ['tema'],
        'lifetime' => 300,
        'cleanup_on' => ['intent_change'],
        'patient_profile' => [
            'can_use' => [],
            'resolve_references' => false
        ]
    ],
    'fuera_de_alcance' => [
        'required_params' => [],
        'optional_params' => [],
        'lifetime' => 60,
        'cleanup_on' => ['intent_change'],
        'patient_profile' => [
            'can_use' => [],
            'resolve_references' => false
        ]
    ]
];
