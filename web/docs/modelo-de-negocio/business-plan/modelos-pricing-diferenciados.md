# Modelos de pricing diferenciados — B2B clínico

**Tipo:** business plan · estrategia comercial  
**Última actualización:** 2026-05-27  
**Contexto:** diferenciación de **modelo de negocio** (no solo UI/UX) frente a HIS tradicionales y startups D2C «consulta gratis + farmacia».

**Alcance del modelo de ingreso:**

| Sí modelamos | No modelamos |
|--------------|--------------|
| Licencia y add-ons al **efector** / **financiador** | Cobrar al sanatorio porque el paciente **sigue yendo a ese hospital** (fidelización del paciente al efector) |
| Autorización OS, RCM, receta enrutada, B2G | Cobrar al sanatorio/OS porque **atrae o suma más pacientes** (captación / volumen institucional) |
| **Pathway fees completados** (prepaga/OS): fee cuando un afiliado cierra un **pathway clínico definido** (control de costo en capitación) | Variables al efector por resumen abierto, adherencia o «continuidad» post-consulta |
| PMPM al **financiador** por afiliado en programa digital (acceso a canal, no «retener en un sanatorio») | Ex vía copagos/bolsillo como línea de ingreso indirecta al efector |

---

## Glosario — abreviaturas y términos en inglés

### Abreviaturas

| Sigla | Significado | En castellano / contexto Bioenlace |
|-------|-------------|-------------------------------------|
| **B2B** | *Business to Business* | Venta entre empresas: el cliente es el efector, sanatorio, prepaga u obra social, no el paciente final. |
| **B2C** | *Business to Consumer* | Venta directa al paciente o consumidor final. |
| **B2B2C** | *Business to Business to Consumer* | El cliente comercial es institucional (B2B); el beneficiario del servicio es el paciente (B2C). Bioenlace vende al efector y el paciente usa la app/flujo. |
| **D2C** | *Direct to Consumer* | Modelo en el que la plataforma llega al paciente sin intermediario institucional (ej. app de telemedicina + farmacia propia). |
| **HIS** | *Hospital Information System* | Sistema de información hospitalario: software clínico-administrativo tradicional (historia, turnos, internación, facturación). |
| **Rx** | *Prescription* | Receta médica / receta electrónica. |
| **OTC** | *Over the counter* | Medicamentos de venta libre (sin receta). |
| **CAC** | *Customer Acquisition Cost* | Costo de adquisición de cliente: lo que cuesta conseguir un usuario o paciente nuevo (marketing, promos, etc.). |
| **STT** | *Speech to Text* | Transcripción de voz a texto (audio de consulta → texto para captura clínica). |
| **GTM** | *Go to Market* | Estrategia comercial de salida al mercado: a quién vendés, por qué canal y con qué propuesta. |
| **ROI** | *Return on Investment* | Retorno de la inversión para el comprador del software. |
| **OS** | *Obra Social* (en Argentina) | Financiador prepago de salud; en otros países a veces se confunde con *Operating System* — aquí siempre es obra social. |
| **PMPM** | *Per Member Per Month* | Precio por afiliado por mes: métrica típica de prepagas. |
| **KPI** | *Key Performance Indicator* | Indicador clave de desempeño (ej. no-show, lead time en agenda). |

### Términos en inglés (negocio y producto)

