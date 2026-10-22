# Fase 04 — Captura y STT

## Objetivo

Que el servidor decida qué hacer con el audio y la transcripción. El audio siempre se conserva.

## Tareas

- La configuración de voz devuelve la política completa: qué reconocedores están habilitados, umbral por perfil y si se acepta el texto del dispositivo.
- Contrato de captura: el cliente sube audio y transcripción local si existe. El servidor evalúa calidad, decide si mejora con STT remoto y guarda el borrador.
- Con STT de dispositivo y de servidor habilitados: se transcribe en el dispositivo, se envía todo y, si supera el umbral, se guarda como si lo hubiera transcrito el servidor.
- Con el servidor deshabilitado: nunca se invoca STT remoto; se conservan audio y texto local.
- Estados y etapas de captura como `code`, `label`, `intent`.
- Se retira del cliente la evaluación de calidad y los mensajes de estado de dictado.
- Sujeto genérico: el motor de captura deja de depender de `subjectPersonaId` e `id_persona` y pasa a recibir una referencia de sujeto (tipo e identificador) en el servicio de extracción, la caché de análisis y el resultado. La vista del resultado deja de ser la del paciente humano.
- Prompts: separar la parte común de la específica del sujeto (persona o animal) para que el sujeto sea una variable.
- Cliente móvil: captura simultánea de audio y reconocimiento validada en dispositivo real. La grabación usa el modo compatible de Android y pide el permiso antes de iniciar.

## Avance

Personal de Salud envía el audio y la transcripción local juntos por `captura/crear-o-subir`. El cliente ya no llama a `audio/transcribir` ni fuerza STT remoto según una evaluación local de calidad; el servidor evalúa la metadata recibida y devuelve la etapa de captura, y el cliente usa esa etapa para continuar el flujo. Si hay que transcribir, se usa `captura/transcribir` sobre el audio persistido. Falta completar la política y presentación de etapas como metadata contractual, revisar las demás superficies STT y validar la captura simultánea en un dispositivo móvil real.

## Criterio de salida

Prueba de punta a punta del cliente Flutter en un dispositivo móvil real; el cliente web propio se valida con su stack web, no con Flutter Web. La lógica de aceptación vive solo en `Domain/Clinical/Capture`.
