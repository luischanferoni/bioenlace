# Modelos de pricing diferenciados — continuidad clínica B2B2C

**Tipo:** business plan · estrategia comercial  
**Última actualización:** 2026-05-27  
**Contexto:** diferenciación de **modelo de negocio** (no solo UI/UX) frente a HIS tradicionales y startups D2C «consulta gratis + farmacia».

---

## Resumen

El patrón de mercado «consulta gratuita si hay receta → compra en plataforma → delivery gratis» monetiza **margen farmacéutico y escala D2C**, no licencias de software hospitalario.

Bioenlace puede adoptar el **mismo flujo de experiencia** (Encounter → receta → fulfillment) sin ser retailer ni subsidiar consultas: pricing **B2B2C** alineado a **continuidad del paciente**, con base SaaS más componente variable y rev share opcional con farmacia partner.

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
| Ingreso principal | Margen farmacia | SaaS clínico + variables |
| Fortaleza | Funnel D2C + checkout | Encounter + UI/UX + guardia + adherencia |
| Build faltante | Farmacia, delivery, escala marketing | Farmacia (~38%); sin red comercial propia |
| Ciclo de venta | Marketing / CAC | 1–12 meses institucional |

**Costos de IA** (referencia [`../../costos/costos-api.md`](../../costos/costos-api.md)): del orden de **USD 7–8/profesional/mes** en uso intensivo (STT incluido). No es el cuello de botella; lo son sueldos, ventas e implementación.

Subsidiar consultas gratis compite en un juego de **capital de riesgo + margen retail**, no en el GTM B2B de Bioenlace.

La diferenciación por UI/UX **no se sostiene** solo bajando el precio del HIS: incumbentes compiten en features y relaciones; telemedicina D2C compite en CAC y farmacia.

---

## Propuesta: cuatro modelos diferenciados

### Modelo A — «Pago por continuidad» (recomendado)

**Idea:** cobrar menos por sillón/licencia y más por **resultados medibles** que el producto ya soporta o está cerca de soportar.

| Componente | Descripción | Diferencial |
|------------|-------------|-------------|
| **Base baja** | USD 5–12/prof/mes o pack clínica por debajo de [matriz tradicional](./matriz-argentina-modulos-precios.md) | Entrada más fácil que HIS clásico |
| **Variable por hitos** | Fee cuando el paciente: confirma turno, asiste, abre resumen, completa adherencia, retira/compra Rx vía partner | Ingreso alineado a retención, no a seats |
| **Rev share farmacia (opcional)** | USD 0.5–2 por receta enrutada a partner | Bioenlace no opera farmacia; cobra puente clínico |

**Quién paga:** sanatorio/clínica (recupera no-shows y reconsultas); opcionalmente farmacia partner por lead calificado.

**ROI ejemplo:** bajar 5 puntos de no-show en 400 turnos/mes a USD 50 de ticket medio → **USD 1.000/mes** recuperados; justifica base + variable.

**Encaje producto:** agenda (~81%), resumen paciente, planes de adherencia (~75%), receta (~75%). Ver [informe ejecutivo](../../his-completo/informe-ejecutivo.md).

---

### Modelo B — «White-label de retención» (prepaga / OS)

**Idea:** la prepaga no compra «un HIS»; compra **que el afiliado no se vaya a la competencia después de la consulta**.

- App y flujo post-consulta con marca del financiador (ver matriz: USD 0.5–1.5/afiliado/mes).
- Handoff a **farmacia de red** conveniada, no farmacia propia de Bioenlace.
- Teleconsulta subsidiada **solo** en pathways acotados (crónicos, renovación Rx), no consulta gratis universal.

**Ingreso Bioenlace:**

- PMPM bajo por afiliado activo en app.
- Fee por afiliado que completa pathway (consulta → resumen → Rx → control).
- Comisión negociada con red farmacéutica (split típico: prepaga / farmacia / Bioenlace).

**Comprador:** gerente de producto / digital de prepaga u OS mediana.

**Build crítico:** módulo autorización OS; API white-label; acuerdos de datos. Ver [mapa vías — autorización](./mapa-vias-ingreso-bioenlace.md).

---

### Modelo C — «Consulta no gratis, UX sin fricción» (anti-clon D2C)

Mismo **recorrido UX** que la startup de referencia; **economía distinta**:

```
Turno → teleconsulta → receta en app → «Pedir en farmacia X» → delivery
         ↑                    ↑
    paga OS/particular    margen farmacia (rev share)
```

- La consulta **la paga quien ya paga hoy** (OS, prepaga, copago, particular).
- Bioenlace monetiza: SaaS al efector + **fee por receta digital enrutada** + premium UX/IA.
- Delivery «gratis» lo subsidia **farmacia o prepaga** como promo comercial, no Bioenlace.

**Ventaja:** no quema caja ni requiere ronda de USD 20M; el efector mejora retención; la farmacia paga demanda calificada (CAC menor que performance marketing).

**Build crítico:** homologación receta nacional; integración 1–2 cadenas farmacia piloto.