| Término | Significado |
|---------|-------------|
| **Encounter** | Encuentro clínico atendido (consulta, guardia, internación): unidad central del modelo FHIR y del flujo Bioenlace. |
| **fulfillment** | Cumplimiento / entrega del pedido: que la receta se dispense, retire o llegue al paciente (farmacia y envío). |
| **rev share** (*revenue share*) | Reparto de ingresos: parte del fee que cobra un socio (ej. farmacia) y se comparte con Bioenlace. |
| **retail** | Venta minorista al consumidor final (farmacia de mostrador u online). |
| **checkout** | Paso de pago / cierre de compra en la app o web. |
| **funnel** (*embudo*) | Recorrido desde el primer contacto hasta la conversión (ej. descarga app → consulta → receta → compra). |
| **white-label** | Producto con marca del cliente (prepaga u OS) en lugar de la marca Bioenlace. |
| **pathway** | Camino clínico acotado y medible (crónico, renovación Rx, post-alta). |
| **pathway fee** | Cobro al **financiador** cuando un afiliado completa un pathway definido (distinto de retener paciente en un sanatorio). |
| **split** | Reparto porcentual de un ingreso entre varias partes (prepaga, farmacia, Bioenlace). |
| **build** | Desarrollo / capacidad de producto que falta construir o integrar (ej. «build crítico: receta nacional»). |
| **freemium** | Modelo freemium: tier gratuito con límites + tier pago al superar umbral o por funciones avanzadas. |
| **tier** | Nivel o plan de producto (gratis, básico, pago). |
| **unit economics** | Economía unitaria: ingresos y costos por unidad de negocio (por profesional, encounter, receta, afiliado). |
| **gross margin** | Margen bruto: ingresos menos costos directos del servicio, antes de gastos generales (meta ~70 % en software por suscripción). |
| **headcount** | Cantidad de personas en el equipo (costo fijo de nómina amortizado por cliente). |
| **ticket** (*ticket medio*) | Monto promedio por transacción o consulta (ej. USD 50 por turno atendido). |
| **no-show** | Paciente que no asiste al turno reservado. |
| **lead time** | Tiempo de espera hasta la atención (desde reserva hasta consulta). |
| **fee** | Tarifa fija por evento o transacción (ej. fee por receta enrutada). |
| **handoff** | Traspaso del paciente de un actor a otro (ej. de la consulta a la farmacia de la red). |
| **compliance** | Cumplimiento normativo y legal (receta, datos de salud, relación con OS/prepagas). |
| **seats** | Licencias por usuario/profesional sentado en el sistema (*per seat*). |
| **performance marketing** | Marketing de performance: pago por resultado (clic, conversión), típico en D2C. |
| **piloto** | Prueba acotada con un cliente real antes de escalar comercialmente. |
| **puente clínico** | Rol de Bioenlace: conectar consulta y receta con fulfillment sin ser farmacia ni operador logístico. |

---

## Resumen

El patrón de mercado «consulta gratuita si hay receta → compra en plataforma → delivery gratis» monetiza **margen farmacéutico y escala D2C**, no licencias de software hospitalario.

Bioenlace adopta el **mismo flujo de experiencia** (Encounter → receta → fulfillment) con economía **B2B**: licencia al efector o financiador, add-ons, autorización OS, RCM, **pathway fees** al financiador y **rev share** con farmacia — sin subsidar consultas ni cobrar al efector por «mantener» o «sumar» pacientes a su cartera.

---

## Qué financian los modelos «consulta gratis + Rx»

Ese modelo no es un HIS: es **B2C + retail farmacéutico** con la consulta como adquisición.

```
Ingreso ≈ (margen Rx + OTC upsell) − (costo médico + CAC + delivery + plataforma)
```

Funciona cuando:

- La consulta está **acotada** (patologías simples, guías, médicos de bajo costo o asincrónicos).
- El **margen del medicamento** (y venta cruzada OTC) paga la consulta y el delivery.
- Hay **escala D2C** (millones de usuarios; no ciclos de venta institucional de 6–12 meses).
- El marco regulatorio lo tolera (receta, publicidad médica, conflictos de interés).

Es el patrón documentado en [China](../china/sistema-salud-publico-y-sector-privado.md) (consulta reembolsable → fulfillment comercial privado) llevado al extremo **vertical**: la plataforma es canal clínico y farmacia a la vez.

En Argentina es más difícil que en EE.UU. o China: márgenes farmacéuticos más comprimidos, OS/prepaga que ya pagan consulta, retail fragmentado y **conflicto de interés** si el médico «gratis» empuja receta en canal propio.

---

## Por qué no copiar ese modelo tal cual

| Factor | Startup D2C (ejemplo inversión USD 20M) | Bioenlace |
|--------|----------------------------------------|-----------|
| Cliente | Paciente (B2C) | Efector / prepaga (B2B) |
| Ingreso principal | Margen farmacia | Licencia clínica + add-ons + transacciones B2B |
| Fortaleza | Funnel D2C + checkout | Encounter + captura + guardia + agenda |
| Build faltante | Farmacia, delivery, escala marketing | Farmacia (~38%); sin red comercial propia |
| Ciclo de venta | Marketing / CAC | 1–12 meses institucional |

