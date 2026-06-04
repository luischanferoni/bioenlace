# Triage al reservar turno (paciente)

## Objetivo

Antes de elegir **servicio y horario**, el paciente responde un **árbol fijo** en lenguaje simple: motivo, alarmas de seguridad, zona y evolución del malestar. No es diagnóstico; alimenta el turno y la preparación del encuentro (y complementa los [motivos pre-consulta](./motivos-consulta.md) posteriores).

## Principios

1. **Seguridad primero:** preguntas de alarma con **banda A** → no se completa la reserva; pantalla de derivación a urgencia / 107.
2. **Catálogo declarativo:** nodos en `web/common/components/Scheduling/metadata/reserva_triage_catalog_v1.yaml` (códigos internos, etiquetas para el usuario).
3. **Sin hardcode en orquestadores:** el flujo conversacional está en `turnos.crear-como-paciente.yaml`; la lógica de compilación en `ReservaTurnoTriageCatalogService` y `scheduling.reserva_triage` (`FlowDraftHydratorRegistry`).
4. **IA opcional después:** texto libre en confirmación (`triage_nota`); el lote de motivos pre-consulta sigue siendo el canal rico de IA.

## Intent del asistente

- **`atencion.necesito-atencion`** — flujo principal (atajo primero en el chat): triage + reserva ambulatoria.
- **`turnos.crear-como-paciente`** — solo agenda (sin triage); clasificación por frases tipo “sacar turno”.

## Pasos del flujo (asistente / SPA)

| Paso | Subintent | API UI |
|------|-----------|--------|
| Motivo (raíz) | `triage_raiz` | `GET /api/v1/turnos/reserva-triage-paso?step=raiz` |
| Alarmas | `triage_alarmas` | `?step=alarmas` |
| Urgencia (solo banda A) | `triage_urgencia` | `GET /api/v1/turnos/reserva-triage-urgencia` |
| Zona corporal | `triage_zona` | `?step=zona&triage_raiz=…` |
| Detalle | `triage_detalle` | `?step=detalle&triage_zona=…` |
| Evolución | `triage_evolucion` | `?step=evolucion` |
| Servicio → … → confirmar | (sin cambios) | flujo turnos existente |

**Atajos por motivo raíz:**

- `tramite_admin` → salta triage clínico y va directo a servicio.
- `control_cronico` → alarmas + evolución (sin zona/detalle).
- `sintoma_nuevo` → recorrido completo.

## Persistencia

Columnas en `turnos` (migración `m260602_150000_turnos_reserva_triage_columns`):

| Columna | Uso |
|---------|-----|
| `reserva_triage_code` | Código hoja principal (última selección significativa) |
| `urgency_band` | `A`–`D` (máxima banda del recorrido) |
| `reserva_triage_meta_json` | Trayectoria (`path`) + versión de catálogo |

Validación al crear: `TurnoPersistService` + `assertCanPersistBooking` (rechaza banda A y campos incompletos según raíz).

## API auxiliar

- `GET /api/v1/turnos/reserva-triage-catalogo` — metadatos de pasos (clientes nativos).
- Permisos RBAC heredados de `crear-como-paciente` (`m260602_150001_api_turnos_reserva_triage_rbac`).

## Bandas (orientativo)

| Banda | Significado operativo |
|-------|----------------------|
| A | Alarma actual → no reservar en app |
| B | Prioridad alta / evaluar presencial pronto |
| C | Ambulatorio programable habitual |
| D | Control / trámite / baja urgencia |

## Relación con teleconsulta

Algunos nodos sugieren `tipo_atencion: teleconsulta` (p. ej. control crónico, dolor muscular por esfuerzo). El paciente **confirma** modalidad en la pantalla final; la agenda debe aceptar teleconsulta en el PES elegido.

## Evolución prevista

- Filtrar servicios ofrecidos según `urgency_band` / código (metadata, no `if` en controller).
- Repreguntas IA solo sobre `triage_nota` o texto libre.
- Reutilizar el mismo catálogo en app móvil paciente sin duplicar árbol en Dart.

Ver también: [turnos.md](./turnos.md), [motivos-consulta.md](./motivos-consulta.md).
