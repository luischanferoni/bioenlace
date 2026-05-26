# QA — Asistente (intents YAML)

[← Índice](./README.md) · Arquitectura: [asistente-motores.md](../arquitectura/asistente-motores.md) · Producto: [asistente-y-chat.md](../producto/asistente-y-chat.md)

Un caso **`CU-AST-NNN`** por cada intent en  
`web/common/components/Assistant/SubIntentEngine/schemas/intents/*.yaml` (**29** archivos).

---

## Cómo probar (común a todos)

| Superficie | Pasos genéricos |
|------------|-----------------|
| **Web asistente** | Login → abrir SPA asistente → escribir frase de `keywords` del YAML o elegir acción del catálogo |
| **Flutter paciente** | Login paciente → chat/asistente → misma frase |
| **Discovery** | Verificar que el intent aparece para el rol correcto (staff vs paciente) |
| **RBAC** | Usuario **sin** permiso en `rbac_route` → 403; con permiso → flujo avanza |
| **Flows** | Completar cada subintent (`assistant_text`); al final `flow_submit` debe devolver éxito o error de negocio claro |
| **API directa** | Opcional: llamar `action_id` / `rbac_route` con JWT y body del draft para aislar fallo UI vs API |

**Registro:** completar tabla al final de cada caso o centralizar en hoja con columna `CU-AST-xxx`.

---

## Índice rápido

| ID | Intent | P | Dominio |
|----|--------|---|---------|
| CU-AST-001 | `agenda.crear-profesional-flow` | P1 | Agenda |
| CU-AST-002 | `agenda.editar-agenda-flow` | P1 | Agenda |
| CU-AST-003 | `agenda.resolver-conflictos-staff-flow` | P1 | Agenda |
| CU-AST-004 | `atencion.mis-atenciones-como-paciente` | P1 | Atención |
| CU-AST-005 | `atencion.ver-ultima-como-paciente` | P1 | Atención |
| CU-AST-006 | `internacion.alta-estructurada-flow` | P0 | Internación |
| CU-AST-007 | `internacion.cambio-cama-flow` | P1 | Internación |
| CU-AST-008 | `internacion.ingreso-flow` | P0 | Internación |
| CU-AST-009 | `internacion.mapa-camas-flow` | P0 | Internación |
| CU-AST-010 | `laboratorio.ver-resultados-como-paciente` | P0 | Laboratorio |
| CU-AST-011 | `receta.ver-recetas-como-paciente` | P1 | Receta |
| CU-AST-012 | `tratamiento.adherencia-resumen-staff` | P1 | Tratamiento |
| CU-AST-013 | `tratamiento.recordatorios-como-paciente` | P1 | Tratamiento |
| CU-AST-014 | `turnos.cancelar-como-paciente-flow` | P0 | Turnos paciente |
| CU-AST-015 | `turnos.cancelar-para-paciente-flow` | P1 | Turnos staff |
| CU-AST-016 | `turnos.conflicto-agenda-flow` | P1 | Turnos |
| CU-AST-017 | `turnos.confirmar-asistencia-flow` | P2 | Turnos |
| CU-AST-018 | `turnos.consultar-ocupacion-dia-flow` | P1 | Turnos |
| CU-AST-019 | `turnos.consultar-politica-autogestion-flow` | P1 | Turnos |
| CU-AST-020 | `turnos.crear-como-paciente` | P0 | Turnos paciente |
| CU-AST-021 | `turnos.crear-para-paciente-flow` | P0 | Turnos staff |
| CU-AST-022 | `turnos.crear-sobreturno-flow` | P1 | Turnos |
| CU-AST-023 | `turnos.indicadores-agenda-flow` | P1 | Turnos |
| CU-AST-024 | `turnos.modificar-como-paciente-flow` | P0 | Turnos paciente |
| CU-AST-025 | `turnos.no-se-presento-flow` | P1 | Turnos |
| CU-AST-026 | `turnos.reubicar-como-paciente-flow` | P1 | Turnos paciente |
| CU-AST-027 | `turnos.ver-agenda-dia-profesional-flow` | P1 | Turnos staff |
| CU-AST-028 | `urgencias.triage-paciente-guardia` | P0 | Urgencias |
| CU-AST-029 | `urgencias.ver-tablero-guardia` | P0 | Urgencias |

---

## Agenda

### CU-AST-001 — `agenda.crear-profesional-flow`

| Campo | Valor |
|-------|-------|
| **Intent** | `agenda.crear-profesional-flow` |
| **RBAC** | `/api/profesional-agenda/crear-agenda-flow` |
| **Prioridad** | P1 |
| **Actor** | Admin RRHH / coordinación |

