# Fase 5 — Piloto, evaluación y cierre

## Objetivo

Validar utilidad, seguridad y equidad antes de activar acciones de alto impacto.

## Etapas

1. Shadow mode sin efectos.
2. Recordatorios y confirmación adicional, sin liberar cupos.
3. Piloto controlado por efector.
4. Evaluación de acceso, falsos positivos y correcciones.
5. Activación explícita y reversible de políticas aprobadas.
6. Consolidación documental y cierre del plan.

## Métricas del piloto

- tasa de no-show frente al baseline;
- confirmaciones entregadas y respondidas;
- cancelaciones o reprogramaciones tempranas;
- falsos positivos;
- turnos liberados y luego reclamados;
- tiempo hasta nueva cita;
- impacto en primera visita y continuidad;
- cobertura y muestra del perfil;
- solicitudes de corrección;
- comparación agregada por grupos autorizados.

## Condiciones para liberar cupos

- entrega de confirmación demostrable;
- muestra suficiente;
- canal alternativo;
- plazo razonable;
- exclusiones clínicas y operativas;
- alternativa accesible para recuperar atención;
- aprobación del efector;
- rollback probado;
- monitoreo de impacto activo.

## Criterios de aceptación

- No hay deterioro significativo de acceso en primera visita o grupos vulnerables.
- Los falsos positivos se mantienen dentro del umbral aprobado.
- El procedimiento de corrección fue probado de punta a punta.
- La política puede deshabilitarse sin migración ni despliegue.
- Los dashboards distinguen acciones recomendadas, ejecutadas y revertidas.
- Producto, privacidad, Scheduling y referentes clínicos aprueban la salida.

## Cierre documental

1. Actualizar `producto/turnos.md` con el comportamiento realmente desplegado.
2. Actualizar `producto/agentes-autonomos.md`.
3. Actualizar `his-completo/11-agenda-turnos.md`.
4. Registrar ADR si queda una decisión transversal permanente.
5. Eliminar `plans/perfil-comportamiento-turnos/`.
