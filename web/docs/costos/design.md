# Costos — Diseño del análisis

## Por qué dos ejes (infra vs API)

Permite decidir **dónde corre el compute** sin mezclar precios de RunPod/GPU con precios por token de Vertex en la misma tabla.

**Alternativa descartada:** un solo Excel global sin separar capex/opex de infra y variable por token.

## Motivos de consulta (app paciente)

Flujo de producto (mayo 2026):

1. El paciente envía mensajes (texto, audio, imagen) **sin IA por mensaje** — solo persistencia (`motivos-consulta/enviar|subir`).
2. **Hasta 1 minuto antes del turno** puede seguir cargando (`motivos_consulta_cierre_minutos` en `params.php`).
3. Al cerrar la ventana, el cron `turno-notificacion/run` ejecuta **`MOTIVOS_IA_BATCH`**: una inferencia con todo el hilo (STT de audios + resumen) → `encounter.reason_text`.
4. El médico ve el resumen en timeline / formulario de consulta.

Coste modelado: **400 llamadas IA/mes** (1 por consulta), no 4× por mensaje. Código: `AppointmentReasonWindowService`, `AppointmentReasonBatchService`.

Impacta [costos-api.md](./costos-api.md) (Apartado 1) e [infra-costos.md](./infra-costos.md) si el lote corre en GPU propia.

## Pruebas

[pruebas-costos-ia.md](./pruebas-costos-ia.md) define conversaciones JSON y CLI para simular costo sin llamar a proveedores reales en cada corrida.
