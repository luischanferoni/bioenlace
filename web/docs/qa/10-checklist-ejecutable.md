# Checklist ejecutable de pruebas

[← Índice](./README.md)

Marcá cada caso en tu planilla (Excel, Jira, etc.). Detalle ampliado en los archivos por módulo.

**Leyenda:** 🔴 bloqueante · 🟡 importante · 🟢 regresión

## Antes de empezar

- [ ] Credenciales de paciente, personal y admin de staging
- [ ] Si probás sector/provincia: centros demo cargados (pedir a desarrollo) — ver [09-admin-efectores-organizacion.md](./09-admin-efectores-organizacion.md)
- [ ] App paciente con notificaciones permitidas (si probás pushes)

---

## Transversal (TRN)

| ID | Pri | Pasos resumidos | Esperado |
|----|-----|-----------------|----------|
| TRN-01 | 🔴 | Login web personal OK | Entrada al inicio o asistente |
| TRN-02 | 🔴 | Login con contraseña incorrecta | Error, sin sesión |
| TRN-03 | 🔴 | Elegir efector + servicio ambulatorio | Menú de turnos visible |
| TRN-04 | 🟡 | Usuario sin efectores asignados | Mensaje claro, no opera |
| TRN-05 | 🔴 | Login app paciente | Inicio sin elegir efector |
| TRN-06 | 🟡 | Buscar persona por documento (staff) | Lista y ficha |
| TRN-07 | 🟡 | Alta paciente por asistente (staff) | Alta OK; sesión del staff no cambia |
| TRN-08 | 🟢 | Abrir enlace guardado que ya no existe | Mensaje orientador, no pantalla vacía |

→ [00-transversal.md](./00-transversal.md)

---

## Contexto paciente — app (CTX)

| ID | Pri | Pasos resumidos | Esperado |
|----|-----|-----------------|----------|
| CTX-01 | 🔴 | Público + provincia del CAP demo | Ofrece CAP demo al sacar turno |
| CTX-02 | 🔴 | Privado + provincia clínica demo | Ofrece clínica privada |
| CTX-03 | 🔴 | Público + otra provincia | No aparecen centros demo |
| CTX-04 | 🔴 | Sin provincia en configuración | Banner / bloqueo de turnos |
| CTX-05 | 🟡 | Cambiar sector con centro incompatible | Error o centro ya no listado |
| CTX-06 | 🟢 | Sugerencia de provincias | Lista con opciones |
| CTX-07 | 🟡 | Chat: ministerio de salud (SDE) | Datos de Santiago del Estero |
| CTX-08 | 🟡 | Mismo flujo con Santa Fe | Datos de Santa Fe |
| CTX-09 | 🔴 | Registro nuevo en app | Contexto persiste al reabrir app |
| CTX-10 | 🟢 | Domicilio “verificando” | Banner coherente; puede actualizarse |
| CTX-11 | 🟡 | Staff registra paciente | Efector del staff no cambia |
| CTX-12 | 🟡 | Búsqueda antigua de duplicados al registrar | No existe / mensaje de flujo nuevo |
| CTX-13 | 🟡 | Inicio sin provincia | Sin bloque de próximos turnos |
| CTX-14 | 🔴 | “Quiero turno” sin provincia | No avanza; mensaje claro |
| CTX-15 | 🟢 | Representación de familiar | Anotar si usa contexto del tutor |

→ [08-registro-contexto-paciente.md](./08-registro-contexto-paciente.md)

---

## Admin efectores (ADM)

| ID | Pri | Pasos resumidos | Esperado |
|----|-----|-----------------|----------|
| ADM-01 | 🔴 | `/admin/efectores` sin filtro | Listado carga |
| ADM-02 | 🔴 | Filtro provincia | Solo esa provincia |
| ADM-03 | 🔴 | Filtro sector Público | Sin privados |
| ADM-04 | 🔴 | Filtro sector Privado | Solo privados |
| ADM-05 | 🟡 | Provincia + sector | Coherente |
| ADM-06 | 🟢 | Ordenar por provincia | Orden OK |
| ADM-07 | 🟢 | Abrir detalle de centro | Sin error |
| ADM-08 | 🟡 | Cruzar con paciente público + CAP | Turno en CAP demo |
| ADM-09 | 🟡 | Cruzar con paciente privado | Turno en clínica demo |
| ADM-10 | 🟡 | Admin filtro Público | Incluye CAP, excluye clínica |

→ [09-admin-efectores-organizacion.md](./09-admin-efectores-organizacion.md)

---

## Turnos (TUR)