**Costos de IA** (referencia [`../../costos/costos-api.md`](../../costos/costos-api.md)): del orden de **USD 1,0–1,1/profesional/mes** en uso intensivo (motivos en 1 lote/consulta, Gemini Flash Lite, Groq STT). No es el cuello de botella; lo son sueldos, ventas e implementación.

Subsidiar consultas gratis compite en un juego de **capital de riesgo + margen retail**, no en el GTM B2B de Bioenlace.

La diferenciación por UI/UX **no se sostiene** solo bajando el precio del HIS: incumbentes compiten en features y relaciones; telemedicina D2C compite en CAC y farmacia.

---

## Propuesta: tres modelos diferenciados

Bioenlace en **Argentina** combina las [siete vías de ingreso](./mapa-vias-ingreso-bioenlace.md) del sector privado. La fórmula agrupa **fuentes institucionales** y el horizonte típico en AR.

```
Ingreso Bioenlace (AR) ≈
  licencia clínica + add-ons por módulo                    [Vía 1 — SaaS / HIS]
+ pack OS / prepaga + autorizaciones digitales              [Vía 2]
+ facturación RCM + % recupero opcional                     [Vía 3]
+ analytics financiador (PMPM) + proyectos auditoría B2G    [Vía 4]
+ recetas enrutadas + rev share farmacia                    [Vía 5 — puente, no retail]
+ licitación provincial / SUMAR + contrato marco            [Vía 6]
+ pathways + PMPM afiliado en programa                      [Vía 7 — financiador]
+ pathway fees completados                                  [Vía 7 — financiador]
− (costo IA + infra + ventas)
```

| Vía | Componente en la fórmula | Horizonte AR | Quién paga (AR) |
|-----|--------------------------|--------------|-----------------|
| **1** | Licencia base, add-ons guardia/internación/receta | **Corto** | Sanatorio, clínica, red ambulatoria |
| **2** | Pack OS, autorización en agenda/Encounter | **Mediano** | Sanatorio; prepaga/OS |
| **3** | RCM, factura–cobro–conciliación, fee transaccional o % recupero | **Mediano–largo** | Sanatorio alto volumen OS |
| **4** | Analytics PMPM, informes, licitación antifraude/glosas | **Largo** | OS grande, prepaga, PAMI, Estado |
| **5** | Receta digital enrutada, rev share | **Mediano** | Cadena farmacia; grupo sanitario con farmacia |
| **6** | Módulo licitado / contrato marco | **Largo** | Provincia, hospital público, SUMAR |
| **7** | PMPM programa + módulo pathways; **fee por pathway completado** | **Mediano** (referencia AR) | Prepaga/OS con modelo de riesgo / capitación |

**Lectura rápida:** núcleo **vía 1** (SaaS clínico). Escalón de ticket **vías 2 + 5** (autorización OS + puente receta). **Vías 3, 4 y 6** son enterprise/B2G. **Vía 7** es referencia para prepagas, no el modelo dominante en AR.

---

### Modelo A — Licencia modular (efector)

**Idea:** pricing según [matriz Argentina](./matriz-argentina-modulos-precios.md): **COGS documentado × (1 + margen sobre costo)**, por profesional y mes. El cliente elige qué `encounter_class` contrata (AMB / EMER / IMP), cuántos profesionales por cada una, y add-ons **audio** / **videollamada**.

| Componente | Descripción |
|------------|-------------|
| **Licencia** | `precio = COGS_ref × (vol_clase/400) × (1 + margen%)` — margen **233 %** ≈ ~70 % bruto; COGS **con context caching**; base ~**USD 3,16**/prof/mes; con audio ~**6,43**; con audio+video ~**USD 23,08**/prof/mes |
| **Clases** | AMB / EMER / IMP habilitan módulos; el precio escala con `encounters_per_professional_month` por clase |
| **Add-ons variables** | Audio (STT profesional ~5 min con **−30 % on-device** → COGS **0,98**) y/o videollamada (self-host, COGS **5,00**) — suman COGS y se reflejan en el precio |
| **Rev share Rx (opcional)** | USD 0,5–2 por receta enrutada a farmacia partner (vía 5; fuera de la fórmula COGS) |

