# Fase 00 — ADR y schema JSON

## Objetivo

Fijar contrato público de la 1ª IA y decisión arquitectónica antes de código.

## Tareas

- [ ] ADR `decisions/asistente-catalogacion-unificada.md`: catalogación, sin guide, catálogo completo, hidratación PHP.
- [ ] Schema JSON v1 (campos obligatorios por `catalogacion`).
- [ ] Tabla de equivalencia: `user_goal` antiguo → `catalogacion` nueva.
- [ ] Plantilla de summary para intents (max ~120 caracteres + 2–4 ejemplos).
- [ ] Casos de aceptación escritos (mínimo 8): síntoma, sacar turno, ver turnos, llegar tarde, listar profesionales, comparar médicos, saludo/hola, fuera HIS.

## Criterio de salida

ADR mergeado; schema copiado en `design.md` del plan si cambia; casos en fase 06 referencian este doc.
