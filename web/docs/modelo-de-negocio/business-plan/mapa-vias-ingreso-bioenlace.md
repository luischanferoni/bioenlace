# Mapa: vías de ingreso × encaje Bioenlace

**Tipo:** business plan · estrategia producto  
**Última actualización:** 2026-05-27  
**Fuentes:** casos en [`../`](../README.md), madurez en [`../../his-completo/informe-ejecutivo.md`](../../his-completo/informe-ejecutivo.md)

---

## Resumen

Las vías de ingreso del sector privado documentadas por país (China, Argentina, Colombia, etc.) no son todas atacables por Bioenlace con el mismo esfuerzo. Este mapa clasifica cada vía según **encaje actual**, **quién paga**, **cómo monetizar** y **qué falta construir**.

**Wedge actual de Bioenlace:** atención ambulatoria + Encounter unificado, agenda con KPIs, paciente digital, guardia operativa, planes de tratamiento y asistente conversacional. **No** es aún un HIS enterprise de facturación, farmacia con stock ni retail Rx.

---

## Tabla resumen

| # | Vía (casos país) | Encaje Bioenlace | Comprador principal | Modelo de ingreso | Horizonte |
|---|------------------|------------------|---------------------|-------------------|-----------|
| 1 | SaaS / HIS clínico | **Alto** | Efector privado, red ambulatoria | Licencia + implementación + soporte | Corto |
| 2 | Autorización + liquidación OS/EPS | **Medio → alto** | Prestador; prepaga/OS | Módulo premium; fee por transacción | Mediano |
| 3 | RCM / facturación al acto clínico | **Bajo hoy** | Prestador | SaaS + % recupero (opcional) | Mediano–largo |
| 4 | Auditoría / antifraude / analytics | **Bajo → medio** | Financiador; Estado | B2G licitación; SaaS analytics | Largo |
| 5 | Retail Rx + delivery (China) | **Puente, no retailer** | Farmacia / plataforma | API por receta enrutada | Mediano (partner) |
| 6 | Copagos / bolsillo paciente | **Indirecto** | Paciente → efector | Retención del cliente de Bioenlace | Corto |
| 7 | Compra pública de prestación / IT | **Indirecto** | Estado / CCSS / SUS | Licitación; módulo + reporting | Largo |
| 8 | Capitación / UPC (Colombia, MA) | **Medio** | EPS / aseguradora | SaaS control de costo | Mediano (fuera AR foco) |

---

## Detalle por vía

### 1. SaaS / HIS clínico

**Ejemplos en casos país:** hospitales públicos que compran IT (China, Alemania); sanatorios AR; clínicas Chile/Brasil; GP systems UK (EMIS, TPP).

**Qué es:** licencia de software para operar agenda, atención, guardia, internación, receta y reporting sobre un registro clínico común.

**Encaje Bioenlace:** **Alto.** Es el core vendible hoy: ambulatorio (~75%), agenda (~81%), guardia (~95%), internación (~82%), receta (~75%), planes de tratamiento (~75%).

**Dónde reduce costos para el comprador:**

- Menos tiempo de documentación (captura asistida texto/voz).
- Menos no-shows y mejor uso de agenda (KPIs staff).
- Menos reconsultas por falta de resumen al paciente.
- Guardia con SLA: menos caos operativo y horas improductivas.

**Quién paga:** director médico / COO / IT del **efector** (sanatorio, policlínica, red ambulatoria). En público: licitación provincial (ciclo largo).

**Cómo generar ingresos:**

- Fee mensual por efector, por profesional activo o por módulo.
- Implementación e integraciones (lab, LIS) one-shot.
- Soporte y evolutivos en contrato anual.

**Build faltante relevante:** historia clínica longitudinal médica sin exportar PDF; teleconsulta nativa en agenda; retiro completo de terminología legacy «consulta».

**Prioridad:** **Corto plazo** — motor de ingresos actual.

---

### 2. Autorización + liquidación (OS, prepaga, EPS)

**Ejemplos en casos país:** Argentina (convenios OS, PMO, glosas); Colombia (EPS → IPS); Brasil (operadoras ANS); EE.UU. (prior authorization).

**Qué es:** flujo administrativo entre **financiador** y **prestador**: validar cobertura, autorizar prácticas de alto costo, liquidar según nomenclador y reglas de cartilla.

