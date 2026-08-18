# Urgencias / guardia

**Madurez orientativa:** 4 / 4 (~95 %) — circuito operativo + historia de episodio + captura unificada.

## Lo que tenemos

- [x] Registro de episodios de guardia por paciente y efector.
- [x] Ingreso (conocido, NN; DNI/Didit en app Personal de Salud).
- [x] Triage estructurado (Manchester 1–5, motivo, vitales opcionales, re-triage auditable).
- [x] Tablero en inicio (web y móvil): cola, estados, minutos de espera, indicadores del día.
- [x] Circuito: espera de triage → médico → atención → egreso, derivación o internación.
- [x] Captura clínica unificada (conducta — alta, internación, derivación — en el encounter).
- [x] Pedidos y resultados de laboratorio visibles en el episodio.
- [x] Solicitud de internación, badge de cama pendiente e ingreso con trazabilidad desde guardia.
- [x] SLA por efector y alerta visual en tablero; export CSV de indicadores.
- [x] Banner y timeline de episodio en historia clínica; signos vitales del episodio.
- [x] Egreso estructurado (destino, diagnóstico operativo, epicrisis).
- [x] Notificaciones al personal; flujos de asistente para tablero y triage.
- [x] Permisos de tablero y captura por capabilities (no solo “está logueado”).

## Lo que falta (refinamiento)

- [ ] Aviso sonoro en tablero al superar SLA.
- [ ] Configuración de umbrales SLA en pantalla de administración (hoy valores de institución).
- [ ] Pedidos con catálogo de actos / envío directo al laboratorio de planta.
- [ ] Box y enfermero asignados; valores críticos de lab en el tablero; plan formal post-alta domiciliaria.

## Documentación de producto

[urgencias-guardia.md](../producto/urgencias-guardia.md) · [hcd-episodio-emergencia-internacion.md](../producto/hcd-episodio-emergencia-internacion.md) · [captura-clinica.md](../producto/captura-clinica.md)
