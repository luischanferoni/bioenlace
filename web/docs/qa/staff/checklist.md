# Checklist — Staff (web)

[← Staff](./README.md) · [Índice general](../README.md)

**Leyenda:** 🔴 bloqueante · 🟡 importante · 🟢 regresión

## Antes de empezar

- [ ] Credenciales de personal de staging
- [ ] Efector + servicio elegidos (ambulatorio / guardia / internación)

---

## Transversal (TRN)

| ID | Pri | Pasos resumidos | Esperado |
|----|-----|-----------------|----------|
| TRN-01 | 🔴 | Login web personal OK | Entrada al inicio o asistente |
| TRN-02 | 🔴 | Login con contraseña incorrecta | Error, sin sesión |
| TRN-03 | 🔴 | Elegir efector + servicio ambulatorio | Panel Pacientes del día con turnos |
| TRN-04 | 🟡 | Usuario sin efectores asignados | Mensaje claro, no opera |
| TRN-06 | 🟡 | Buscar persona por documento | Lista y ficha |
| TRN-07 | 🟡 | Alta paciente por asistente | Alta OK; sesión del staff no cambia |
| TRN-08 | 🟢 | Abrir enlace guardado obsoleto | Mensaje orientador |

→ [transversal.md](./transversal.md) · [registro-paciente-staff.md](./registro-paciente-staff.md)

---

## Turnos staff (TUR)

| ID | Pri | Pasos resumidos | Esperado |
|----|-----|-----------------|----------|
| TUR-01 | 🔴 | Agenda del día (staff) | Turnos visibles |
| TUR-02 | 🔴 | Crear turno con cupo libre | Turno creado |
| TUR-03 | 🔴 | Mismo horario ocupado | Rechazo |
| TUR-09 | 🟢 | Sobreturno staff | Aparece en agenda |
| TUR-10 | 🟢 | Marcar no vino | Estado ausente |
| TUR-11 | 🟡 | Cancelación con lista de espera | Aviso al siguiente |

→ [turnos-agenda.md](./turnos-agenda.md)

---

## Guardia (URG)

| ID | Pri | Pasos resumidos | Esperado |
|----|-----|-----------------|----------|
| URG-01 | 🔴 | Tablero con sesión guardia | Cola visible |
| URG-02 | 🔴 | Triage Manchester | Clasificación guardada |
| URG-03 | 🟡 | Atender y guardar captura | Caso en atención / cerrado |
| URG-04 | 🟡 | Derivar a otro hospital | Sale de cola activa |
| URG-05 | 🟡 | Reserva sin cupo post-triage | Mensaje o push alternativo |

→ [urgencias-guardia.md](./urgencias-guardia.md)

---

## Internación (INT)

| ID | Pri | Pasos resumidos | Esperado |
|----|-----|-----------------|----------|
| INT-01 | 🔴 | Mapa de camas | Estados por color |
| INT-02 | 🔴 | Ingreso en cama libre | Cama ocupada, internación activa |
| INT-03 | 🟡 | Cambio de cama | Nueva cama, mismo paciente |
| INT-04 | 🔴 | Alta con epicrisis | Cama libre |
| INT-05 | 🟡 | Ingreso: sugerencia de cama | Lista de camas sugeridas |
| INT-06 | 🟡 | Alta: seguimiento post-alta | Aviso o encuesta al paciente |

→ [internacion.md](./internacion.md)

---

## Asistente staff (AST)

| ID | Pri | Pasos resumidos | Esperado |
|----|-----|-----------------|----------|
| AST-04 | 🟡 | “Mapa de camas” (staff) | Mapa |
| AST-05 | 🟡 | Acción sin permiso | Mensaje claro |

→ [asistente.md](./asistente.md)

---

## Avisos automáticos (AGT)

| ID | Pri | Situación | Esperado |
|----|-----|-----------|----------|
| AGT-01 | 🟡 | Respuesta encuesta seguimiento | Flujo coherente |
| AGT-02 | 🟡 | Laboratorio valor crítico | Notificación |
| AGT-03 | 🟡 | Cancelación + lista espera | Aviso al siguiente |
| AGT-05 | 🟡 | Sin cupo post-triage | Alternativa o derivación |
| AGT-06 | 🟡 | Alta internación | Seguimiento post-alta |
| AGT-07 | 🟡 | Receta incompleta | No emite; mensaje claro |
| AGT-08 | 🟡 | Ingreso internación | Sugerencia de camas |
| AGT-09 | 🟢 | Riesgo no-show | Push confirmación |
| AGT-10 | 🟢 | Sin respuesta reprogramación | Cierre o escalada |

→ [notificaciones-automaticas.md](./notificaciones-automaticas.md)

---

## Reportes (NOM)

| ID | Pri | Pasos resumidos | Esperado |
|----|-----|-----------------|----------|
| NOM-01 | 🟡 | Alta práctica en nomenclador | Usable en captura |
| NOM-02 | 🟢 | Planilla por rango de fechas | Genera sin error |

→ [reportes-nomenclador.md](./reportes-nomenclador.md)

Contexto paciente (app): [paciente/checklist.md](../paciente/checklist.md). Admin efectores: [admin_efector/checklist.md](../admin_efector/checklist.md).

---

## Automatización (solo desarrollo)

`vendor/bin/codecept run unit` en carpeta `web/` — ver repositorio.