---

### Modelo D — Freemium institucional (adquisición por UX)

Para clínicas 5–15 profesionales:

| Tier | Incluye | Precio |
|------|---------|--------|
| **Gratis** | Hasta N encounters/mes: ambulatorio + resumen paciente básico | USD 0 |
| **Pago** | Superar umbral; guardia; OS; analytics; IA ilimitada | Según [matriz](./matriz-argentina-modulos-precios.md) |

Compite con «HIS caro y complejo» sin regalar consultas al paciente final. Costo marginal bajo en clínicas chicas (IA ~USD 1–3/prof/mes en uso moderado con optimizaciones; ver [estrategias API](../../costos/estrategias-api.md)).

---

## Unit economics orientativos (Argentina)

Supuestos: **clínica 20 profesionales**, ~8.000 encounters/año, ~670/mes.

| Concepto | Orden de magnitud (USD/mes) |
|----------|----------------------------|
| Costo IA (20 prof, uso medio) | 100–200 |
| Infra + soporte | 200–500 |
| Costo equipo (amortizado por cliente) | Variable según headcount; meta **gross margin SaaS ~70%** |
| **Precio tradicional** (matriz) | 1.500–3.000 |
| **Precio «continuidad» (Modelo A)** | 800–1.500 base + 0.3–1 por encounter con resumen entregado + 0.5–2 por Rx enrutada |

**Ejemplo variable Rx:** 200 recetas/mes enrutadas × USD 1 fee Bioenlace = **USD 200/mes** extra. Si farmacia partner paga USD 3 por Rx (lead + cumplimiento) = **USD 600/mes**.

La consulta no se subsidia; el ingreso incremental viene de **continuidad medible** y **puente a fulfillment**, no de margen retail propio.

---

## Escenarios por tipo de cliente (Argentina)

| Escenario | Modelo sugerido | Ticket orientativo/mes | Build prioritario |
|-----------|-----------------|------------------------|-------------------|
| Clínica 10 prof | D (freemium) → A | 500–1.200 base + variable | Receta nacional |
| Sanatorio 80 camas | A + C | 5–12k base + variable + rev share Rx | Autorización OS; partner farmacia |
| Prepaga piloto 50k afiliados | B | 25–75k PMPM + pathway fees | White-label; API; autorización |
| Red ambulatoria 5 sedes | A | 3–8k + variable por sede | KPIs ROI dashboard comercial |

---

## UI/UX como parte del modelo de negocio

La experiencia no es solo estética; es el **mecanismo de cobro variable**:

1. **Un solo hilo paciente:** turno → consulta → resumen claro → receta → «qué hago ahora» → adherencia → control.
2. **Asistente:** menos carga al médico (captura); más guía post-consulta al paciente (continuidad).
3. **Dashboard ROI** para el comprador: no-show ↓, % resumen abierto, % adherencia, % Rx completada en partner.

La competencia HIS vende **módulos**. Bioenlace puede vender **«porcentaje de pacientes que terminan el camino»**.

Métricas ya parcialmente en producto: KPIs agenda (no-show, lead time), adherencia planes staff. Falta: **% resumen abierto**, **% Rx enrutada/completada** como KPIs comerciales explícitos.

---

## Posicionamiento sugerido

> **Bioenlace no cobra por pantallas; cobra porque el paciente no se pierde después de la consulta.**

| Horizonte | Acción comercial |
|-----------|------------------|
| **Corto** | Modelo A (base + variable continuidad) + piloto Modelo C con 1–2 farmacias |
| **Mediano** | Modelo B con una prepaga con red de farmacias |
| **Demostración** | Dashboard ROI en propuesta comercial (no solo demo funcional) |

---

## Qué evitar

| Modelo | Riesgo |
|--------|--------|
| Consulta gratis universal D2C | Quema caja; CAC alto; regulación; GTM distinto |
| Ser farmacia / operador delivery | Capital intensivo; margen AR bajo |
| Solo bajar precio del HIS | Carrera al fondo vs incumbentes |
| % del acto médico sin compliance | Complejidad legal con OS/prepagas |

---

## Relación con mapa de vías de ingreso

| Modelo Bioenlace | Vía del [mapa](./mapa-vias-ingreso-bioenlace.md) |
|------------------|--------------------------------------------------|
| A, D | 1 SaaS clínico + 6 copagos indirectos (retención) |
| B | 2 autorización OS + 8 pathways / control costo |
| C | 5 retail Rx (puente, no retailer) |
| Rev share farmacia | 5 + parte de 6 |

---

## Referencias

- [Mapa vías × Bioenlace](./mapa-vias-ingreso-bioenlace.md)
- [Matriz Argentina módulos y precios](./matriz-argentina-modulos-precios.md)
- [China — cuatro vías privadas](../china/sistema-salud-publico-y-sector-privado.md)
- [Costos IA API](../../costos/costos-api.md)
- [Informe ejecutivo madurez](../../his-completo/informe-ejecutivo.md)