| ID | Pri | Pasos resumidos | Esperado |
|----|-----|-----------------|----------|
| TUR-01 | 🔴 | Agenda del día (staff) | Turnos visibles |
| TUR-02 | 🔴 | Crear turno con cupo libre | Turno creado |
| TUR-03 | 🔴 | Mismo horario ocupado | Rechazo |
| TUR-04 | 🔴 | Paciente saca turno (contexto OK) | Confirmación |
| TUR-05 | 🔴 | Paciente con centro de otra provincia/sector | No listado o error |
| TUR-06 | 🟡 | Cancelar con anticipación suficiente | Cancelado |
| TUR-07 | 🟡 | Cancelar fuera de plazo | Rechazo explicado |
| TUR-08 | 🟡 | Reprogramar | Nuevo horario |
| TUR-09 | 🟢 | Sobreturno staff | Aparece en agenda |
| TUR-10 | 🟢 | Marcar no vino | Estado ausente |
| TUR-11 | 🟡 | Cancelación con lista de espera | Aviso al siguiente |

→ [02-turnos-agenda.md](./02-turnos-agenda.md)

---

## Captura clínica (CAP)

| ID | Pri | Pasos resumidos | Esperado |
|----|-----|-----------------|----------|
| CAP-01 | 🔴 | Abrir captura de consulta | Pantalla carga |
| CAP-02 | 🟡 | Guardar motivo y evolución | Aparece en historia |
| CAP-03 | 🟡 | Audio → texto (si hay micrófono) | Texto en campos |
| CAP-04 | 🟡 | Derivar a otro servicio | En referencias pendientes |
| CAP-05 | 🟢 | Receta en misma consulta | Paciente ve receta |

→ [01-captura-clinica.md](./01-captura-clinica.md)

---

## Guardia (URG)

| ID | Pri | Pasos resumidos | Esperado |
|----|-----|-----------------|----------|
| URG-01 | 🔴 | Tablero con sesión guardia | Cola visible |
| URG-02 | 🔴 | Triage Manchester | Clasificación guardada |
| URG-03 | 🟡 | Atender y guardar captura | Caso en atención / cerrado |
| URG-04 | 🟡 | Derivar a otro hospital | Sale de cola activa |
| URG-05 | 🟡 | Reserva sin cupo post-triage | Mensaje o push alternativo |

→ [03-urgencias-guardia.md](./03-urgencias-guardia.md) · [11-notificaciones-automaticas.md](./11-notificaciones-automaticas.md)

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

→ [04-internacion.md](./04-internacion.md)

---

## Lab, receta, planes (LAB)

| ID | Pri | Pasos resumidos | Esperado |
|----|-----|-----------------|----------|
| LAB-01 | 🔴 | Paciente: mis resultados | Solo los propios |
| LAB-02 | 🟡 | Resultado nuevo cargado | Aparece en lista |
| LAB-03 | 🟡 | Abrir detalle / PDF | Descarga o vista OK |
| RX-01 | 🔴 | Paciente: mis recetas | Solo las propias |
| RX-02 | 🟡 | Médico emite receta | En lista del paciente |
| RX-03 | 🟡 | Receta con datos faltantes | Bloqueo antes de emitir |
| PLN-01 | 🟡 | Plan de tratamiento activo | Visible en app |
| PLN-02 | 🟢 | Recordatorios de medicación | Lista en app |

→ [05-laboratorio-receta-planes.md](./05-laboratorio-receta-planes.md)

---

## Asistente (AST)

| ID | Pri | Pasos resumidos | Esperado |
|----|-----|-----------------|----------|
| AST-01 | 🔴 | “Quiero un turno” (contexto OK) | Flujo turnos |
| AST-02 | 🔴 | Igual sin provincia | Bloqueo o mensaje |
| AST-03 | 🟡 | “Cancelar turno” | Flujo cancelación |
| AST-04 | 🟡 | “Mapa de camas” (staff) | Mapa |
| AST-05 | 🟡 | Acción sin permiso | Mensaje claro |
| AST-06 | 🟢 | Ministerio de salud provincia | FAQ correcta |

→ [07-asistente.md](./07-asistente.md)

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

→ [11-notificaciones-automaticas.md](./11-notificaciones-automaticas.md)

---

## Reportes (NOM)

| ID | Pri | Pasos resumidos | Esperado |
|----|-----|-----------------|----------|
| NOM-01 | 🟡 | Alta práctica en nomenclador | Usable en captura |
| NOM-02 | 🟢 | Planilla por rango de fechas | Genera sin error |

→ [06-reportes-nomenclador.md](./06-reportes-nomenclador.md)

---

## Automatización (solo equipo desarrollo)

Tests de regresión en servidor, no parte del checklist manual:

`vendor/bin/codecept run unit` en carpeta `web/` — ver repositorio.
