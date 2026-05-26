# Planes de tratamiento (care plans)

**Madurez orientativa:** 3 / 4 (~75 %)

## Lo que tenemos

- [x] Care plan vinculado a paciente y opcionalmente a encounter.
- [x] Actividades del plan (medicación, controles, etc.) con estados.
- [x] Consulta de planes activos para el paciente en Bioenlace.
- [x] Recordatorios en dispositivo del paciente (sincronización desde API; preferencias por ítem cuando aplica).
- [x] Presentación en detalle de plan para seguimiento.
- [x] **Dashboard staff de adherencia** por efector: planes activos, % actividades completadas, resumen global (`/api/v1/clinical/care-plans/adherencia-resumen-staff`, intent `tratamiento.adherencia-resumen-staff`).

## Lo que falta

- [ ] Outcomes clínicos vinculados a adherencia (no solo % de actividades marcadas).
- [ ] Integración automática con dispensación o con laboratorio de controles.
- [ ] Planes generados o ajustados por IA con validación médica explícita.
- [ ] Versionado y auditoría de cambios del plan a nivel regulatorio.

## En producto hoy

[producto/planes-de-tratamiento.md](../producto/planes-de-tratamiento.md)