**Encaje Bioenlace:** **Medio hoy, alto potencial.** La captura clínica (Encounter) ya existe; falta el circuito **autorización en agenda y al pedir estudio**, reglas PMO/cartilla y handoff a liquidación.

**Dónde reduce costos:**

- **Prestador:** menos glosas y rechazos por mala codificación o falta de autorización previa; cobro más rápido.
- **Financiador:** menos prácticas innecesarias; auditoría con trazabilidad clínica.
- **Paciente:** menos sorpresas de copago y menos idas y vueltas.

**Quién paga:**

- **Prestador** (sanatorio): paga módulo que acelera cobro a OS/prepaga.
- **Prepaga / grupo sanitario:** paga canal digital + reglas de autorización integradas al acto clínico (sin reemplazar legacy de liquidación de un día).

**Cómo generar ingresos:**

- Módulo premium mensual («pack OS»).
- Fee por autorización digital procesada.
- Implementación por financiador (integración API o batch).

**Build faltante:** autorización OS en flujo de reserva; validación cartilla al prescribir/pedir estudio; conectores por financiador (no hay estándar único en AR); tablero de glosas pendientes.

**Prioridad:** **Mediano plazo** — escalón de ticket en Argentina; prioridad #1 del informe ejecutivo junto con receta nacional.

---

### 3. RCM / facturación al acto clínico

**Ejemplos en casos país:** EE.UU. (claim → payer); Colombia (facturación electrónica IPS); Argentina (liquidación sanatorio–OS).

**Qué es:** traducir el **acto clínico documentado** en ítems facturables, generar comprobante/claim, seguir cobranza y conciliación.

**Encaje Bioenlace:** **Bajo hoy (~38% facturación).** Hay bases de nomenclador en parte de recorridos; no hay ciclo factura–cobro–contabilidad pleno.

**Dónde reduce costos:**

- Menos retrabajo administrativo post-atención.
- Menos discrepancia entre lo clínico y lo facturado.
- Conciliación más rápida con OS (menos días de capital inmovilizado).

**Quién paga:** prestador (sanatorio, clínica con alto volumen OS). Financiador indirectamente si reduce fraude.

**Cómo generar ingresos:**

- SaaS por volumen facturado o por puesto administrativo.
- Opcional: % sobre recupero (modelo RCM clásico; más complejo legal/comercial).

**Build faltante:** ciclo completo factura–cobro; integración contable; reglas por financiador; internación y guardia facturables end-to-end.

**Prioridad:** **Mediano–largo** — enterprise revenue; integraciones pesadas por institución.

---

### 4. Auditoría / antifraude / analytics (estilo NHSA)

**Ejemplos en casos país:** China (NHSA, big data claims); Argentina (auditoría RNOS, glosas OS); Colombia (auditoría EPS).

**Qué es:** analizar patrones de prestación, recetas, repetición de estudios y claims para detectar fraude, sobreutilización o error.

**Encaje Bioenlace:** **Bajo → medio.** Bioenlace tiene datos clínicos estructurados (Encounter, pedidos, receta) pero no producto de analytics B2G ni motor de reglas antifraude a escala financiador.

**Dónde reduce costos:**

- Financiador: ahorro directo en prestaciones indebidas.
- Estado: control de gasto en subsidios y programas.

**Quién paga:** obra social grande, prepaga, PAMI (muy largo), o licitación estatal.

**Cómo generar ingresos:**

- SaaS analytics por afiliado (PMPM).
- Proyecto B2G por licitación.
- Informes periódicos + alertas.

**Build faltante:** capa analítica cross-efector; reglas configurables; anonimización/agregación; conectores a sistemas de liquidación del financiador.

**Prioridad:** **Largo plazo** — alto valor pero ventas largas y requisito de escala de datos.

---

### 5. Retail Rx + delivery (modelo China)

**Ejemplos en casos país:** JD Health, AliHealth, Meituan; receta hospital de internet → checkout en super-app.

**Qué es:** margen comercial y logística en **dispensación** de medicamentos, separado del acto clínico reembolsable.

**Encaje Bioenlace:** **No como retailer.** Rol natural: **capa clínica + receta digital + derivación** (handoff API a farmacia o marketplace).

**Dónde reduce costos:**

- Institución: no opera farmacia ni delivery.
- Paciente: continuidad de tratamiento (menos abandono) — beneficio indirecto al efector.

**Quién paga:** cadena farmacéutica o plataforma de delivery (B2B2C); efector paga SaaS clínico, no el margen retail.

**Cómo generar ingresos:**