**Quién paga:** director médico / COO / IT del efector.

**ROI comercial (operativo):** menos tiempo de documentación, menos no-shows (KPIs agenda), guardia con SLA — argumentos de eficiencia, no de retención de paciente.

**Encaje producto:** ambulatorio (~75%), agenda (~81%), guardia (~95%), internación (~82%), receta (~75%). Ver [informe ejecutivo](../../his-completo/informe-ejecutivo.md).

**Calculador público:** [`institucional/#precios`](../../../../institucional/index.html) (copy: «profesional», no PES).

---

### Modelo B — White-label financiador (prepaga / OS)

**Idea:** la prepaga u OS compra **canal digital + reglas de autorización** integradas al acto clínico, sin reemplazar de golpe su liquidación legacy.

- App y flujos con marca del financiador (matriz: USD 0,5–1,5/afiliado/mes).
- Handoff a red farmacéutica conveniada (vía 5).
- Pathways clínicos acotados (crónicos, renovación Rx) donde aplique el modelo de riesgo.

**Ingreso Bioenlace:**

- PMPM por afiliado incluido en el **programa** del financiador (canal digital / white-label), no por «retener paciente en un sanatorio».
- Módulo pack OS + autorizaciones (vía 2).
- **Pathway fees completados:** tarifa cuando el afiliado cumple el camino clínico acordado (ej. crónico: consulta + plan + control a 30 días; renovación Rx: teleconsulta + receta en red).
- Comisión negociada con red farmacéutica (rev share).

**Ejemplo pathway fee:** 8.000 pathways completados/mes × USD 3 = **USD 24k/mes** además de PMPM (orden de magnitud; contrato define qué cuenta como «completado»).

**Comprador:** gerente de producto / digital de prepaga u OS mediana.

**Build crítico:** autorización OS; API white-label; acuerdos de datos. Ver [mapa vías — autorización](./mapa-vias-ingreso-bioenlace.md).

---

### Modelo C — Receta puente + UX operativa (anti-clon D2C)

Mismo **recorrido UX** que la startup de referencia; **economía institucional**:

```
Turno → atención → receta en app → «Pedir en farmacia X» → delivery
         ↑                    ↑
    paga OS/particular    rev share farmacia (partner)
```

- La consulta **la paga quien ya paga hoy** (OS, prepaga, particular).
- Bioenlace monetiza: **licencia al efector** + **fee / rev share por receta enrutada** (vía 5).
- Delivery lo subsidia **farmacia o prepaga** como promo, no Bioenlace.

**Build crítico:** homologación receta nacional; integración 1–2 cadenas farmacia piloto.

---

### Modelo D — Freemium institucional (adquisición por UX)

Para clínicas 5–15 profesionales:

| Tier | Incluye | Precio |
|------|---------|--------|
| **Gratis** | Hasta N encounters/mes: ambulatorio básico | USD 0 |
| **Pago** | Superar umbral; más clases (EMER/IMP); audio; videollamada | Según [matriz](./matriz-argentina-modulos-precios.md) (COGS + margen) |

Compite con «HIS caro y complejo» sin regalar consultas al paciente final. COGS variable ~USD 0,95–3,5/prof/mes sin video (con caché; ver [costos-api](../../costos/costos-api.md)); precio lista base ~USD 3,16/prof/mes con margen ~70 % bruto.

---

## Unit economics orientativos (Argentina)

Supuestos: **clínica 20 profesionales**, ~8.000 encounters/año, ~670/mes.

