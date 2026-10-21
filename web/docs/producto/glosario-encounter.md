# Glosario clínico: encounter

Vocabulario canónico para código, contratos y copy clínico. La frontera de nombres es deliberada: el dominio usa `encounter`; la interfaz puede usar el texto que el backend publique para cada audiencia.

| Término interno | Significado | Copy posible |
|---|---|---|
| **Encounter** | Registro de una interacción clínica con un sujeto, profesional y contexto. Es el término interno para clases, rutas, payloads y persistencia. | «Consulta» cuando ese sea el texto definido por producto/backend. |
| **Episodio** | Contexto longitudinal u operativo que agrupa encounters relacionados, como guardia o internación. No es sinónimo de encounter. | «Episodio de guardia», «internación». |
| **Captura** | Borrador de información clínica aportada por texto o audio, procesada y revisada antes de persistir como encounter. | «Escribir o dictar», «revisar la captura». |
| **Motivo** | Razón clínica de la interacción o del pedido de atención. Es un dato asociado; no nombra al encounter. | «Motivo de consulta» si el backend lo entrega así. |

## Reglas de uso

- Usar `encounter` en identificadores, clases, archivos, rutas y claves de persistencia.
- Usar «consulta» solo en copy de interfaz definido por el backend; no introducirlo como nombre de dominio ni como traducción local de códigos.
- No usar «atención» como sinónimo de encounter. Reservarlo para los conceptos de producto que expresen acceso o prestación de servicios.
- No confundir el encounter con el turno, el episodio que lo contiene o la captura todavía no guardada.
- Mantener separados los nombres de API legados que aún estén en migración; renombrar en un cambio coordinado con todos sus consumidores, sin alias permanente.

La migración está en curso. El estado y los pasos pendientes se registran en la [fase 02 del plan](../plans/clientes-contrato-api/phases/02-terminologia-encounter.md).
