# Checklist ejecutable de pruebas

[← Índice](./README.md)

Lista consolidada para marcar en planilla propia (Excel, issue tracker, etc.). Cada fila es un caso reproducible.

**Leyenda:** 🔴 bloqueante · 🟡 importante · 🟢 regresión / nice-to-have

**Preparación común**

- [ ] `php yii clinical-seed/efector-demo-contexto` ejecutado sin error
- [ ] Usuario paciente de prueba con JWT válido
- [ ] Usuario staff con efector 863 (o demo) y `set-session`
- [ ] Usuario admin con acceso a `/admin/efectores`

---

## Transversal (TRN)

| ID | Pri | Pasos resumidos | Esperado |
|----|-----|-----------------|----------|
| TRN-01 | 🔴 | Login web staff credenciales válidas | Entrada al home / asistente |
| TRN-02 | 🔴 | Login credenciales inválidas | Mensaje de error, sin sesión |
| TRN-03 | 🔴 | `set-session` efector + servicio ambulatorio | Menú turnos/agenda visible |
| TRN-04 | 🟡 | Staff sin efectores asignados | Mensaje claro, sin operar |
| TRN-05 | 🔴 | Login app paciente | Home paciente sin elegir efector |
| TRN-06 | 🟡 | Buscar persona por documento (staff) | Lista y ficha |
| TRN-07 | 🟡 | Alta paciente nuevo (staff vía asistente) | Persona creada, sin tocar sesión staff |
| TRN-08 | 🟢 | URL legacy internación retirada | Mensaje orientador, no 404 vacío |

Detalle: [00-transversal.md](./00-transversal.md)

---

## Contexto paciente (CTX)

| ID | Pri | Pasos resumidos | Esperado |
|----|-----|-----------------|----------|
| CTX-01 | 🔴 | Paciente `PUBLICO` + provincia CAP demo | Oferta incluye CAP demo |
| CTX-02 | 🔴 | Paciente `PRIVADO` + provincia clínica demo | Oferta incluye clínica privada |
| CTX-03 | 🔴 | `PUBLICO` + provincia incorrecta | No aparecen efectores demo |
| CTX-04 | 🔴 | Sin provincia en contexto | Intents operativos bloqueados / banner |
| CTX-05 | 🟡 | Cambiar sector tras elegir efector incompatible | Error al reservar turno |
| CTX-06 | 🟢 | `sugerir-provincias-como-paciente` | Hasta 5 provincias |
| CTX-07 | 🟡 | Recurso provincial SDE (asistente) | Datos de `recursos-provinciales.yaml` |
| CTX-08 | 🟡 | Recurso provincial Santa Fe | No mezcla con SDE |
| CTX-09 | 🔴 | Autoregistro app | Fila `persona_paciente_contexto` |
| CTX-10 | 🟢 | Cron domicilio RENAPER | Estado avanza / reintentos |
| CTX-11 | 🟡 | Alta staff no cambia sesión operativa | Efector staff intacto |
| CTX-12 | 🟡 | Ruta MPI legacy | HTTP 410 |
| CTX-13 | 🟡 | Home sin provincia | Sin próximos turnos en panel |
| CTX-14 | 🔴 | Intent turno sin `puedeOperarApp` | No avanza flujo |
| CTX-15 | 🟢 | Representación otro paciente | Documentar contexto del actor |

Detalle: [08-registro-contexto-paciente.md](./08-registro-contexto-paciente.md)

---

## Admin y seeds (ADM / SEED / MED)