| Concepto | Orden de magnitud (USD/mes) |
|----------|----------------------------|
| COGS IA+STT (20 prof, base sin video) | ~19–55 (0,95–2,73 × 20; [costos-api](../../costos/costos-api.md) con caché y −30 % STT) |
| COGS + videollamada (20 prof, audio+video) | ~139 (6,93 × 20) |
| Infra + soporte | 200–500 |
| Costo equipo (amortizado por cliente) | Variable según headcount; meta **gross margin ~70%** |
| **Precio lista** (20 prof, solo base, margen 233 %) | ~**63** (20 × 3,16) |
| **Precio lista** (20 prof + audio + videollamada) | ~**462** (20 × 23,08) |
| **Ingreso incremental Rx** (opcional) | 200 recetas/mes × USD 1 fee = **USD 200/mes**; rev share farmacia aparte |

El ingreso incremental por receta enrutada es **puente a fulfillment** (vía 5), no margen retail propio. Cifras de lista: [matriz Argentina](./matriz-argentina-modulos-precios.md).

---

## Escenarios por tipo de cliente (Argentina)

| Escenario | Modelo sugerido | Ticket orientativo/mes | Build prioritario |
|-----------|-----------------|------------------------|-------------------|
| Clínica 10 prof (solo base) | D → A | ~32–64 (matriz; ± audio) | Receta nacional |
| Clínica 10 prof + audio + videollamada | A | ~231 | Teleconsulta self-host |
| Sanatorio 80 camas (plantel mixto AMB+EMER+IMP) | A + C | Centenas–bajos miles según N profesionales y video; + rev share Rx | Autorización OS; partner farmacia |
| Prepaga piloto 50k afiliados | B | 25–75k PMPM + pack OS + pathway fees | White-label; API; autorización; reglas pathway |
| Red ambulatoria 5 sedes | A | Según Σ profesionales × precio unitario | Autorización OS; receta nacional |

---

## Diferenciación operativa

Argumentos de venta al **comprador institucional** (efector o financiador):

1. **Captura asistida:** menos tiempo de documentación por encounter.
2. **Agenda y guardia:** KPIs operativos (no-show, lead time, SLA guardia).
3. **Receta y autorización:** del acto clínico al cobro y la dispensación, sin operar farmacia.

La competencia HIS vende **módulos por licencia**. Bioenlace compite en **eficiencia clínica-administrativa** y en **vías 2 y 5** cuando el build lo permita.

---

## Posicionamiento sugerido

> **Bioenlace cobra licencia, add-ons, OS/receta y pathways al financiador; no cobra al sanatorio por fidelizar pacientes ni por sumar volumen a su cartera.**

| Horizonte | Acción comercial |
|-----------|------------------|
| **Corto** | Modelo A (matriz) + piloto Modelo C con 1–2 farmacias |
| **Mediano** | Modelo B con una prepaga; pack OS (vía 2) |
| **Demostración** | ROI operativo (documentación, agenda, guardia) en propuesta |

---

## Qué evitar

| Modelo | Riesgo |
|--------|--------|
| Consulta gratis universal D2C | Quema caja; CAC alto; regulación; GTM distinto |
| Ser farmacia / operador delivery | Capital intensivo; margen AR bajo |
| Solo bajar precio del HIS | Carrera al fondo vs incumbentes |
| Cobro al efector por «retener paciente» o «traer más pacientes» | Confunde producto clínico con marketing del hospital/OS |
| Variables al efector (resumen abierto, adherencia post-consulta) | Fuera de alcance; distinto de pathway fee al financiador |
| % del acto médico sin compliance | Complejidad legal con OS/prepagas |

---

## Relación con mapa de vías de ingreso

| Modelo Bioenlace | Vía del [mapa](./mapa-vias-ingreso-bioenlace.md) |
|------------------|--------------------------------------------------|
| A, D | 1 SaaS clínico |
| B | 2 autorización OS + 7 pathways (PMPM + pathway fees) |
| C | 5 retail Rx (puente, no retailer) |
| Rev share farmacia | 5 |

---

## Referencias

- [Mapa vías × Bioenlace](./mapa-vias-ingreso-bioenlace.md)
- [Matriz Argentina módulos y precios](./matriz-argentina-modulos-precios.md)
- [China — cuatro vías privadas](../china/sistema-salud-publico-y-sector-privado.md)
- [Costos IA API](../../costos/costos-api.md)
- [Informe ejecutivo madurez](../../his-completo/informe-ejecutivo.md)