**Frases de prueba:** «alta profesional», «crear agenda en el efector».

**Pasos:** Iniciar flow → completar datos profesional + PES + bloques agenda → submit. Verificar enfermería/coordinación puede enlazar a `agenda.editar-agenda-flow` si el YAML lo ofrece.

**Esperado:** Profesional con agenda visible en CU-TUR-011 / CU-AST-027.

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

### CU-AST-002 — `agenda.editar-agenda-flow`

| **RBAC** | `/api/profesional-agenda/editar-agenda-flow` | **P** | P1 |

**Pasos:** Elegir profesional existente → modificar horario/bloque → guardar. Revisar slots del día afectado.

**Esperado:** Cambios reflejados en ocupación (CU-AST-018).

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

### CU-AST-003 — `agenda.resolver-conflictos-staff-flow`

| **RBAC** | `/api/profesional-agenda/resolver-conflicto-agenda-para-paciente` | **P** | P1 |

**Precondición:** Turnos afectados por cambio de agenda (conflicto generado).

**Pasos:** Staff resuelve conflicto eligiendo reubicación/cancelación según UI del flow.

**Esperado:** Turnos actualizados; paciente notificado si aplica (CU-TUR-015).

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## Atención (paciente)

### CU-AST-004 — `atencion.mis-atenciones-como-paciente`

| **RBAC** | `/api/clinical/encounter/mis-atenciones-como-paciente` | **P** | P1 |

**Precondición:** Paciente con ≥2 encounters cerrados.

**Pasos:** Login paciente → «mis atenciones» / intent → listado.

**Esperado:** Lista cronológica; sin datos de otro paciente.

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

### CU-AST-005 — `atencion.ver-ultima-como-paciente`

| **RBAC** | `/api/clinical/encounter/ultima-atencion-ui-como-paciente` | **P** | P1 |

**Esperado:** UI JSON de última atención (resumen publicado, no borrador crudo).

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## Internación

### CU-AST-006 — `internacion.alta-estructurada-flow`

| **RBAC** | `/api/clinical/internacion/alta-formulario` | **P** | P0 |

**Precondición:** Internación activa; sesión IMP (CU-TR-002).

**Pasos:** Flow → plantilla epicrisis → campos obligatorios → confirmar alta.

**Esperado:** CU-IMP-006; cama libre.

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

### CU-AST-007 — `internacion.cambio-cama-flow`

| **RBAC** | `/api/clinical/internacion/cambio-cama-formulario` | **P** | P1 |

**Esperado:** CU-IMP-005.

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

### CU-AST-008 — `internacion.ingreso-flow`

| **RBAC** | `/api/clinical/internacion/ingreso-formulario` | **P** | P0 |

**Pasos:** Paciente + cama libre + datos ingreso → submit.

**Esperado:** CU-IMP-003.

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

### CU-AST-009 — `internacion.mapa-camas-flow`

| **RBAC** | `/api/clinical/internacion/mapa-camas` | **P** | P0 |

**Esperado:** Mapa interactivo equivalente a CU-IMP-001.

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## Laboratorio y receta (paciente)

### CU-AST-010 — `laboratorio.ver-resultados-como-paciente`

| **RBAC** | `/api/clinical/laboratory-result/mis-resultados-como-paciente` | **P** | P0 |

**Precondición:** `observation` / resultados de laboratorio para `id_persona` del token.

**Esperado:** CU-LAB-001.

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

### CU-AST-011 — `receta.ver-recetas-como-paciente`

| **RBAC** | `/api/clinical/electronic-prescription/mis-recetas-como-paciente` | **P** | P1 |

**Esperado:** CU-REC-002; PDF/descarga si la UI lo expone.

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## Tratamiento

### CU-AST-012 — `tratamiento.adherencia-resumen-staff`

| **RBAC** | `/api/clinical/care-plans/adherencia-resumen-staff` | **P** | P1 |

**Actor:** Staff con care plans en el efector.

**Esperado:** Resumen adherencia; CU-PLAN-003.

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

### CU-AST-013 — `tratamiento.recordatorios-como-paciente`

| **RBAC** | `/api/clinical/care-plan/preferencias-recordatorios-como-paciente` | **P** | P1 |

**Esperado:** CU-PLAN-002; preferencias persistidas.

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## Turnos — paciente

### CU-AST-020 — `turnos.crear-como-paciente`

