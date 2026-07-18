# Fase 5 — Piloto, evaluación y cierre

**Estado:** pendiente de ejecución operativa. La evidencia técnica de entrega y apertura de confirmaciones ya está disponible mediante ACK autenticado de la app paciente; falta acumular y evaluar datos reales en shadow mode.

## Objetivo

Validar utilidad, seguridad y equidad antes de activar acciones de alto impacto.

## Premisas del perfil

- El perfil factual es **uniforme en todo el sistema**: las definiciones métricas (incluida cancelación tardía) son globales y versionadas en el contrato.
- No hay retrocompatibilidad ni reconstrucción histórica: sólo se materializan eventos `NATIVE` emitidos desde la implementación canónica.
- Los scopes por efector/servicio/modalidad sirven para análisis, no para redefinir la semántica del perfil.
- Las políticas operativas de cada efector (plazos de cancelación en app, recordatorios, exclusiones locales) son independientes del perfil factual.

## Etapas

1. Shadow mode sin efectos.
2. Recordatorios y confirmación adicional, sin liberar cupos.
3. Piloto controlado por efector.
4. Evaluación de acceso, falsos positivos y correcciones.
5. Activación explícita y reversible de políticas aprobadas.
6. Consolidación documental y cierre del plan.

## Métricas del piloto

- tasa de no-show frente al baseline;
- confirmaciones solicitadas, entregadas, abiertas y respondidas;
- cobertura del ACK de entrega por plataforma y versión de app;
- cancelaciones o reprogramaciones tempranas (definición global de tardía);
- falsos positivos;
- turnos liberados y luego reclamados;
- tiempo hasta nueva cita;
- impacto en primera visita y continuidad;
- cobertura y muestra del perfil (eventos nativos);
- solicitudes de corrección;
- comparación agregada por grupos autorizados.

## Evidencia de confirmación disponible

- `CONFIRMATION_DELIVERY_CONFIRMED` se emite únicamente ante ACK autenticado de la app paciente; la aceptación HTTP de FCM no acredita entrega.
- `CONFIRMATION_OPENED` se emite únicamente ante tap explícito del push; abrir o marcar una alerta de la bandeja no acredita apertura.
- `CONFIRMATION_RATE` cuenta respuestas dentro del conjunto con entrega acreditada y no puede superar `1`.
- La ausencia de ACK no demuestra falta de entrega: debe medirse su cobertura y no tratarse como falta de respuesta.

Esta implementación desbloquea la medición de entrega requerida por el piloto, pero no reemplaza la evaluación operativa ni habilita por sí sola la liberación automática.

## Políticas que pueden aprobarse antes de acumular datos

Son invariantes de seguridad, atribución y equidad; no dependen de calibración estadística:

- cancelaciones de sistema, staff o efector no se atribuyen al paciente;
- falta de entrega acreditada no se interpreta como falta de respuesta;
- `insufficient_data` nunca habilita una acción de alto impacto;
- primera visita y continuidad priorizada quedan excluidas de liberación automática;
- ausencia de perfil vigente o compatible produce una decisión conservadora;
- liberación automática se clasifica como acción de alto impacto;
- toda política debe ser explícita, versionada, explicable, reversible y auditable;
- cancelación tardía se define **globalmente** en el contrato del perfil (p. ej. &lt; 24 h antes de la cita), no por efector;
- el perfil sólo consume evidencia `NATIVE` (sin backfill ni `LEGACY_INFERRED`).

## Parámetros que deben calibrarse con el piloto

- tamaño mínimo de muestra;
- valor concreto del umbral global de cancelación tardía (candidato inicial: 24 h);
- umbrales de riesgo y falsos positivos aceptables;
- plazos y cantidad de intentos de confirmación;
- definición y disponibilidad del canal alternativo;
- criterios operativos de exclusión adicionales por efector (política local, no redefinición del perfil).

## Condiciones para liberar cupos

- entrega de confirmación demostrable mediante `CONFIRMATION_DELIVERY_CONFIRMED`;
- cobertura del ACK conocida y suficiente para el alcance del piloto;
- muestra suficiente de eventos nativos;
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
