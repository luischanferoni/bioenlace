# Costos — Diseño del análisis

## Por qué dos ejes (infra vs API)

Permite decidir **dónde corre el compute** sin mezclar precios de RunPod/GPU con precios por token de Vertex en la misma tabla.

**Alternativa descartada:** un solo Excel global sin separar capex/opex de infra y variable por token.

## Comunicación pre-turno

Se modela aparte porque es conversación **antes** de confirmar turno y puede no terminar en reserva. Impacta ambos ejes; ver secciones en [flows/infra_costos.md](./flows/infra_costos.md) y [flows/costos.md](./flows/costos.md).

## Pruebas

[flows/pruebas_costos_ia.md](./flows/pruebas_costos_ia.md) define conversaciones JSON y CLI para simular costo sin llamar a proveedores reales en cada corrida.
