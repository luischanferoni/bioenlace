# Checklist — Médico

[← Médico](./README.md) · [Índice general](../README.md)

**Leyenda:** 🔴 bloqueante · 🟡 importante · 🟢 regresión

---

## Captura clínica (CAP)

| ID | Pri | Pasos resumidos | Esperado |
|----|-----|-----------------|----------|
| CAP-01 | 🔴 | Abrir captura de consulta | Pantalla carga |
| CAP-02 | 🟡 | Guardar motivo y evolución | Aparece en historia |
| CAP-03 | 🟡 | Audio → texto (si hay micrófono) | Texto en campos |
| CAP-04 | 🟡 | Derivar a otro servicio | En referencias pendientes |
| CAP-05 | 🟢 | Receta en misma consulta | Paciente ve receta |
| CAP-06 | 🟡 | Timeline turno — paciente cargó motivos | Resumen del chat visible en ventana médico |
| CAP-07 | 🟢 | Timeline turno — paciente sin motivos | Sin resumen; captura no bloqueada |

→ [captura-clinica.md](./captura-clinica.md)

---

## Recetas y planes (RX / PLN)

| ID | Pri | Pasos resumidos | Esperado |
|----|-----|-----------------|----------|
| RX-02 | 🟡 | Médico emite receta | En lista del paciente |
| RX-03 | 🟡 | Receta con datos faltantes | Bloqueo antes de emitir |
| PLN-01 | 🟡 | Plan de tratamiento activo | Visible en app paciente |

→ [laboratorio-receta-planes.md](./laboratorio-receta-planes.md)

Guardia e internación: [staff/checklist.md](../staff/checklist.md) (URG, INT).
