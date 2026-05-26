# Urgencias / guardia

**Madurez orientativa:** 4 / 4 (~95 %) — circuito v1 + post-v1 (pedidos/lab, cama, SLA, CSV, FCM médico).

## Lo que tenemos

- [x] Registro de episodios de guardia por paciente y efector.
- [x] Pantallas de trabajo de guardia para el equipo (libro / ingresos).
- [x] Base para continuidad con consulta o internación.
- [x] Triage estructurado (Manchester 1–5, motivo, vitales opcionales, re-triage con evento).
- [x] Tablero operativo en inicio web y móvil médico (cola, estados, minutos de espera).
- [x] Asignación, inicio de atención con `captura_url`, derivación y egreso vía API.
- [x] Indicadores resumen (medianas door-to-triage / door-to-doctor) + materialización diaria opcional.
- [x] Push servidor y cliente FCM app médico (`EMERGENCY_*`).
- [x] Intents asistente `urgencias.*` para tablero y triage.
- [x] Pedidos y resultados de lab en tablero (`resumen-clinico`, `crear-pedido`).
- [x] Solicitud de internación + badge “cama pendiente” + ingreso web con `id_guardia`.
- [x] SLA por efector (`efector_emergency_config`) y alerta visual en tablero.
- [x] Export CSV de indicadores (`indicadores-export-csv`).

## Lo que falta (refinamiento)

- [ ] Aviso sonoro en tablero al superar SLA.
- [ ] Configuración SLA por UI de administración (hoy defaults en BD).
- [ ] Pedidos con catálogo SNOMED / envío directo al LIS (sigue siendo indicación en Bioenlace).

## Documentación de producto

Ver [urgencias-guardia.md](../producto/urgencias-guardia.md).
