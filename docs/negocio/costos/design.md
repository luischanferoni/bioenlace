# Costos — criterio de diseño

## Por qué dos ejes (infra vs API)

Permite decidir **dónde corre el compute** sin mezclar precios de RunPod/GPU con precios por token de Vertex en la misma tabla. Cada eje tiene su propia tabla de costos y su propio supuesto de volumen: [infra-costos.md](infra-costos.md) y [costos-api.md](costos-api.md).

**Alternativa descartada:** un solo Excel global sin separar capex/opex de infra y variable por token.

## Dónde vive cada cosa

| Tema | Documento |
|------|-----------|
| Flujo de motivos de consulta (una sola pasada IA por consulta, cierre 10 min antes del turno) | [costos-api.md §2](costos-api.md#2-motivos-de-consulta-chat-dedicado-antes-de-la-atención) |
| Palancas de ahorro por ítem | [estrategias-reduccion/](estrategias-reduccion/README.md) |
| Mix porcentual del COGS (qué pesa cada componente) | [resumen-costos-bioenlace.md § Composición del COGS](resumen-costos-bioenlace.md#composición-del-cogs-qué-pesa-en-el-costo) |
| Conversaciones y CLI de prueba | [pruebas-costos-ia.md](pruebas-costos-ia.md) |
| ¿Centro de datos propio? IA (GPU) vs videollamada, opciones, capex/opex, break-even y custodia física | [analisis-centro-datos-propio.md](analisis-centro-datos-propio.md) |
