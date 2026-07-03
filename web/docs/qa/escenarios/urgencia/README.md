# Urgencia — Guardia y derivación desde la app

[← Escenarios](../README.md) · Producto: [urgencias-guardia.md](../../../producto/urgencias-guardia.md) · [triage-reserva-turno.md](../../../producto/triage-reserva-turno.md)

## De qué se trata

Dos caminos que QA debe distinguir:

1. **Paciente en la app con urgencia real o banda A** — no se completa turno ambulatorio; derivación a guardia / 107.
2. **Paciente en guardia presencial** — el equipo registra ingreso, triage Manchester y atención médica (`encounter_class` EMER).

Este documento cubre ambos en un solo guion de prueba.

**Encounter:** EMER (guardia) o ninguno (solo derivación en app).

---

## Prerrequisitos QA

| Requisito | Notas |
|-----------|--------|
| Efector con **guardia** habilitada | Sesión operativa EMER |
| Usuario staff/médico de guardia | Rol con acceso al tablero |
| Usuario paciente en app | Para probar derivación banda A |
| Paciente de prueba para ingreso guardia | DNI / persona ya cargada o alta en guardia |

---

## Consulta de ejemplo (guion)

### Lo que dice el paciente (app — no debe reservar turno)

> «Tengo un dolor fuerte en el pecho y me cuesta respirar.»

**En alarmas del triage:** marca síntomas de alarma (banda A).

**Mensaje esperado del sistema (orientativo):**

> «Tu situación requiere atención inmediata. Acudí a la guardia más cercana o llamá al 107.»

### Lo que dice el paciente (guardia presencial)

> «Me duele el abdomen desde esta mañana, con vómitos.»

### Lo que dice el médico de guardia (captura)

> «Paciente ingresa por dolor abdominal difuso de 6 h de evolución con dos episodios de vómito. Triage nivel 3. Abdomen blando, dolor a la palpación en epigastrio. Plan: hidratación EV, analgesia, laboratorio, reevaluación en 2 h.»

---

## Paciente — paso a paso

### A. Urgencia en app (derivación, sin turno)

**Intent:** `atencion.necesito-atencion` → raíz **Urgencia** o alarmas banda A

1. **Vos** describís síntomas graves en **Asistente** (guion pecho/disnea).
2. **El sistema** muestra paso de alarmas; si corresponde banda **A**, **no** ofrece servicio ni horario.
3. **El sistema** muestra pantalla de **derivación a guardia / emergencia** (y aviso 107).
4. **Vos** **no** debés ver turno ambulatorio confirmado.

### B. Paciente que ya está en guardia

1. El paciente **no usa la app** para el circuito de guardia (salvo resumen post-atención si aplica).
2. QA valida el flujo **staff** siguiente.

---

## Personal de salud — paso a paso

### 1. Tablero de guardia

1. **Vos** fijás sesión **guardia** ([transversal.md](../../staff/transversal.md)).
2. **Vos** abrís tablero de guardia.
3. **El sistema** muestra cola: ingresos, triage pendiente, espera médico, en atención.

### 2. Ingreso y triage

1. **Vos** registrás ingreso del paciente de prueba (o tomás uno en cola).
2. **Vos** completás **triage** (Manchester 1–5, motivo, signos).
3. **El sistema** pasa el caso a espera médico; puede notificar al equipo si está configurado.

### 3. Atender

1. **Vos** (médico) te **asignás** el caso o iniciás atención.
2. **El sistema** abre captura clínica del ingreso EMER.
3. **Vos** documentás con el guion y guardás.
4. **El sistema** actualiza estado en tablero (en atención → alta/derivación/internación).

### 4. Cierre del episodio

1. **Vos** das **alta**, **derivás** a otro efector o **internás** según el caso.
2. **El sistema** saca el caso de la cola activa.

Detalle operativo: [urgencias-guardia.md](../../staff/urgencias-guardia.md).

**Si el caso pasa a internación:** [internacion/README.md](../internacion/README.md).

---

## Notificaciones — cuándo esperar qué

| Momento | Quién | Qué esperar |
|---------|-------|-------------|
| Banda A en reserva (app) | Paciente | Mensaje en pantalla; **no** push de turno confirmado |
| Triage crítico en guardia | Staff | Push al equipo según configuración del efector |
| Médico se asigna caso | Médico | Push en app Personal de Salud (si habilitado) |
| Alta de guardia | Paciente | Resumen de atención / seguimiento según programa |

Los recordatorios **JOURNEY_*** de turno ambulatorio **no aplican** a este escenario.

---

## Qué validar (checklist)

| ID | Validación |
|----|------------|
| URG-01 | Banda A en app: sin turno creado; mensaje de derivación visible |
| URG-02 | Tablero guardia lista ingresos y estados coherentes |
| URG-03 | Triage registrado; caso pasa a espera médico |
| URG-04 | Captura EMER guarda documentación; tablero refleja estado |
| URG-05 | Alta/derivación/internación cierra el caso en cola activa |
| URG-06 | Aviso 107 / emergencia presencial visible donde corresponda |

---

## Referencias

- QA staff: [urgencias-guardia.md](../../staff/urgencias-guardia.md)
- QA médico: [captura-clinica.md](../../medico/captura-clinica.md) (§ Guardia)
- Producto: [urgencias-guardia.md](../../../producto/urgencias-guardia.md)
- Notificaciones: [notificaciones-automaticas.md](../../staff/notificaciones-automaticas.md)
