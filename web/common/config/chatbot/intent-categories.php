<?php
/**
 * Configuración de categorías e intents para el orquestador de consultas
 * 
 * Define todas las categorías de consultas y sus intents asociados,
 * junto con keywords y patrones para detección por reglas.
 */

return [
    // 1. GESTIÓN DE TURNOS
    'turnos' => [
        'name' => 'Gestión de Turnos',
        'description' => 'Acciones concretas relacionadas con turnos médicos',
        'intents' => [
            'crear_turno' => [
                'name' => 'Crear Turno',
                'keywords' => [
                    'sacar turno', 'reservar turno', 'agendar turno', 'pedir turno',
                    'necesito turno', 'quiero turno', 'turno para', 'turno con',
                    'agendar', 'reservar', 'sacar cita', 'cita médica'
                ],
                'patterns' => [
                    '/\b(sacar|reservar|agendar|pedir|necesito|quiero)\s+(un\s+)?turno/i',
                    '/turno\s+(para|con|de)/i',
                    '/agendar\s+(cita|consulta)/i'
                ],
                'handler' => 'TurnosHandler',
                'priority' => 'high'
            ],
            'modificar_turno' => [
                'name' => 'Modificar Turno',
                'keywords' => [
                    'cambiar turno', 'modificar turno', 'reagendar turno',
                    'cambiar fecha', 'cambiar horario', 'mover turno',
                    'reagendar', 'modificar cita'
                ],
                'patterns' => [
                    '/\b(cambiar|modificar|reagendar|mover)\s+(el\s+)?turno/i',
                    '/cambiar\s+(fecha|horario|hora)/i'
                ],
                'handler' => 'TurnosHandler',
                'priority' => 'high'
            ],
            'cancelar_turno' => [
                'name' => 'Cancelar Turno',
                'keywords' => [
                    'cancelar turno', 'anular turno', 'borrar turno',
                    'no puedo ir', 'no voy a ir', 'cancelar cita'
                ],
                'patterns' => [
                    '/\b(cancelar|anular|borrar)\s+(el\s+)?turno/i',
                    '/no\s+(puedo|voy)\s+a\s+ir/i'
                ],
                'handler' => 'TurnosHandler',
                'priority' => 'high'
            ],
            'consultar_turnos' => [
                'name' => 'Consultar Turnos',
                'keywords' => [
                    'mis turnos', 'ver turnos', 'turnos futuros', 'próximo turno',
                    'cuándo es mi turno', 'qué turnos tengo', 'turnos pasados'
                ],
                'patterns' => [
                    '/\b(mis|ver|consultar)\s+turnos/i',
                    '/pr[oó]ximo\s+turno/i',
                    '/cu[áa]ndo\s+es\s+mi\s+turno/i'
                ],
                'handler' => 'TurnosHandler',
                'priority' => 'medium'
            ],
            'disponibilidad_turnos' => [
                'name' => 'Disponibilidad de Turnos',
                'keywords' => [
                    'horarios disponibles', 'disponibilidad', 'turnos disponibles',
                    'qué horarios hay', 'cuándo hay turno'
                ],
                'patterns' => [
                    '/horarios?\s+disponibles/i',
                    '/turnos?\s+disponibles/i',
                    '/cu[áa]ndo\s+hay\s+turno/i'
                ],
                'handler' => 'TurnosHandler',
                'priority' => 'medium'
            ]
        ]
    ],

    // 2. HISTORIA CLÍNICA
    'historia_clinica' => [
        'name' => 'Historia Clínica',
        'description' => 'Acciones para acceder a información clínica del paciente',
        'intents' => [
            'ver_historia_clinica' => [
                'name' => 'Ver Historia Clínica',
                'keywords' => [
                    'mi historia clínica', 'historia clínica', 'historial médico',
                    'ver mi historia', 'historia médica'
                ],
                'patterns' => [
                    '/historia\s+cl[ií]nica/i',
                    '/historial\s+m[ée]dico/i'
                ],
                'handler' => 'HistoriaClinicaHandler',
                'priority' => 'high'
            ],
            'ver_consultas_anteriores' => [
                'name' => 'Ver Consultas Anteriores',
                'keywords' => [
                    'mis consultas', 'consultas anteriores', 'consultas pasadas',
                    'ver consultas', 'historial de consultas'
                ],
                'patterns' => [
                    '/\b(mis|ver|consultas?)\s+consultas?/i',
                    '/consultas?\s+(anteriores|pasadas)/i'
                ],
                'handler' => 'HistoriaClinicaHandler',
                'priority' => 'medium'
            ],
            'ver_diagnosticos' => [
                'name' => 'Ver Diagnósticos',
                'keywords' => [
                    'mis diagnósticos', 'diagnósticos', 'ver diagnósticos',
                    'qué me diagnosticaron'
                ],
                'patterns' => [
                    '/\b(mis|ver)\s+diagn[oó]sticos?/i',
                    '/qu[ée]\s+me\s+diagnosticaron/i'
                ],
                'handler' => 'HistoriaClinicaHandler',
                'priority' => 'medium'
            ],
            'ver_medicamentos' => [
                'name' => 'Ver Medicamentos',
                'keywords' => [
                    'mis medicamentos', 'medicamentos recetados', 'qué medicamentos tomo',
                    'ver medicamentos', 'medicación'
                ],
                'patterns' => [
                    '/\b(mis|ver)\s+medicamentos?/i',
                    '/medicaci[oó]n/i'
                ],
                'handler' => 'HistoriaClinicaHandler',
                'priority' => 'medium'
            ],
            'ver_practicas' => [
                'name' => 'Ver Prácticas',
                'keywords' => [
                    'mis estudios', 'resultados', 'prácticas realizadas',
                    'ver resultados', 'estudios médicos'
                ],
                'patterns' => [
                    '/\b(mis|ver)\s+(estudios|pr[áa]cticas|resultados)/i',
                    '/resultados?\s+(de\s+)?(estudios|an[áa]lisis)/i'
                ],
                'handler' => 'HistoriaClinicaHandler',
                'priority' => 'medium'
            ],
            'ver_alergias' => [
                'name' => 'Ver Alergias',
                'keywords' => [
                    'mis alergias', 'alergias', 'a qué soy alérgico',
                    'ver alergias', 'intolerancias'
                ],
                'patterns' => [
                    '/\b(mis|ver)\s+alergias?/i',
                    '/a\s+qu[ée]\s+soy\s+al[ée]rgico/i'
                ],
                'handler' => 'HistoriaClinicaHandler',
                'priority' => 'medium'
            ]
        ]
    ],

    // 3. CONSULTA MÉDICA / INFORMACIÓN DE SALUD
    'consulta_medica' => [
        'name' => 'Consulta Médica',
        'description' => 'Consultas informativas sobre salud, síntomas, medicamentos, prevención',
        'intents' => [
            'consulta_sintomas' => [
                'name' => 'Consulta sobre Síntomas',
                'keywords' => [
                    'síntomas', 'me duele', 'tengo dolor', 'me siento mal',
                    'es normal que', 'qué significa', 'por qué tengo'
                ],
                'patterns' => [
                    '/\b(es\s+normal|qu[ée]\s+significa|por\s+qu[ée])\s+que/i',
                    '/\b(me\s+duele|tengo\s+dolor|siento)/i',
                    '/s[ií]ntomas?/i'
                ],
                'handler' => 'ConsultaMedicaHandler',
                'priority' => 'medium'
            ],
            'consulta_medicamento' => [
                'name' => 'Consulta sobre Medicamentos',
                'keywords' => [
                    'puedo tomar', 'es seguro tomar', 'medicamento para',
                    'ibuprofeno', 'paracetamol', 'puedo usar'
                ],
                'patterns' => [
                    '/\b(puedo|es\s+seguro)\s+tomar/i',
                    '/medicamento\s+para/i',
                    '/\b(puedo|debo)\s+usar\s+(este|ese)\s+medicamento/i'
                ],
                'handler' => 'ConsultaMedicaHandler',
                'priority' => 'medium'
            ],
            'consulta_prevencion' => [
                'name' => 'Consulta sobre Prevención',
                'keywords' => [
                    'cómo prevenir', 'prevención', 'cuidados', 'qué hacer para',
                    'cómo cuidar', 'medidas preventivas'
                ],
                'patterns' => [
                    '/c[oó]mo\s+prevenir/i',
                    '/prevenci[oó]n/i',
                    '/cuidados?\s+(de|para)/i'
                ],
                'handler' => 'ConsultaMedicaHandler',
                'priority' => 'low'
            ],
            'consulta_vacunacion' => [
                'name' => 'Consulta sobre Vacunación',
                'keywords' => [
                    'vacunas', 'vacunación', 'calendario de vacunas',
                    'qué vacunas necesito', 'vacuna para'
                ],
                'patterns' => [
                    '/vacunas?/i',
                    '/vacunaci[oó]n/i',
                    '/calendario\s+de\s+vacunas/i'
                ],
                'handler' => 'ConsultaMedicaHandler',
                'priority' => 'medium'
            ],
            'cuando_consultar' => [
                'name' => 'Cuándo Consultar',
                'keywords' => [
                    'cuándo consultar', 'cuándo ir al médico', 'cuándo debo ir',
                    'es urgente', 'necesito ir al médico'
                ],
                'patterns' => [
                    '/cu[áa]ndo\s+(consultar|ir\s+al\s+m[ée]dico|debo\s+ir)/i',
                    '/es\s+urgente/i'
                ],
                'handler' => 'ConsultaMedicaHandler',
                'priority' => 'medium'
            ]
        ]
    ],

    // 4. EFECTORES Y UBICACIONES
    'efectores' => [
        'name' => 'Efectores y Ubicaciones',
        'description' => 'Búsqueda e información sobre centros de salud',
        'intents' => [
            'buscar_efector' => [
                'name' => 'Buscar Efector',
                'keywords' => [
                    'buscar centro', 'centro de salud', 'hospital cerca',
                    'dónde hay', 'centro médico', 'clínica'
                ],
                'patterns' => [
                    '/buscar\s+(centro|hospital|cl[ií]nica)/i',
                    '/d[oó]nde\s+hay\s+(centro|hospital)/i',
                    '/centro\s+(de\s+)?salud/i'
                ],
                'handler' => 'EfectoresHandler',
                'priority' => 'medium'
            ],
            'informacion_efector' => [
                'name' => 'Información de Efector',
                'keywords' => [
                    'información del centro', 'horarios del centro',
                    'datos del hospital', 'contacto del centro'
                ],
                'patterns' => [
                    '/informaci[oó]n\s+(del|de\s+el)\s+(centro|hospital)/i',
                    '/horarios?\s+(del|de\s+el)\s+centro/i'
                ],
                'handler' => 'EfectoresHandler',
                'priority' => 'medium'
            ],
            'direcciones_rutas' => [
                'name' => 'Direcciones y Rutas',
                'keywords' => [
                    'cómo llegar', 'dirección', 'dónde queda',
                    'ubicación', 'direcciones'
                ],
                'patterns' => [
                    '/c[oó]mo\s+llegar/i',
                    '/d[oó]nde\s+queda/i',
                    '/direcci[oó]n/i'
                ],
                'handler' => 'EfectoresHandler',
                'priority' => 'low'
            ]
        ]
    ],

    // 5. PROFESIONALES DE SALUD
    'profesionales' => [
        'name' => 'Profesionales de Salud',
        'description' => 'Búsqueda e información sobre profesionales médicos',
        'intents' => [
            'buscar_profesional' => [
                'name' => 'Buscar Profesional',
                'keywords' => [
                    'buscar médico', 'buscar doctor', 'médico de',
                    'profesional de', 'especialista'
                ],
                'patterns' => [
                    '/buscar\s+(m[ée]dico|doctor|profesional)/i',
                    '/m[ée]dico\s+de/i',
                    '/especialista/i'
                ],
                'handler' => 'ProfesionalesHandler',
                'priority' => 'medium'
            ],
            'informacion_profesional' => [
                'name' => 'Información de Profesional',
                'keywords' => [
                    'información del médico', 'horarios del doctor',
                    'datos del profesional'
                ],
                'patterns' => [
                    '/informaci[oó]n\s+(del|de\s+el)\s+(m[ée]dico|doctor)/i',
                    '/horarios?\s+(del|de\s+el)\s+(m[ée]dico|doctor)/i'
                ],
                'handler' => 'ProfesionalesHandler',
                'priority' => 'medium'
            ]
        ]
    ],

    // 6. MEDICAMENTOS Y FARMACIA
    'medicamentos' => [
        'name' => 'Medicamentos y Farmacia',
        'description' => 'Acciones concretas relacionadas con medicamentos y farmacias',
        'intents' => [
            'farmacias_turno' => [
                'name' => 'Farmacias de Turno',
                'keywords' => [
                    'farmacias de turno', 'farmacia abierta', 'farmacia ahora',
                    'farmacia 24 horas', 'farmacia de guardia'
                ],
                'patterns' => [
                    '/farmacias?\s+(de\s+)?turno/i',
                    '/farmacia\s+(abierta|ahora|24\s+horas)/i',
                    '/farmacia\s+de\s+guardia/i'
                ],
                'handler' => 'MedicamentosHandler',
                'priority' => 'high'
            ],
            'buscar_farmacias' => [
                'name' => 'Buscar Farmacias',
                'keywords' => [
                    'buscar farmacia', 'farmacias cercanas', 'dónde hay farmacia',
                    'farmacia cerca'
                ],
                'patterns' => [
                    '/buscar\s+farmacia/i',
                    '/farmacias?\s+cercanas?/i',
                    '/d[oó]nde\s+hay\s+farmacia/i'
                ],
                'handler' => 'MedicamentosHandler',
                'priority' => 'medium'
            ],
            'disponibilidad_medicamentos' => [
                'name' => 'Disponibilidad de Medicamentos',
                'keywords' => [
                    'disponibilidad de medicamentos', 'hay medicamento',
                    'medicamento disponible', 'tienen medicamento'
                ],
                'patterns' => [
                    '/disponibilidad\s+de\s+medicamentos?/i',
                    '/hay\s+medicamento/i',
                    '/tienen\s+medicamento/i'
                ],
                'handler' => 'MedicamentosHandler',
                'priority' => 'medium'
            ]
        ]
    ],

    // 7. PRÁCTICAS Y ESTUDIOS
    'practicas' => [
        'name' => 'Prácticas y Estudios',
        'description' => 'Solicitud y consulta de estudios médicos',
        'intents' => [
            'solicitar_practica' => [
                'name' => 'Solicitar Práctica',
                'keywords' => [
                    'solicitar estudio', 'pedir análisis', 'necesito estudio',
                    'sacar turno para estudio'
                ],
                'patterns' => [
                    '/solicitar\s+(estudio|an[áa]lisis|pr[áa]ctica)/i',
                    '/pedir\s+(estudio|an[áa]lisis)/i'
                ],
                'handler' => 'PracticasHandler',
                'priority' => 'high'
            ],
            'resultados_practicas' => [
                'name' => 'Resultados de Prácticas',
                'keywords' => [
                    'resultados', 'ver resultados', 'mis resultados',
                    'resultados de estudios'
                ],
                'patterns' => [
                    '/resultados?/i',
                    '/ver\s+resultados?/i',
                    '/mis\s+resultados?/i'
                ],
                'handler' => 'PracticasHandler',
                'priority' => 'high'
            ]
        ]
    ],

    // 8. TRÁMITES Y DOCUMENTACIÓN
    'tramites' => [
        'name' => 'Trámites y Documentación',
        'description' => 'Solicitud de certificados, recetas y documentación médica',
        'intents' => [
            'certificados' => [
                'name' => 'Certificados',
                'keywords' => [
                    'certificado médico', 'necesito certificado', 'sacar certificado',
                    'certificado de salud'
                ],
                'patterns' => [
                    '/certificado\s+(m[ée]dico|de\s+salud)/i',
                    '/necesito\s+certificado/i'
                ],
                'handler' => 'TramitesHandler',
                'priority' => 'medium'
            ],
            'recetas' => [
                'name' => 'Recetas',
                'keywords' => [
                    'receta médica', 'ver receta', 'mis recetas',
                    'receta de medicamentos'
                ],
                'patterns' => [
                    '/receta\s+(m[ée]dica|de\s+medicamentos?)/i',
                    '/ver\s+receta/i',
                    '/mis\s+recetas?/i'
                ],
                'handler' => 'TramitesHandler',
                'priority' => 'medium'
            ]
        ]
    ],

    // 9. PROGRAMAS Y SERVICIOS ESPECIALES
    'programas' => [
        'name' => 'Programas y Servicios Especiales',
        'description' => 'Información sobre programas de salud',
        'intents' => [
            'programas_salud' => [
                'name' => 'Programas de Salud',
                'keywords' => [
                    'programas de salud', 'qué programas hay', 'programas disponibles',
                    'programa para'
                ],
                'patterns' => [
                    '/programas?\s+(de\s+)?salud/i',
                    '/qu[ée]\s+programas?\s+hay/i'
                ],
                'handler' => 'ProgramasHandler',
                'priority' => 'low'
            ],
            'enfermedades_cronicas' => [
                'name' => 'Enfermedades Crónicas',
                'keywords' => [
                    'programa diabetes', 'programa hipertensión', 'enfermedades crónicas',
                    'programa para diabetes'
                ],
                'patterns' => [
                    '/programa\s+(diabetes|hipertensi[oó]n)/i',
                    '/enfermedades?\s+cr[oó]nicas?/i'
                ],
                'handler' => 'ProgramasHandler',
                'priority' => 'medium'
            ]
        ]
    ],

    // 10. EMERGENCIAS Y URGENCIAS
    'emergencias' => [
        'name' => 'Emergencias y Urgencias',
        'description' => 'Emergencias médicas que requieren atención inmediata',
        'intents' => [
            'emergencia_critica' => [
                'name' => 'Emergencia Crítica',
                'keywords' => [
                    'no respira', 'perdió el conocimiento', 'dolor de pecho intenso',
                    'emergencia', 'urgencia', 'ayuda', 'socorro'
                ],
                'patterns' => [
                    '/no\s+respira/i',
                    '/perdi[oó]\s+el\s+conocimiento/i',
                    '/dolor\s+de\s+pecho\s+intenso/i',
                    '/\b(emergencia|urgencia|ayuda|socorro)\b/i'
                ],
                'handler' => 'EmergenciasHandler',
                'priority' => 'critical',
                'requires_immediate_response' => true
            ],
            'guardia' => [
                'name' => 'Información de Guardia',
                'keywords' => [
                    'guardia', 'servicio de emergencia', 'urgencias',
                    'dónde está la guardia'
                ],
                'patterns' => [
                    '/guardia/i',
                    '/servicio\s+de\s+emergencia/i',
                    '/urgencias?/i'
                ],
                'handler' => 'EmergenciasHandler',
                'priority' => 'high'
            ],
            'telefonos_emergencia' => [
                'name' => 'Teléfonos de Emergencia',
                'keywords' => [
                    'teléfono emergencia', 'número emergencia', 'llamar emergencia',
                    '107', '911'
                ],
                'patterns' => [
                    '/tel[ée]fono\s+emergencia/i',
                    '/n[úu]mero\s+emergencia/i',
                    '/\b(107|911)\b/i'
                ],
                'handler' => 'EmergenciasHandler',
                'priority' => 'high'
            ]
        ]
    ],

    // 11. INTERNACIÓN
    'internacion' => [
        'name' => 'Internación',
        'description' => 'Información sobre internación',
        'intents' => [
            'estado_internacion' => [
                'name' => 'Estado de Internación',
                'keywords' => [
                    'estado de internación', 'cómo está internado', 'paciente internado',
                    'internación'
                ],
                'patterns' => [
                    '/estado\s+de\s+internaci[oó]n/i',
                    '/c[oó]mo\s+est[áa]\s+internado/i'
                ],
                'handler' => 'InternacionHandler',
                'priority' => 'high'
            ]
        ]
    ],

    // 12. SALUD PÚBLICA Y EPIDEMIOLOGÍA
    'salud_publica' => [
        'name' => 'Salud Pública',
        'description' => 'Información sobre salud pública y epidemiología',
        'intents' => [
            'campanas_salud' => [
                'name' => 'Campañas de Salud',
                'keywords' => [
                    'campañas de salud', 'campaña', 'programas de prevención'
                ],
                'patterns' => [
                    '/campa[ñn]as?\s+(de\s+)?salud/i',
                    '/programas?\s+de\s+prevenci[oó]n/i'
                ],
                'handler' => 'SaludPublicaHandler',
                'priority' => 'low'
            ]
        ]
    ],

    // 13. CONSULTAS GENERALES / SOPORTE
    'general' => [
        'name' => 'Consultas Generales',
        'description' => 'Consultas no médicas: ayuda, contacto, navegación del sistema',
        'intents' => [
            'saludo' => [
                'name' => 'Saludo',
                'keywords' => [
                    'hola', 'buenos días', 'buenas tardes', 'buenas noches',
                    'buen día', 'hi', 'hello'
                ],
                'patterns' => [
                    '/\b(hola|buenos?\s+d[ií]as?|buenas?\s+tardes?|buenas?\s+noches?|hi|hello)\b/i'
                ],
                'handler' => 'GeneralHandler',
                'priority' => 'low'
            ],
            'ayuda' => [
                'name' => 'Ayuda',
                'keywords' => [
                    'ayuda', 'cómo funciona', 'qué puedo hacer', 'instrucciones',
                    'cómo usar', 'help'
                ],
                'patterns' => [
                    '/\b(ayuda|help)\b/i',
                    '/c[oó]mo\s+(funciona|usar)/i',
                    '/qu[ée]\s+puedo\s+hacer/i'
                ],
                'handler' => 'GeneralHandler',
                'priority' => 'low'
            ],
            'contacto' => [
                'name' => 'Contacto',
                'keywords' => [
                    'contacto', 'teléfono', 'email', 'dónde contactar',
                    'información de contacto'
                ],
                'patterns' => [
                    '/contacto/i',
                    '/tel[ée]fono/i',
                    '/d[oó]nde\s+contactar/i'
                ],
                'handler' => 'GeneralHandler',
                'priority' => 'low'
            ],
            'fuera_de_alcance' => [
                'name' => 'Fuera de Alcance',
                'keywords' => [],
                'patterns' => [],
                'handler' => 'GeneralHandler',
                'priority' => 'low',
                'is_fallback' => true // Se usa cuando no hay match
            ]
        ]
    ]
];
