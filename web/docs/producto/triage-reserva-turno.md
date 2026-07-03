# Triage al reservar turno (paciente)

## Objetivo

Antes de elegir **servicio y horario**, el paciente responde un **árbol fijo** en lenguaje simple: motivo, alarmas de seguridad, zona y evolución del malestar. No es diagnóstico; alimenta el turno y la preparación del encuentro (y complementa los [motivos pre-consulta](./motivos-consulta.md) posteriores).

## Principios

1. **Seguridad primero:** preguntas de alarma con **banda A** → no se completa la reserva; pantalla de derivación a urgencia / 107.
2. **Catálogo declarativo:** nodos en `web/common/components/Domain/Scheduling/metadata/reserva_triage_catalog_v1.yaml` (códigos internos, etiquetas para el usuario).
3. **Sin hardcode en orquestadores:** el flujo conversacional está en `atencion.necesito-atencion.yaml`; la lógica de compilación en `ReservaTurnoTriageCatalogService`, elegibilidad remota en `TeleconsultaElegibilidadService` y enriquecimiento vía `scheduling.reserva_triage` (`FlowDraftHydratorRegistry`).
4. **IA opcional después:** texto libre en confirmación (`triage_nota`); el lote de motivos pre-consulta sigue siendo el canal rico de IA.

## Intent del asistente

- **`atencion.necesito-atencion`** — malestar nuevo o urgencia (atajo en el chat): triage + reserva ambulatoria.
- **`atencion.consultas-seguimiento-flow`** — consulta general o seguimiento de tratamiento (app paciente); ver [consultas-seguimiento.md](./consultas-seguimiento.md).
- **`turnos.crear-como-paciente`** — solo agenda (sin triage); clasificación por frases tipo “sacar turno”.

## Pasos del flujo (asistente / SPA)

| Paso | Subintent | API UI |
|------|-----------|--------|
| Motivo (raíz) | `triage_raiz` | `GET /api/v1/turnos/reserva-triage-paso?step=raiz` — **Malestar nuevo** y **Urgencia** (sin «Seguimiento»; ese camino está en consultas-seguimiento) |
| Alarmas | `triage_alarmas` | `?step=alarmas` |
| Urgencia (solo banda A) | `triage_urgencia` | `GET /api/v1/turnos/reserva-triage-urgencia` |
| Zona corporal | `triage_zona` | `?step=zona&triage_raiz=…` |
| Detalle | `triage_detalle` | `?step=detalle&triage_zona=…` |
| Evolución | `triage_evolucion` | `?step=evolucion` |
| Servicio | `select_servicio` | `servicios.elegir-acepta-turnos` (+ query triage para filtrar por rol sugerido) |
| Modalidad (si aplica) | `select_tipo_atencion` | `?step=modalidad&id_servicio_asignado=…` + campos triage |
| Centro → profesional → día → horario | (sin cambios) | flujo turnos existente |

**Atajos por motivo raíz (necesito-atención):**

- `malestar_nuevo` → zona → modalidad → servicio.
- `urgencia` → categoría → pantalla de derivación (sin reserva).

El código `seguimiento_cronico` permanece en el catálogo para persistencia y el flow **Consultas y seguimiento** (`ui_selectable: false` en raíz).

**Servicio (Medicina clínica hub):**

- Tras el triage ambulatorio, el paciente solo ve **Medicina clínica / generalistas** (`reserva_modo=hub_paciente`).
- Especialistas: turno solo con **derivación vigente** del clínico; modalidad **solo teleconsulta**.
- Detalle: [medicina-clinica-hub-reserva.md](./medicina-clinica-hub-reserva.md).

**Modalidad (presencial / remoto) con clínica:**

- El paso aparece **después del servicio**, solo si `teleconsulta_ofercible = 1` en el draft (hydrator).
- Si el servicio o el triage no permiten remoto, se fija `tipo_atencion = presencial` y se salta la pantalla.
- Detalle de reglas: [teleconsulta-elegibilidad.md](./teleconsulta-elegibilidad.md).

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

- Política por servicio: `servicios.teleconsulta_politica` (`ninguna` | `todas` | `algunas`) y allowlist `servicio_teleconsulta_caso`.
- Nodos del catálogo pueden marcar `teleconsulta_elegibilidad` o sugerir `tipo_atencion: teleconsulta`.
- El profesional habilita teleconsulta en su PES con `acepta_consultas_online` al configurar agenda.
- Al reservar remoto, el listado de profesionales filtra solo quienes aceptan consultas online.

## Evolución prevista

- Repreguntas IA solo sobre `triage_nota` o texto libre.
- Reutilizar el mismo catálogo en app móvil paciente sin duplicar árbol en Dart.
- Rol `oftalmologia` en nodos oculares del catálogo cuando se agreguen ramas de triage oftalmológico.

Ver también: [turnos.md](./turnos.md), [teleconsulta-elegibilidad.md](./teleconsulta-elegibilidad.md), [motivos-consulta.md](./motivos-consulta.md).
