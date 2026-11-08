# Fase 07 — Planes SaaS y precio consistente

## Objetivo

Presentar el alta móvil como una selección simple de planes, y usar el backend como fuente única para precios, impuestos y conversión a ARS en Personal de Salud y el sitio institucional.

## Decisiones

- La contratación disponible sigue siendo mensual. No mostrar opción anual hasta definir precio, descuento, cobro y ciclo de licencia anual.
- La cuenta profesional personal es gratuita.
- El consultorio corresponde a un profesional y 250 atenciones AMB (media jornada) o 500 (jornada completa) por mes; el importe es calculado por el backend.
- Clínica/centro conserva la selección de clases y volumen; los requisitos particulares se piden después de elegir el plan.
- La pantalla previa a Didit evita siglas, nombres de proveedores, fórmulas, USD/MEP y detalles internos. Explica solo el plan, qué incluye, el precio mensual estimado en ARS y que el total final se confirma antes de pagar.
- El calculador detallado de `institucional/precios.html` se conserva como simulador exploratorio. No duplica fórmulas: consulta al endpoint público de cotización.
- La API devuelve configuración presentacional y estimaciones; los costos y la fórmula permanecen en el servidor.
- La cotización incluye líneas por clase con volumen y precio unitario, precio de lista, descuento por volumen y desglose de neto/IVA/total en ARS.
- Las tasas MEP se guardan como registros históricos inmutables con fecha de vigencia. Checkout guarda además su snapshot de tasa/fecha/IVA/importe.
- La tasa vigente se carga trimestralmente con `php yii billing-exchange-rate/set <tasa> <YYYY-MM-DD> "MEP vendedor"`; una tasa con más de 100 días bloquea nuevas cotizaciones hasta actualizarla.

## Discrepancia de costos IA a corregir

- El sitio institucional configuraba `patient_chat_amb = USD 0,0048` por atención.
- El catálogo de backend y la matriz comercial usaban `USD 0,0019`, correspondiente a una hipótesis de costo más favorable.
- Se adopta `USD 0,0048` como costo de referencia de producción acordado para AMB, considerando el mayor contexto que puede incluir `preprocess` y el historial/adjuntos de `guide`.
- A 250 atenciones AMB (media jornada), con los demás componentes vigentes y margen de lista, el precio estimado es aproximadamente USD 7,75 mensuales antes de conversión e impuestos; a 500 (jornada completa), ~USD 15,50. No se fija este valor en la UI; se cotiza con el backend.
- Actualizar costos y supuestos de Guide/preprocess, documentando la diferencia entre estimación conservadora y escenario con caché. No alterar prompts ni límites clínicos como parte de esta fase.

## Tareas

- [x] Alinear costo AMB de referencia a USD 0,0048 y actualizar pruebas/documentación de costos IA.
- [x] Agregar historial de cotizaciones MEP con vigencia y mantenimiento por comando administrativo.
- [x] Agregar endpoint público de cotización mensual validado en servidor; mantener checkout como autoridad final.
- [x] Usar el catálogo backend como API pública de planes para que app y web consuman las mismas opciones comerciales.
- [x] Reemplazar el cálculo local de `institucional/precios.html` por solicitudes al endpoint, preservando controles y desglose.
- [x] Rediseñar la selección móvil previa a Didit como acordeones de cuenta profesional, consultorio y clínica/centro.
- [x] Mostrar precios en ARS, con impuestos incluidos; retirar detalles técnicos del paso previo a identidad.
- [x] Pasar el plan elegido al checkout y precargarlo en el formulario comercial.
- [x] Mantener visible el precio final, renovación y cancelación antes de autorizar Mercado Pago.
- [ ] Verificar en ejecución que publicación de precios y checkout usan la misma tasa vigente y que el intento persistido retiene el snapshot.

## Criterios de aceptación

- El precio de 250 AMB (media jornada) y 500 AMB (jornada completa) coincide en la web, la app, la API y el checkout antes de cambios de tasa.
- Modificar el catálogo de precios en backend modifica ambas superficies sin editar fórmulas frontend.
- Una nueva cotización trimestral crea una fila histórica y las cotizaciones futuras la usan; los intentos previos conservan su snapshot.
- El alta móvil permite entender y elegir cada recorrido antes de Didit, con lenguaje de usuario y sin presentar el alta de identidad como una contratación.
- No aparece opción anual mientras no exista soporte de anualidad.
