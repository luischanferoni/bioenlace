# Checklist — Paciente (app)

[← Paciente](./README.md) · [Índice general](../README.md)

**Leyenda:** 🔴 bloqueante · 🟡 importante · 🟢 regresión

## Antes de empezar

- [ ] Usuario paciente de staging
- [ ] App con notificaciones permitidas (si probás pushes)
- [ ] Centros demo cargados si probás sector/provincia (pedir al responsable del entorno)

---

## Login app (TRN)

| ID | Pri | Pasos resumidos | Esperado |
|----|-----|-----------------|----------|
| TRN-05 | 🔴 | Login app paciente | Inicio sin elegir efector |

→ [contexto-registro.md](./contexto-registro.md) (entrada paciente en transversal staff: [staff/transversal.md](../staff/transversal.md))

---

## Contexto paciente (CTX)

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
| CTX-10 | 🟢 | Domicilio “verificando” | Banner coherente |
| CTX-13 | 🟡 | Inicio sin provincia | Sin bloque de próximos turnos |
| CTX-14 | 🔴 | “Quiero turno” sin provincia | No avanza; mensaje claro |
| CTX-15 | 🟢 | Representación de familiar | Anotar si usa contexto del tutor |

→ [contexto-registro.md](./contexto-registro.md)

---

## Turnos app (TUR)

| ID | Pri | Pasos resumidos | Esperado |
|----|-----|-----------------|----------|
| TUR-04 | 🔴 | Paciente saca turno (contexto OK) | Confirmación |
| TUR-05 | 🔴 | Paciente con centro de otra provincia/sector | No listado o error |
| TUR-06 | 🟡 | Cancelar con anticipación suficiente | Cancelado |
| TUR-07 | 🟡 | Cancelar fuera de plazo | Rechazo explicado |
| TUR-08 | 🟡 | Reprogramar | Nuevo horario |

→ [turnos.md](./turnos.md)

---

## Lab, receta, planes (LAB / RX / PLN)

| ID | Pri | Pasos resumidos | Esperado |
|----|-----|-----------------|----------|
| LAB-01 | 🔴 | Paciente: mis resultados | Solo los propios |
| LAB-02 | 🟡 | Resultado nuevo cargado | Aparece en lista |
| LAB-03 | 🟡 | Abrir detalle / PDF | Descarga o vista OK |
| RX-01 | 🔴 | Paciente: mis recetas | Solo las propias |
| PLN-01 | 🟡 | Plan de tratamiento activo | Visible en app |
| PLN-02 | 🟢 | Recordatorios de medicación | Lista en app |

→ [laboratorio-receta-planes.md](./laboratorio-receta-planes.md)

---

## Asistente app (AST)

| ID | Pri | Pasos resumidos | Esperado |
|----|-----|-----------------|----------|
| AST-01 | 🔴 | “Quiero un turno” (contexto OK) | Flujo turnos |
| AST-02 | 🔴 | Igual sin provincia | Bloqueo o mensaje |
| AST-03 | 🟡 | “Cancelar turno” | Flujo cancelación |
| AST-06 | 🟢 | Ministerio de salud provincia | FAQ correcta |

→ [asistente.md](./asistente.md)

Checklist staff / médico / admin: [staff/checklist.md](../staff/checklist.md), [medico/checklist.md](../medico/checklist.md), [admin_efector/checklist.md](../admin_efector/checklist.md).
