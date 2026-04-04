<?php
return [
    'adminEmail' => 'admin@example.com',
    'supportEmail' => 'support@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName' => 'Example.com mailer',
    'user.passwordResetTokenExpire' => 3600,
    'user.passwordMinLength' => 8,
    'bsVersion' => '5.x',
    'hostFormsAPI'=> 'http://10.10.10.235:9559',//'http://localhost:3000',//'http://saluddigital.msalsgo.gob.ar:9559'
    'SISA_APP_ID'=> '6df3d2f1',
    'SISA_APP_KEY'=>'e6d747f81e4ef3112750cc477f35fd29',
    'hf_activar_correccion' => true,
    /** Push turnos: si se define httpEndpoint, TurnoNotificacionController reenvía el payload (proxy). */
    'turnosPush' => [
        'httpEndpoint' => null,
    ],
    /** Autogestión paciente: oferta de próximos slots (endpoint slots-disponibles-como-paciente en API v1). */
    'turnosPaciente' => [
        /** Máximo de slots devueltos en una respuesta agrupada */
        'slots_oferta_max' => 20,
        /** Días hacia adelante que puede explorar TurnoSlotFinder */
        'slots_busqueda_max_dias' => 45,
        /** Hora límite inclusive: &lt; este HH:MM → franja `manana`, ≥ → `tarde` */
        'franja_tarde_desde' => '13:00',
        /** Tope duro si el cliente envía `limite` (anti-abuso) */
        'slots_oferta_max_cliente' => 60,
    ],
    /**
     * IDs de servicio (tabla servicios) que bajo encounter IMP listan agenda quirúrgica en /api/v1/pacientes.
     * Vacío: solo heurística por nombre (cirugía, quirófano, etc.) en {@see \common\models\Servicio::esServicioAgendaQuirurgica}.
     */
    'serviciosAgendaQuirurgicaIds' => [],

    /**
     * Catálogo de acciones API: descubrimiento de controladores v1, listas filtradas por RBAC, UniversalQuery.
     * useCache => false: sin caché de aplicación en cada request (útil mientras se suman rutas/controladores).
     * Sobrescribir en params-local (frontend/common) durante construcción; en producción dejar true.
     */
    'apiActionCatalog' => [
        'useCache' => true,
    ],
];
