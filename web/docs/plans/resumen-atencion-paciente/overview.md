# Overview — Resumen de atención (paciente)

## Objetivo

Cerrar el ciclo post-consulta: el paciente entiende qué ocurrió en su atención, qué debe hacer después y cómo acceder a recetas, pedidos y resultados vinculados — sin descargar toda la historia clínica ni ver el texto crudo del profesional.

## Actores

| Actor | Rol |
|-------|-----|
| **Paciente** | Recibe push ~minutos después de finalizada la atención; consulta última atención o listado; navega vínculos (receta, lab, derivación). |
| **Sistema** | Job diferido post-`close`, snapshot del resumen, FCM + notificación in-app. |
| **Staff** | (Fase 5) Solicita expediente legal; recibe aviso cuando el PDF está listo. |
| **Profesional** | Sin paso extra de “autorizar envío”; el resumen publicado es el texto ya procesado por IA al guardar. |

## Alcance v1 (ambulatorio)

- Encounters `encounter_class = AMB`, `status = finished`.
- Paciente identificado por `getIdPersona()` — **sin** vínculo a un efector; puede atenderse en cualquier efector de Bioenlace.
- Resumen narrativo = **texto IA** (`texto_procesado` persistido en `encounter.note`), no el dictado crudo ni listados SNOMED como cuerpo principal.
- Publicación automática **T + Δ** (p. ej. 3–5 min) tras `EncounterLifecycleService::close`, sin confirmación del médico.
- Artefactos enlazados por `encounter_id`: receta electrónica emitida, `service_request` (lab, imagen, derivación), care plan agudo si aplica.
- Segundo momento: push de **resultados de laboratorio** con enlace a la atención donde se solicitó el estudio.

## Fuera de alcance v1

- Internación, guardia, OBSENC (solo ambulatorio).
- Descarga de expediente legal por el paciente.
- Historia clínica completa en un solo PDF.
- Reutilizar `GET personas/{id}/historia-clinica` (endpoint staff, vacío de eventos, lógica por efector en sesión).
- Incluir en el push diagnósticos o texto clínico (solo ids; detalle en app autenticada).

## Fases

| Fase | Entrega |
|------|---------|
| 0 | Marco y decisiones (este plan) |
| 1 | Snapshot resumen, API listar/ver como paciente, job publicación post-cierre |
| 2 | FCM `ENCOUNTER_SUMMARY_READY`, `persona_notificacion` |
| 3 | UI JSON + intents asistente + pantallas Flutter |
| 4 | Grafo de vínculos (lab ↔ encounter, derivaciones, pedidos) |
| 5 | Cola expediente legal staff + rol RBAC + notificación staff |

## Dependencias

- Encounter FHIR cerrado (`EncounterLifecycleService::close`).
- Texto IA disponible en `encounter.note` al guardar documentación (`texto_procesado`).
- Receta electrónica y laboratorio paciente ya operativos (enlaces, no reimplementar).

## Doc operativa al cerrar

Mover resumen estable a `web/docs/dominio/flows/resumen-atencion-paciente.md` y eliminar esta carpeta según [design.md](../design.md).
