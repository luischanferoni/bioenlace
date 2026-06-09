# Fase 4 — API, móvil y asistente

**Estado:** implementada

## Asistente

| Artefacto | Ubicación |
|-----------|-----------|
| Flow pre-consulta | `schemas/intents/care-packs.asistencia-pre-consulta-flow.yaml` |
| Catálogo acciones | `Catalog/CarePackUiActionCatalog.php` → `UiActionCatalog` |
| API runtime | `GET|POST /api/v1/care-packs/assistance` |

Flujo: elegir turno pendiente → formulario dinámico del pack → POST respuestas.

## App paciente (Flutter)

| Pantalla | CTA |
|----------|-----|
| Inicio (`home_screen.dart`) | «Cuestionario pre-consulta» en turnos futuros con ventana abierta |
| Mis turnos | Idem |
| Navegación compartida | `shared/lib/clinical/care_pack_navigation.dart` |

Flag API en listado de turnos: `asistencia_cohorte_disponible` (misma ventana que motivos).

## Staff — cohorte y respuestas

| Canal | Detalle |
|-------|---------|
| API | `GET /api/v1/personas/{id}/historia-clinica` incluye `care_pack_cohorte` |
| Servicio | `CarePackEncounterStaffService` |
| Web timeline | bloque «Asistencia pre-consulta (cohorte)» en `timeline.php` |
| App médico | tarjeta en `patient_timeline_screen.dart` |

Sin endpoint staff dedicado: reutiliza permiso `historia-clinica` y `EncounterAccessService`.

## Prueba local

1. `care_cohort.enabled = true` en `params.php`
2. Cron: `php yii care-pack/run-jobs`
3. Paciente: turno con encounter → botón pre-consulta o asistente «cuestionario pre consulta»
4. Médico: historia clínica del turno → bloque cohorte con respuestas
