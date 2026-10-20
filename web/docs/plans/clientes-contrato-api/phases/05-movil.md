# Fase 05 — Móvil

## Objetivo

Dos apps delgadas sobre `mobile/packages/shared`, sin textos de negocio ni vocabulario clínico fijos.

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
