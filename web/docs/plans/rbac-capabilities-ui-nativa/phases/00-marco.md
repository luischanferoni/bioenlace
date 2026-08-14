# Fase 0 — Marco y matriz rol → capability

## Objetivo

Fijar vocabulario, capas y matriz acordada antes de migraciones. Entregable: revisión de producto + checklist QA firmada.

## Capas (recordatorio)

| # | Pregunta | Capa |
|---|----------|------|
| 1 | ¿El rol tiene la operación? | RBAC (intent o capability → ruta) |
| 2 | ¿Puede actuar sobre este recurso? | Dominio (`Encounter.access`, `GuardiaEpisode.*`, …) |
| 3 | ¿Mostramos el botón en este cliente? | Manifiesto UI + flags `puede_*` del panel |

## Matriz MVP (guardia + encounter)

| Operación producto | capability_id | Administrativo | Enfermería | Médico | AdminEfector |
|--------------------|---------------|:--------------:|:----------:|:------:|:------------:|
| Ver tablero guardia | `guardia.view_board` | ✓ | ✓ | ✓ | ✓ |
| Ingreso / identidad | `guardia.ingreso` | ✓ | — | — | ✓ |
| Triage | `guardia.triage` | ✓ | ✓ | ✓ | ✓ |
| Tomar caso / atender | `guardia.atender` | — | — | ✓ | — |
| Paciente se retiró | `guardia.retiro_administrativo` | ✓ | — | —* | ✓ |
| Egreso clínico | `guardia.egreso_clinico` | — | — | ✓ | — |
| Ver consulta staff | `encounter.ver_como_staff` | ✓** | ✓ | ✓ | ✓** |
| Captura clínica | `encounter.capturar` | — | — | ✓ | — |
| Nota enfermería sin tomar caso | `encounter.documentar_nota` | — | ✓ | — | — |

\* Médico usa egreso clínico o retiro según modo; ver dominio en `GuardiaEgresoEstructuradoService`.  
\** Lectura; dominio puede restringir si no es participante — documentar caso admisión.

## Endpoints a auditar (inventario)

Generar lista automática en Fase 1; base manual:

### Guardia

- `/api/clinical/emergency-guardia/ingresar`
- `…/ingresar-formulario`
- `…/buscar-persona-ingreso`
- `…/vincular-identidad`
- `…/registrar-triage`
- `…/registrar-triage-formulario`
- `…/elegir-paciente-triage`
- `…/ver`
- `…/iniciar-atencion`
- `…/egreso-formulario`
- `…/finalizar`
- `/api/home/panel` (sección `emergency_board`)

### Encounter

- `/api/clinical/encounter/ver-consulta-como-staff`
- `/api/clinical/encounter/captura-*`
- `/api/clinical/encounter/guardar`
- `/api/clinical/encounter/analizar`

## Criterios de aceptación Fase 0

- [ ] Matriz revisada con producto clínico + admisión.
- [ ] Listado de endpoints acordado (sin sorpresas en Fase 2).
- [ ] Decisión: Fase 2 puede desplegarse **antes** de Fase 5 (manifiesto por roles) — **sí**, parche RBAC primero.

## QA smoke (post-programa completo)

Roles de prueba en staging:

1. **Administrativo EMER**: panel carga → ingreso NN → triage → retiro (sin 403).
2. **Enfermería EMER**: triage → documentar nota sin iniciar-atencion.
3. **Médico EMER**: tomar caso → captura → egreso clínico.
4. **Administrativo AMB**: no recibe grants de captura por error de propagación.
