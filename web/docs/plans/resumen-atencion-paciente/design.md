# Design — Resumen de atención (paciente)

## Decisión central: qué ve el paciente como “resumen”

| Fuente | ¿Usar en resumen paciente? |
|--------|----------------------------|
| Texto libre del médico (`texto_original`) | **No** — puede estar mal formateado o ser confuso. |
| Códigos / términos SNOMED (`condition`, etc.) | **No** como cuerpo principal — solo metadatos o enlaces internos si hace falta. |
| **Texto corregido/enriquecido por IA** (`texto_procesado`) | **Sí** — es el contenido narrativo del resumen. |

### Anclaje en código actual

1. `POST …/clinical/encounter/analizar` → `ConsultaProcesamientoService::analizar` (formato + IA).
2. `POST …/clinical/encounter/guardar` → `EncounterDocumentationService::guardar` persiste `encounter.note` desde `body['texto_procesado']` (o `observacion`).
3. Al **publicar** al paciente (job T+Δ), tomar **`encounter.note`** como fuente y opcionalmente **congelar** en snapshot (tabla o JSON) para que cambios posteriores del staff no alteren lo ya notificado sin republicar.

Si `note` está vacío al cerrar (consulta sin pasar por guardar con IA), el resumen puede mostrar solo **artefactos estructurados** (receta, pedidos) + mensaje “Resumen narrativo no disponible”.

## Publicación automática (sin OK médico)

```
close(encounter) → status=finished
    → encolar PublishPatientEncounterSummaryJob(run_at = now + Δ)
    → job: validar AMB + finished + note/artefactos
    → guardar snapshot + patient_summary_published_at
    → FCM + persona_notificacion
```

- **Δ recomendado:** 3–5 minutos (receta emitida y `service_request` ya persistidos).
- Si el encounter deja de estar `finished` antes del job, cancelar o reprogramar.
- Republicación: si se reabre y vuelve a cerrar, nueva versión de snapshot + opcional segunda notificación (definir en Fase 1).

## Alcance institucional

- El paciente **no** está ligado a un efector.
- Listados y detalle filtran por `subject_persona_id = getIdPersona()`.
- Cada ítem muestra **efector + profesional + fecha** para contexto.

## Modelo de pantalla: atención + artefactos

```text
PatientEncounterSummary
├── encounterId, periodEnd, efector, profesional, servicio
├── narrativeText          ← snapshot de encounter.note (IA)
├── prescriptions[]        ← electronic_prescription issued
├── orders[]               ← service_request (lab, imagen, referral…)
├── carePlanSummary?       ← plan agudo del encounter si existe
└── links
    ├── childEncounters[]  ← derivación con otro encounter finished (si hay)
    └── appointments[]     ← turno de seguimiento sin encounter aún
```

**Laboratorio:** `diagnostic_report` con `encounter_id` → en detalle del informe, bloque `relatedEncounter` con teaser del resumen + CTA “Ver atención donde se pidió”.

## Seguridad

| Control | Detalle |
|---------|---------|
| Estado | Solo `finished`; nunca `in-progress`. |
| Clase | Solo `AMB` en v1. |
| Autorización | `subject_persona_id === getIdPersona()`; acciones `*-como-paciente`. |
| Push | Payload sin PHI: `type`, `encounter_id`. |
| Auditoría | Log lecturas paciente; expediente legal staff con rol dedicado (Fase 5). |
| Access service | `EncounterAccessService` permite paciente en cualquier estado — los endpoints nuevos **añaden** filtro `finished`. |

## API (convención propuesta)

| Método | Ruta | Uso |
|--------|------|-----|
| GET | `/api/v1/clinical/encounter/listar-atenciones-como-paciente` | Lista paginada AMB finished |
| GET | `/api/v1/clinical/encounter/ver-resumen-como-paciente` | Detalle + grafo (`encounter_id`) |
| GET | `/api/v1/clinical/encounter/ultima-atencion-como-paciente` | Atajo última por `period_end` |

RBAC ghost: `/api/clinical/encounter/…` (sin `v1`).

## Código objetivo (cuando se implemente)

| Área | Ubicación sugerida |
|------|---------------------|
| Builder + snapshot | `common/components/Clinical/PatientSummary/` |
| Job | `console` o queue Yii |
| API | `clinical/EncounterPatientSummaryController` o acciones en `EncounterController` |
| Push | `PushNotificationTypes::ENCOUNTER_SUMMARY_READY` |
| UI JSON | `frontend/modules/api/v1/views/json/clinical/encounter/` |

## Expediente legal (staff, Fase 5)

- Cola async (generación PDF/ZIP amplio).
- Rol RBAC a definir (ej. `ExpedienteLegalGenerar`).
- Notificación al staff cuando está listo para descarga — **no** inmediato.
- Fuera de la app paciente.

## Alternativas descartadas

- Resumen = dump de `datosExtraidos` SNOMED → ilegible para paciente.
- Esperar autorización médico → fricción; sustituido por reglas de sistema + texto IA.
- Un solo endpoint “historia clínica” paciente mezclando todo → se separa lista, detalle y expediente staff.
