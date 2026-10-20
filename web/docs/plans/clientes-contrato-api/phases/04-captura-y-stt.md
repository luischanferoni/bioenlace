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

## Criterio de salida

Prueba de punta a punta en dispositivo real y en navegador. La lógica de aceptación vive solo en `Domain/Clinical/Capture`.
