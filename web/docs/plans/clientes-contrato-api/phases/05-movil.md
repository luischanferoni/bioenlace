# Fase 05 — Móvil

## Objetivo

Dos apps delgadas sobre `mobile/packages/shared`, sin textos de negocio ni vocabulario clínico fijos.

## Avance

Personal de Salud obtiene las capacidades de la clase encounter desde el catálogo y las usa para seleccionar el contenido del inicio, navegación de fecha, acciones de internación y polling de guardia. El wizard y la configuración de área usan la metadata específica de cada efector y descartan clases no disponibles, evitando ofrecer opciones basadas solo en la unión global. En captura clínica, audio y transcripción local se envían por el pipeline de captura para que el servidor resuelva STT. Falta migrar las demás pantallas y contratos indicados en esta fase.

`DynamicForm` y `SearchableCardSelector` ahora viven en `mobile/packages/shared`; ambas apps conservan sus imports locales como reexportaciones y pasan su identificador `appClient` explícitamente para mantener intacta la identidad HTTP de cada aplicación.

El transporte de acciones async (`cancelar-como-paciente`, `tomar-como-staff`, `cerrar-como-staff`) también se centralizó en `EncounterAsyncClient` dentro de shared. Las dos apps mantienen wrappers locales compatibles que fijan su `appClient`; la cobertura compartida verifica la identidad de cada app, el cuerpo de cierre con nota y el mensaje de error del servidor.

El servicio de chat de encounter (`mensajes`, `enviar` y `subir`) ahora se centraliza en `EncounterChatClient`. Los wrappers locales mantienen el rol predeterminado de paciente o médico, y el cliente compartido permite inyectar HTTP para verificar transporte y cargas multipart en pruebas.

El chat del asistente y el modelo `Message` también se centralizaron en shared. Las apps mantienen sus imports y clases locales como reexportación/wrapper, y el cliente fija el `appClient` correspondiente a cada una.

Los clientes de chat y acciones async interpretan los errores estándar con `ApiContractException` y devuelven `message` más el objeto estructurado (`code`, `intent`, `retryable` y `field_errors`) para que los consumidores existentes sigan funcionando y los nuevos puedan reaccionar a metadata sin inspeccionar texto. Las respuestas legacy `{success, message}` se conservan durante esta migración; falta generalizar este comportamiento al resto de clientes móviles y centralizar su transporte.

## Tareas

- Mover a shared lo duplicado: formulario dinámico, selector de tarjetas, servicios de chat y de atención asíncrona.
- Cliente HTTP único en shared que interpreta el contrato y expone un fallo con `code`, `message`, `intent`, `retryable`.
- Archivo único de textos de infraestructura por app. Se elimina la traducción heurística de errores por contenido de texto.
- Registro de módulos por ámbito de atención en Personal de Salud (ver fase 7).
- Reescribir la pantalla de timeline del paciente en componentes: banner de episodio, secciones clínicas, cola de capturas, captura de voz. Todos alimentados por la API.
- Pantallas nativas solo cuando el hardware lo exige (captura, escáner de DNI, cámara); el resto por UI JSON.
- App Paciente: representación de persona o mascota como sujeto activo, enviado en cada request (ver fase 7).
- Regla de lint y guarda de CI contra textos literales fuera del archivo de infraestructura.

## Criterio de salida

`flutter analyze` sin avisos nuevos, tests de shared y contadores de móvil en su nuevo tope.
