# Urgencias / guardia

**Madurez orientativa:** 4/4 (circuito operativo v1)

## Lo que tenemos

- [x] Registro de episodios de guardia por paciente y efector.
- [x] Pantallas de trabajo de guardia para el equipo (libro / ingresos).
- [x] Base para continuidad con consulta o internación.
- [x] Triage estructurado (Manchester 1–5, motivo, vitales opcionales, re-triage con evento).
- [x] Tablero operativo en inicio web y móvil médico (cola, estados, minutos de espera).
- [x] Asignación, inicio de atención con `captura_url`, derivación y egreso vía API.
- [x] Indicadores resumen (medianas door-to-triage / door-to-doctor) + materialización diaria opcional.
- [x] Push servidor (`EMERGENCY_ASSIGNED_TO_YOU`, `EMERGENCY_PATIENT_CRITICAL`).
- [x] Intents asistente `urgencias.*` para tablero y triage.

## Lo que falta (post v1)

- [ ] Pedidos y resultados integrados desde guardia sin salir del módulo.
- [ ] Derivación a cama con trazabilidad completa (badge “pendiente” en tablero).
- [ ] Export CSV de indicadores para dirección médica.
- [ ] FCM en app médico para recibir push de guardia.
- [ ] SLA por nivel con `efector_emergency_config`.

## Documentación de producto

Ver [urgencias-guardia.md](../producto/urgencias-guardia.md).
