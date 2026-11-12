# Fase 00 — Decisiones y modelo

## Decisiones confirmadas

- [x] El add-on es **solo reactivo**: el paciente inicia la conversación; no hay plantillas proactivas.
- [x] No se envían datos clínicos por WhatsApp: solo turnos, información y trámites.
- [x] Toda alta, baja o modificación exige step-up inmediatamente antes de ejecutar.
- [x] El vínculo inicial se crea con verificación fuerte, nunca con “SI”.
- [x] El número es uno compartido de Bioenlace.
- [x] El add-on es opcional, como videollamada, y aplica a todas las clases de atención.
- [x] El chat del paciente es con el asistente, no con personal de salud; no depende de la clase de atención.
- [x] `includes_patient_chat` es un dato viejo y debe retirarse del catálogo de precios.
- [x] Passkey y huella local de la app se prefieren sobre Didit por costo y fuerza.
- [x] TTL de step-up: 10 minutos o consumo, lo que ocurra primero.
- [x] Primer vínculo: Didit contra una Persona existente; no se crea Persona desde WhatsApp. Si no hay match, se rechaza.
- [x] Acción pendiente: tabla con columnas explícitas y el cuerpo en JSON.
- [x] Vínculos viejos creados con “SI”: lectura permitida y step-up obligatorio en la primera escritura.
- [x] Passkey: se ofrece registrarla después de la primera verificación, sin obligar.
- [x] Efector del add-on: se exige al menos un efector activo con el add-on; para una operación atada a un efector, se exige el de esa operación.
- [x] Gate de contenido: un intent solo se responde por WhatsApp si declara `whatsapp: allowed`; por defecto queda bloqueado. Los intents clínicos no se declaran.

## Criterio de salida

No queda ambigüedad sobre métodos de verificación, TTL, migración de vínculos viejos ni resolución del entitlement; los textos viven en un catálogo y no en el servicio.