- Fee por receta enrutada o revenue share con partner.
- White-label de receta + checkout para grupo sanitario con farmacia propia.

**Build faltante:** homologación receta nacional; API de dispensación; partners comerciales; farmacia con stock (~38%) si se quiere cerrar loop.

**Prioridad:** **Mediano plazo vía partners** — no competir con retail; ser infraestructura clínica.

---

### 6. Copagos / bolsillo del paciente

**Ejemplos en casos país:** Chile (copagos FONASA MLE); Argentina (prácticas fuera de cartilla); Singapur (copagos altos).

**Qué es:** ingreso del prestador cuando el financiador no cubre 100% o el paciente elige fuera de red.

**Encaje Bioenlace:** **Indirecto.** Bioenlace no cobra al paciente por el acto; mejora la experiencia que retiene pacientes de pago al efector.

**Dónde reduce costos:** no aplica como ingreso Bioenlace; sí **aumenta retención** del cliente (sanatorio) que cobra copagos.

**Quién paga:** paciente al prestador. Bioenlace se cobra al prestador por plataforma.

**Cómo generar ingresos:** upsell de módulos que demuestren más retención (resumen paciente, app, planes de tratamiento).

**Build faltante:** estimación de copago en agenda/reserva; integración medios de pago (opcional, no core HIS).

**Prioridad:** **Corto plazo** como argumento de venta al efector, no como línea de ingreso directa.

---

### 7. Compra pública de prestación e IT

**Ejemplos en casos país:** UK (NHS → privados); Costa Rica (CCSS outsourcing); Brasil (SUS compra servicios); México (IMSS); Argentina (provincias).

**Qué es:** el Estado o el seguro social integrado **contrata** prestación privada o software con presupuesto público.

**Encaje Bioenlace:** **Indirecto.** Producto vendible como módulo (ambulatorio, guardia, reporting) en licitación; no como reemplazo total del hospital público en corto plazo.

**Dónde reduce costos:**

- Mejor triage y guardia → menos saturación.
- Reporting de SLA para justificar contratos de compra de servicios.
- Digitalización ambulatoria sin papel.

**Quién paga:** ministerio provincial, hospital público, programa (SUMAR, etc.).

**Cómo generar ingresos:** licitación; contrato marco; implementación + soporte anual.

**Build faltante:** requisitos soberanía de datos; export/reporting regulatorio; certificaciones que pida cada jurisdicción `[pendiente normativa]`.

**Prioridad:** **Largo plazo** — complemento al wedge privado; ciclos de venta largos.

---

### 8. Capitación / UPC (control de costo en aseguramiento)

**Ejemplos en casos país:** Colombia (UPC a EPS); EE.UU. (Medicare Advantage); Brasil (capitación en algunos modelos).

**Qué es:** el financiador recibe un monto fijo por afiliado y asume riesgo; incentivo a **reducir costo por caso** sin perder calidad.

**Encaje Bioenlace:** **Medio** vía planes de tratamiento, adherencia, pathways y analytics de utilización. No es foco Argentina inmediato (modelo más por evento/OS que UPC pura).

**Dónde reduce costos:**

- Menos reingresos por mala adherencia.
- Menos estudios duplicados si hay continuidad clínica.
- Priorización de seguimiento crónico (dashboard adherencia ya ~75%).

**Quién paga:** EPS / aseguradora con modelo capitado.

**Cómo generar ingresos:** SaaS por afiliado gestionado; módulo «pathways + SLA clínico».

**Build faltante:** adherencia vinculada a outcomes; reglas por pathway; integración automática lab/farmacia en planes.

**Prioridad:** **Mediano plazo** para expansión Colombia/Brasil; referencia para prepagas AR con modelos de riesgo.

---

## Lectura estratégica

| Horizonte | Vías a priorizar | Mensaje comercial |
|-----------|------------------|-------------------|
| **Corto** | 1 (SaaS clínico), 6 (indirecto vía retención) | «Mejor ambulatorio + agenda + paciente digital con ROI medible» |
| **Mediano** | 2 (autorización OS), 5 (puente receta), 8 (pathways) | «Del acto clínico al cobro y la continuidad, sin retail» |
| **Largo** | 3 (RCM pleno), 4 (auditoría B2G), 7 (licitación pública) | «Enterprise + financiador + Estado» |

Ver matriz operativa para Argentina: [matriz-argentina-modulos-precios.md](./matriz-argentina-modulos-precios.md).