| ID | Pri | Pasos resumidos | Esperado |
|----|-----|-----------------|----------|
| ADM-01 | 🔴 | `/admin/efectores` sin filtro | Listado carga |
| ADM-02 | 🔴 | Filtro provincia | Solo efectores de esa provincia |
| ADM-03 | 🔴 | Filtro sector Público | Sin financiamiento Privado |
| ADM-04 | 🔴 | Filtro sector Privado | Solo Privado |
| ADM-05 | 🟡 | Provincia + sector | Intersección correcta |
| ADM-06 | 🟢 | Ordenar por provincia | Orden estable |
| ADM-07 | 🟢 | Link al detalle efector | View sin error |
| SEED-01 | 🔴 | `efector-demo-contexto` 1ª vez | 2 efectores + médicos |
| SEED-02 | 🔴 | `efector-demo-contexto` 2ª vez | Idempotente |
| SEED-03 | 🟡 | `efector-demo-contexto-info` | Muestra ids y médicos |
| SEED-04 | 🟡 | `efector-demo-contexto-remove` | Limpia demo |
| SEED-05 | 🟡 | Seed con geo vacío | Crea localidades DEV* |
| SEED-06 | 🔴 | Médico id_efector ≥ 1000 | Documento ≤ 8 chars |
| MED-01 | 🟡 | `medico-med-general --efector=863` | User/doc legacy |
| MED-05 | 🟡 | Login médico seed + set-session | Opera en efector |

Detalle: [09-admin-efectores-organizacion.md](./09-admin-efectores-organizacion.md)

---

## Turnos y agenda (TUR)

| ID | Pri | Pasos resumidos | Esperado |
|----|-----|-----------------|----------|
| TUR-01 | 🔴 | Ver agenda del día (staff) | Turnos listados |
| TUR-02 | 🔴 | Crear turno staff cupo libre | Turno creado |
| TUR-03 | 🔴 | Crear turno cupo ocupado | Rechazo |
| TUR-04 | 🔴 | Paciente saca turno (efector permitido por contexto) | Confirmación |
| TUR-05 | 🔴 | Paciente turno en efector de otra provincia/sector | Rechazo o no listado |
| TUR-06 | 🟡 | Cancelar turno paciente (anticipación OK) | Cancelado |
| TUR-07 | 🟡 | Cancelar tarde (si regla lo impide) | Rechazo explicado |
| TUR-08 | 🟡 | Reprogramar turno | Cupo actualizado |
| TUR-09 | 🟢 | Sobreturno staff | Marcado sobreturno |
| TUR-10 | 🟢 | Marcar no-show | Estado ausente |
| TUR-11 | 🟡 | Lista de espera tras cancelación (A03) | Siguiente paciente notificado |

Detalle: [02-turnos-agenda.md](./02-turnos-agenda.md)

---

## Captura clínica (CAP)

| ID | Pri | Pasos resumidos | Esperado |
|----|-----|-----------------|----------|
| CAP-01 | 🔴 | Abrir consulta ambulatoria | Pantalla captura |
| CAP-02 | 🟡 | Guardar motivo + evolución texto | Persistido en encounter |
| CAP-03 | 🟡 | Audio → transcripción (si habilitado) | Texto en campos clínicos |
| CAP-04 | 🟡 | Derivar a otro servicio | Derivación registrada |
| CAP-05 | 🟢 | Emitir receta en consulta | Receta en lista paciente |

Detalle: [01-captura-clinica.md](./01-captura-clinica.md)

---

## Urgencias / guardia (URG)

| ID | Pri | Pasos resumidos | Esperado |
|----|-----|-----------------|----------|
| URG-01 | 🔴 | Tablero guardia con sesión guardia | Cola visible |
| URG-02 | 🔴 | Triage Manchester | Clasificación guardada |
| URG-03 | 🟡 | Atender paciente en guardia | Encounter en progreso |
| URG-04 | 🟡 | Derivar a otro efector | Derivación registrada |
| URG-05 | 🟡 | Grilla vacía post-triage (A05) | Push canal alternativo / mensaje |

Detalle: [03-urgencias-guardia.md](./03-urgencias-guardia.md) · Agentes: [11-agentes-reglas-autonomas.md](./11-agentes-reglas-autonomas.md)

---

## Internación (INT)

| ID | Pri | Pasos resumidos | Esperado |
|----|-----|-----------------|----------|
| INT-01 | 🔴 | Mapa de camas | Camas por estado |
| INT-02 | 🔴 | Ingreso con cama libre | Internación activa |
| INT-03 | 🟡 | Cambio de cama | Cama actualizada |
| INT-04 | 🔴 | Alta estructurada | Cama liberada |
| INT-05 | 🟡 | Ingreso con sugerencia cama (F02) | `cama_sugerencias` en contexto |
| INT-06 | 🟡 | Post-alta (B02) | Touchpoint en cola seguimiento |

