# Fase 1 — Ingreso a guardia con DNI (sin alta libre)

## Objetivo

El camino feliz de **Ingresar paciente** no crea persona tipeando ficha. Identidad = búsqueda local **o** DNI (código de barras / documento+sexo → RENAPER).

## Checklist

- [x] `GuardiaIngresoService` deja de usar `PersonaAltaOperativaService`.
- [x] Body de alta: `codigo_barras` **o** `documento` + `sexo_biologico`; el dominio llama `RegistroStaffPacienteService`.
- [x] Modal web: consultar identidad (preview RENAPER), datos de solo lectura, confirmar ingreso.
- [x] App Personal de Salud: misma regla.
- [x] Docs producto/QA de ingreso.
- [x] NN / Didit **no** en esta fase (mensaje claro si RENAPER no encuentra).

## Fuera

- Tablet Didit, identidad pendiente, sesión de ventanilla.
