# Fase 04 — Captura y STT

## Objetivo

Que el servidor decida qué hacer con el audio y la transcripción. El audio siempre se conserva.

## Tareas

- La configuración de voz devuelve la política completa: qué reconocedores están habilitados, umbral por perfil y si se acepta el texto del dispositivo.
- Contrato de captura: el cliente sube audio y transcripción local si existe. El servidor evalúa calidad, decide si mejora con STT remoto y guarda el borrador.
- Con STT de dispositivo y de servidor habilitados: se transcribe en el dispositivo, se envía todo y, si supera el umbral, se guarda como si lo hubiera transcrito el servidor.
- Con el servidor deshabilitado: nunca se invoca STT remoto; se conservan audio y texto local.
- Estados y etapas de captura como `code`, `label`, `intent`.
- Se retira del cliente la decisión de calidad para escoger entre audio o texto; el servidor evalúa la metadata y decide si acepta el texto o procesa el audio.
- Sujeto genérico: el motor de captura deja de depender de `subjectPersonaId` e `id_persona` y pasa a recibir una referencia de sujeto (tipo e identificador) en el servicio de extracción, la caché de análisis y el resultado. La vista del resultado deja de ser la del paciente humano.
- Prompts: separar la parte común de la específica del sujeto (persona o animal) para que el sujeto sea una variable.
- Cliente móvil: captura simultánea de audio y reconocimiento validada en dispositivo real. La grabación usa el modo compatible de Android y pide el permiso antes de iniciar.

## Avance

Personal de Salud envía el audio y la transcripción local juntos por `captura/crear-o-subir`. El cliente ya no llama a `audio/transcribir` ni fuerza STT remoto según una evaluación local de calidad; el servidor evalúa la metadata recibida y devuelve la etapa de captura junto con `stage_metadata` (`code`, `label`, `intent`), y el cliente usa esa etapa para continuar el flujo y presenta la etiqueta/intención recibidas en la cola local. Si hay que transcribir, se usa `captura/transcribir` sobre el audio persistido.

El chat de motivos de la app Paciente ahora conserva el audio y la transcripción local en un mismo mensaje, incluso en la cola de reintentos. El endpoint `encounter-reasons/subir` persiste la transcripción y la metadata STT en columnas separadas, sin sobrescribir la ruta del archivo. El servidor evalúa la transcripción con el perfil `motivos_consulta`: si es aceptable se reutiliza al generar el resumen; si requiere mejora y el STT servidor está habilitado, el lote transcribe el audio guardado; con STT servidor deshabilitado, el lote nunca lo invoca y conserva el texto local disponible. La interfaz del chat muestra la transcripción junto al audio.

Pendiente de implementación: el motor de captura todavía modela el sujeto como `subjectPersonaId` y conserva `id_persona`/`subject_persona_id` como identidad de la captura y la caché; la referencia genérica `tipo + identificador` de sujeto, necesaria para veterinaria, no está implementada. La prueba de captura simultánea en un dispositivo real queda como validación, no como bloqueo de cierre una vez completado el comportamiento.

Bloqueo documentado (no implementar todavía): el sujeto genérico toca 13 archivos de `Domain/Clinical/Capture` (modelo, repositorio, caché, extracción y presentadores) y depende del diseño veterinario, aún hipotético (ver `design.md`). Separar la parte común y la específica del sujeto en los prompts requiere edición manual, por la regla de prompts reservados. Hasta definir el sujeto veterinario, la fase 04 queda con el comportamiento de captura y STT terminado y este punto abierto.

## Criterio de salida

Prueba de punta a punta del cliente Flutter en un dispositivo móvil real; el cliente web propio se valida con su stack web, no con Flutter Web. La lógica de aceptación vive solo en `Domain/Clinical/Capture`.