| **RBAC** | `/api/turnos/crear-como-paciente` | **P** | P0 |

**Subintents:** servicio → efector → profesional → día → horario (`flow_submit` con `slot_id`).

**Frases:** «sacar turno», «reservar cita».

**Esperado:** CU-TUR-004; turno en BD.

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

### CU-AST-014 — `turnos.cancelar-como-paciente-flow`

| **RBAC** | `/api/turnos/cancelar-como-paciente` | **P** | P0 |

**Precondición:** Turno futuro dentro de política de cancelación.

**Esperado:** CU-TUR-005.

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

### CU-AST-024 — `turnos.modificar-como-paciente-flow`

| **RBAC** | `/api/turnos/reprogramar-como-paciente` | **P** | P0 |

**Esperado:** CU-TUR-006; nuevo slot válido.

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

### CU-AST-026 — `turnos.reubicar-como-paciente-flow`

| **RBAC** | `/api/turnos/reubicar-como-paciente` | **P** | P1 |

**Contexto:** Reubicación por conflicto de agenda (enlace con CU-AST-016).

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

### CU-AST-016 — `turnos.conflicto-agenda-flow`

| **RBAC** | `/api/turnos/resolver-conflicto-agenda-como-paciente` | **P** | P1 |

**Precondición:** Notificación o estado de conflicto en turno del paciente.

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

### CU-AST-019 — `turnos.consultar-politica-autogestion-flow`

| **RBAC** | `/api/turnos/politica-como-paciente` | **P** | P1 |

**Esperado:** Texto/reglas de anticipación mínima para cancelar/modificar.

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

### CU-AST-017 — `turnos.confirmar-asistencia-flow`

| **RBAC** | `/api/turnos/confirmar-asistencia-como-paciente` | **P** | P2 |

**Esperado:** CU-TUR-014.

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## Turnos — staff

### CU-AST-021 — `turnos.crear-para-paciente-flow`

| **RBAC** | `/api/turnos/crear-para-paciente` | **P** | P0 |

**Actor:** Staff; paciente identificado en draft.

**Esperado:** CU-TUR-003.

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

### CU-AST-015 — `turnos.cancelar-para-paciente-flow`

| **RBAC** | `/api/turnos/cancelar-operativo` | **P** | P1 |

**Esperado:** Cancelación staff sin restricción de autogestión del paciente (auditoría).

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

### CU-AST-022 — `turnos.crear-sobreturno-flow`

| **RBAC** | `/api/turnos/crear-sobreturno` | **P** | P1 |

**Esperado:** CU-TUR-009.

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

### CU-AST-025 — `turnos.no-se-presento-flow`

| **RBAC** | `/api/turnos/no-se-presento` | **P** | P1 |

**Esperado:** CU-TUR-010; estado turno actualizado.

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

### CU-AST-027 — `turnos.ver-agenda-dia-profesional-flow`

| **RBAC** | `/api/profesional-agenda/ver-agenda-dia` | **P** | P1 |

**Esperado:** CU-TUR-011; lista turnos del día para PES.

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

### CU-AST-018 — `turnos.consultar-ocupacion-dia-flow`

| **RBAC** | `/api/turnos/consultar-ocupacion-dia` | **P** | P1 |

**Esperado:** Porcentaje/cupos ocupados vs libres para fecha y servicio.

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

### CU-AST-023 — `turnos.indicadores-agenda-flow`

| **RBAC** | `/api/turnos/indicadores-agenda` | **P** | P1 |

**Esperado:** CU-TUR-012; KPIs coherentes con datos del día.

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## Urgencias

### CU-AST-029 — `urgencias.ver-tablero-guardia`

| **RBAC** | `/api/clinical/emergency-guardia/tablero` | **P** | P0 |

**Precondición:** Sesión EMER (CU-TR-002).

**Esperado:** CU-EMER-001.

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

### CU-AST-028 — `urgencias.triage-paciente-guardia`

| **RBAC** | `/api/clinical/emergency-guardia/elegir-paciente-triage` | **P** | P0 |

**Pasos:** Elegir paciente en cola → completar subintents triage → submit.

**Esperado:** CU-EMER-002.

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## Checklist regresión asistente (release)

- [ ] Los 29 intents cargan en discovery (sin error de schema YAML).
- [ ] Todos los P0 (AST-006, 008, 009, 010, 014, 020, 024, 028, 029) ejecutados en web o Flutter.
- [ ] Paciente no ve intents staff (agenda.*, crear-para-paciente, triage).
- [ ] Staff EMER no ve intents de autogestión paciente sin permiso.