Detalle: [04-internacion.md](./04-internacion.md)

---

## Laboratorio, receta, planes (LAB)

| ID | Pri | Pasos resumidos | Esperado |
|----|-----|-----------------|----------|
| LAB-01 | 🔴 | Paciente `mis-resultados-como-paciente` | Solo propios |
| LAB-02 | 🟡 | Seed `laboratory-demo` | Informe demo visible |
| LAB-03 | 🟡 | PDF / detalle resultado | Descarga OK |
| RX-01 | 🔴 | Paciente `mis-recetas-como-paciente` | Solo propias |
| RX-02 | 🟡 | Emitir receta staff | Aparece en lista |
| RX-03 | 🟡 | Validación pre-RDI (E03) flag on | Bloqueo si faltan campos |
| PLN-01 | 🟡 | Care plan activo paciente | GET active |
| PLN-02 | 🟢 | Recordatorios demo timing | Endpoint recordatorios |

Detalle: [05-laboratorio-receta-planes.md](./05-laboratorio-receta-planes.md)

---

## Asistente (AST)

| ID | Pri | Pasos resumidos | Esperado |
|----|-----|-----------------|----------|
| AST-01 | 🔴 | “quiero un turno” (paciente, contexto OK) | Flujo turnos |
| AST-02 | 🔴 | Mismo sin contexto provincia | Mensaje / bloqueo |
| AST-03 | 🟡 | “cancelar turno” | Flujo cancelación |
| AST-04 | 🟡 | “mapa de camas” (staff internación) | Pantalla mapa |
| AST-05 | 🟡 | Intent sin permiso | Mensaje claro |
| AST-06 | 🟢 | Recurso provincial (CTX-07) | FAQ provincia |

Detalle: [07-asistente.md](./07-asistente.md)

---

## Agentes autónomos por reglas (AGT)

| ID | Pri | Flag / trigger | Esperado |
|----|-----|----------------|----------|
| AGT-01 | 🟡 | B01 touchpoint respuesta | Rama cohorte según reglas YAML |
| AGT-02 | 🟡 | B03 resultado lab crítico | Clasificación + notificación |
| AGT-03 | 🟡 | A03 cancelación turno | Waitlist FIFO notificada |
| AGT-04 | 🟢 | A02 multicanal | Escalada según reglas |
| AGT-05 | 🟡 | A05 grilla vacía triage | Push `RESERVA_TRIAGE_CANAL_ALTERNATIVO` |
| AGT-06 | 🟡 | B02 alta internación | Touchpoint post-alta en cola |
| AGT-07 | 🟡 | E03 emitir receta | Validación pre-RDI |
| AGT-08 | 🟡 | F02 contexto ingreso | Sugerencias de cama |
| AGT-09 | 🟢 | A04 riesgo no-show | Push confirmación |
| AGT-10 | 🟢 | A06 sin respuesta negociación | Cierre / escalada staff |

Detalle: [11-agentes-reglas-autonomas.md](./11-agentes-reglas-autonomas.md)

---

## Tests automatizados (PHPUnit / Codeception)

Ejecutar en `web/`:

```bash
vendor/bin/codecept run unit organization/MedicoMedGeneralEfectorSeedServiceTest
vendor/bin/codecept run unit organization/EfectorDemoSeedServiceConstantsTest
vendor/bin/codecept run unit person/PacienteContextoOfferingMetadataTest
vendor/bin/codecept run unit person/PacienteContextoOfferingServiceTest
vendor/bin/codecept run unit agent
```

| Suite | Qué cubre |
|-------|-----------|
| `organization/*Seed*` | Documentos médico ≤ 8 chars, constantes demo |
| `person/PacienteContexto*` | Reglas sector público/privado en metadata y matching |
| `unit/agent` | Motores de reglas agentes autónomos |

---

## Reportes y nomenclador (NOM)

| ID | Pri | Pasos resumidos | Esperado |
|----|-----|-----------------|----------|
| NOM-01 | 🟡 | ABM prácticas efector | Alta/edición |
| NOM-02 | 🟢 | Planilla ministerial rango fechas | Genera sin error |

Detalle: [06-reportes-nomenclador.md](./06-reportes-nomenclador.md)
