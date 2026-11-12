# Fase 00 — Decisiones y modelo

## Decisiones confirmadas

- [x] El add-on es **solo reactivo**: el paciente inicia la conversación; no hay plantillas proactivas.
- [x] No se envían datos clínicos por WhatsApp: solo turnos, información y trámites.
- [x] Toda alta, baja o modificación se completa en la app, que exige huella local o passkey justo antes de ejecutar. WhatsApp no ejecuta escrituras: deriva a la app.
- [x] El vínculo inicial se crea con verificación fuerte, nunca con “SI”.
- [x] El número es uno compartido de Bioenlace.
- [x] El add-on es opcional, como videollamada, y aplica a todas las clases de atención.
- [x] El chat del paciente es con el asistente, no con personal de salud; no depende de la clase de atención.
- [x] `includes_patient_chat` es un dato viejo y debe retirarse del catálogo de precios.
- [x] Passkey y huella local de la app se prefieren sobre Didit por costo y fuerza.
- [x] No hay step-up biométrico dentro de WhatsApp en esta entrega; el estado de assurance queda reservado para una fase futura.
- [x] Primer vínculo: Didit contra una Persona existente; no se crea Persona desde WhatsApp. Si no hay match, se rechaza.
- [x] No se persiste una acción pendiente: las escrituras se abren en la app.
- [x] Vínculos viejos creados con “SI”: lectura permitida y escritura derivada a la app.
- [x] Passkey: se ofrece registrarla después de la primera verificación, sin obligar.
- [x] Efector del add-on: se exige al menos un efector activo con el add-on; para una operación atada a un efector, se exige el de esa operación.
- [x] Gate de contenido: un intent solo se responde por WhatsApp si está declarado en `Channels/WhatsApp/channel-scope.yaml`; por defecto queda bloqueado. Los intents clínicos no se declaran.

## Criterio de salida

No queda ambigüedad sobre métodos de verificación, TTL, migración de vínculos viejos ni resolución del entitlement; los textos viven en un catálogo y no en el servicio.
