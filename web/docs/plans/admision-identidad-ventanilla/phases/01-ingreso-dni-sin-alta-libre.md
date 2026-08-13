# Fase 1 — Ingreso a guardia con DNI (sin alta libre)

## Objetivo

El camino feliz de **Ingresar paciente** no crea persona tipeando ficha. Identidad = búsqueda local **o** DNI (código de barras / documento+sexo → RENAPER).

## Checklist

- [x] `GuardiaIngresoService` deja de usar `PersonaAltaOperativaService`.
- [x] Body de alta: `codigo_barras` **o** `documento` + `sexo_biologico`; el dominio llama `RegistroStaffPacienteService`.
- [x] Modal web: paciente conocido o NN. DNI/Didit solo en app (`ingreso_dni_clients: mobile`); leyenda si no está en el sistema.
- [x] App Personal de Salud: búsqueda local, DNI (código de barras / documento+sexo → RENAPER), Didit o NN.
- [x] Docs producto/QA de ingreso.
- [x] NN / Didit **no** en esta fase (mensaje claro si RENAPER no encuentra).

## Fuera

- Tablet Didit, identidad pendiente, sesión de ventanilla.
