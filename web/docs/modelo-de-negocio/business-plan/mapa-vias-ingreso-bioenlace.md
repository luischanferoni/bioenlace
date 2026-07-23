# Mapa: vías de ingreso × encaje Bioenlace

**Tipo:** business plan · estrategia producto  
**Última actualización:** 2026-05-27  
**Fuentes:** casos en `[../](../README.md)`, madurez en `[../../his-completo/informe-ejecutivo.md](../../his-completo/informe-ejecutivo.md)`

---

## Glosario — abreviaturas y términos en inglés

### Abreviaturas


| Sigla      | Significado                                           | En castellano / contexto                                                                   |
| ---------- | ----------------------------------------------------- | ------------------------------------------------------------------------------------------ |
| **HIS**    | *Hospital Information System*                         | Sistema de información hospitalario (historia, turnos, internación, facturación).          |
| **OS**     | *Obra Social* (Argentina)                             | Financiador prepago de salud; aquí no es *Operating System*.                               |
| **EPS**    | *Entidad Promotora de Salud* (Colombia)               | Financiador / aseguradora que administra el régimen contributivo y subsidio.               |
| **IPS**    | *Instituciones Prestadoras de Salud* (Colombia)       | Prestador que atiende afiliados de una EPS (equivalente a clínica/sanatorio).              |
| **RCM**    | *Revenue Cycle Management*                            | Gestión del ciclo de ingresos: acto clínico → factura/claim → cobro → conciliación.        |
| **B2G**    | *Business to Government*                              | Venta o licitación al Estado (ministerio, hospital público, programa).                     |
| **B2B2C**  | *Business to Business to Consumer*                    | Cliente institucional; el usuario final es el paciente (efector compra, paciente usa app). |
| **Rx**     | *Prescription*                                        | Receta médica / receta electrónica.                                                        |
| **PMPM**   | *Per Member Per Month*                                | Precio por afiliado por mes (típico en prepagas y analytics).                              |
| **UPC**    | *Unidad de Pago por Capitación* (Colombia)            | Monto fijo que recibe la EPS por afiliado; modelo de capitación.                           |
| **KPI**    | *Key Performance Indicator*                           | Indicador clave (no-show, lead time, % adherencia, etc.).                                  |
| **SLA**    | *Service Level Agreement*                             | Acuerdo de nivel de servicio (ej. tiempos máximos en guardia).                             |
| **ROI**    | *Return on Investment*                                | Retorno de la inversión para el comprador del software.                                    |
| **LIS**    | *Laboratory Information System*                       | Sistema de laboratorio; integración de resultados con la historia clínica.                 |
| **PMO**    | *Programa Médico Obligatorio* (Argentina)             | Nomenclador y prestaciones mínimas de las obras sociales.                                  |
| **COO**    | *Chief Operating Officer*                             | Director de operaciones; comprador frecuente en sanatorios.                                |
| **NHSA**   | *National Healthcare Security Administration* (China) | Administración nacional de seguro médico; auditoría y control de claims.                   |
| **RNOS**   | *Registro Nacional de Obras Sociales* (Argentina)     | Registro de obras sociales; contexto de auditoría y normativa.                             |
| **ANS**    | *Agência Nacional de Saúde Suplementar* (Brasil)      | Regulador de operadoras de salud privadas.                                                 |
| **CCSS**   | *Caja Costarricense de Seguro Social*                 | Seguro social integrado de Costa Rica.                                                     |
| **SUS**    | *Sistema Único de Saúde* (Brasil)                     | Sistema público de salud brasileño.                                                        |
| **IMSS**   | *Instituto Mexicano del Seguro Social*                | Seguro social mexicano.                                                                    |
| **FONASA** | *Fondo Nacional de Salud* (Chile)                     | Financiador público chileno.                                                               |
| **MLE**    | *Modalidad de Libre Elección* (Chile)                 | Régimen FONASA con elección de prestador y copagos.                                        |
| **MA**     | *Medicare Advantage* (EE.UU.)                         | Plan de Medicare gestionado por aseguradora privada (modelo capitado).                     |
| **GP**     | *General Practitioner* (Reino Unido)                  | Médico de cabecera en atención primaria.                                                   |


### Términos en inglés (negocio y producto)


| Término                         | Significado                                                                                                     |
| ------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| **wedge** (*cuña*)              | Punto de entrada comercial donde Bioenlace ya encaja fuerte (ambulatorio + Encounter + agenda + guardia).       |
| **Encounter**                   | Encuentro clínico atendido: unidad central del registro FHIR y del flujo Bioenlace.                             |
| **claim**                       | Reclamo de cobro al financiador (EE.UU. y modelos similares): traducción del acto clínico en ítems facturables. |
| **handoff**                     | Traspaso del paciente o del dato entre actores (consulta → autorización → farmacia → liquidación).              |
| **build**                       | Capacidad de producto que falta desarrollar o integrar («build faltante»).                                      |
| **retail**                      | Venta minorista al consumidor final; en vía 5, operar farmacia propia (Bioenlace no lo hace).                   |
| **checkout**                    | Cierre de compra en app o web (modelo China super-app).                                                         |
| **white-label**                 | Producto con marca del cliente (prepaga, OS) en lugar de Bioenlace.                                             |
| **pathway**                     | Camino clínico acotado y medible (crónico, renovación Rx, post-alta).                                           |
| **enterprise**                  | Segmento institucional grande (sanatorio, red, financiador) con integraciones pesadas.                          |
| **end-to-end**                  | De punta a punta (ej. guardia facturable desde ingreso hasta cobro).                                            |
| **one-shot**                    | Pago único por implementación o integración, no recurrente.                                                     |
| **rev share** (*revenue share*) | Reparto de ingresos con un socio comercial (ej. farmacia).                                                      |
| **upsell**                      | Venta de módulos adicionales al cliente existente.                                                              |
| **no-show**                     | Paciente que no asiste al turno reservado.                                                                      |
| **batch**                       | Procesamiento por lotes (archivos periódicos) en lugar de integración en tiempo real.                           |
| **outcomes**                    | Resultados clínicos medibles vinculados a adherencia o pathways.                                                |
| **loop**                        | Ciclo cerrado (ej. receta → dispensación → confirmación de cumplimiento).                                       |
| **retailer**                    | Operador que vende al consumidor final; Bioenlace actúa como puente, no retailer.                               |
| **prior authorization**         | Autorización previa del financiador antes de una práctica o estudio de alto costo.                              |
| **puente clínico**              | Rol de Bioenlace: receta digital y derivación sin operar farmacia ni logística.                                 |


---

## Resumen

Las vías de ingreso del sector privado documentadas por país (China, Argentina, Colombia, etc.) **no son todas atacables** por Bioenlace con el mismo esfuerzo ni el mismo horizonte de producto. Este mapa clasifica cada vía según:


| Criterio                | Pregunta que responde                                                                                          |
| ----------------------- | -------------------------------------------------------------------------------------------------------------- |
| **Encaje actual**       | ¿Qué tan listo está el producto hoy? (madurez en [informe ejecutivo](../../his-completo/informe-ejecutivo.md)) |
| **Quién paga**          | Efector, financiador (OS/prepaga), farmacia partner, Estado                                                    |
| **Cómo monetizar**      | Licencia, add-on, transacción, rev share, licitación                                                           |
| **Qué falta construir** | Gap de producto o integración antes de escalar ventas                                                          |


**Wedge actual de Bioenlace:** atención ambulatoria + Encounter unificado, agenda con KPIs, paciente digital, guardia operativa, planes de tratamiento y asistente conversacional. **No** es aún un HIS enterprise de facturación completa, operador de farmacia con stock ni retail Rx propio.

**En Argentina (lectura rápida):** el núcleo comercial hoy es **vía 1** (SaaS / HIS clínico). El escalón de ticket es **vía 2 + vía 5** (autorización OS + receta enrutada a farmacia). **Vías 3, 4 y 6** son enterprise o B2G (ciclos largos). **Vía 7** (PMPM + pathway fees) es referencia para prepagas con modelo de riesgo. **Fuera de alcance:** cobrar al efector por fidelizar pacientes en el sanatorio o por sumar volumen a su cartera; ex vía copagos/bolsillo como ingreso indirecto al efector. Detalle: [modelos-pricing-diferenciados.md](./modelos-pricing-diferenciados.md).

---

### Componentes de ingreso que aparecen en varias vías

Al leer la tabla y el detalle, conviene distinguir cuatro líneas que se repiten en la fórmula de ingreso y en propuestas comerciales:

#### Licencia base y add-ons por módulo (vía 1)

- **Licencia:** por profesional y mes = **COGS × (1 + margen sobre costo)**. El COGS sale de [costos-api.md](../../costos/costos-api.md); el margen y la lista comercial en [matriz-argentina-modulos-precios.md](./matriz-argentina-modulos-precios.md).
- **Clases de encounter (AMB / EMER / IMP):** el cliente contrata qué tipos de atención habilita y cuántos profesionales por cada uno. Mismo precio unitario; lo no contratado se deshabilita en producto.
- **Add-ons variables de costo:** audio (dictado del profesional) y videollamada — suman COGS y suben el precio unitario.


| Add-on / alcance (AR) | Para qué | Orden de magnitud (lista, vol. 400) |
| --------------------- | -------- | ----------------------------------- |
| Base (sin audio ni video) | IA + captura texto (COGS con caché) | ~**USD 3,16**/prof/mes |
| + Audio | Dictado (STT ~5 min, −30 % on-device) | ~**USD 6,43**/prof/mes |
| + Videollamada (incluye STT) | Teleconsulta self-host (§6) @ 40 % | ~**USD 12,25**/prof/mes |
| Clase EMER / IMP | Tablero guardia / internación | Escala si el volumen/mes difiere del de AMB |
| Receta electrónica / pack OS | Vías 2 y 5 | Cotización aparte (no en fórmula COGS) |


Ver [matriz-argentina-modulos-precios.md](./matriz-argentina-modulos-precios.md). Calculador: sitio institucional `#precios` (término público: **profesional**, no PES).

#### Fuera de la fórmula de ingreso

**Implementación**, **integraciones** (LIS, lab), **soporte** y **evolutivos** no entran en la fórmula agregada de ingreso Bioenlace; el modelo comercial se expresa en licencia, add-ons y el resto de vías. Pueden existir en contratos reales como costo operativo o acuerdo aparte, pero no se modelan como líneas de revenue en [modelos-pricing-diferenciados.md](./modelos-pricing-diferenciados.md).

#### Facturación RCM (vía 3)

**RCM** (*Revenue Cycle Management*): gestión del ciclo de ingresos del prestador — desde la atención documentada hasta el cobro y la conciliación con obra social o prepaga.

```
Encounter documentado
  → ítems nomenclador / PMO
  → factura o liquidación al financiador
  → seguimiento (glosas, rechazos)
  → cobro y conciliación contable
```

**Encaje Bioenlace hoy:** bajo (~38% facturación en informe ejecutivo). Hay nomenclador en parte del recorrido; falta el ciclo factura–cobro–contabilidad end-to-end.

**Monetización cuando exista el módulo:** suscripción por volumen facturado o puesto administrativo; opcional % sobre recupero (más complejo en AR). **Quién paga:** sanatorio con alto volumen OS. No es el comprador típico de una clínica ambulatoria chica.

#### Recetas enrutadas (vía 5)

El médico emite la **receta digital en Bioenlace** y el sistema **deriva al paciente** a dispensación en farmacia de red o partner («Retirar en X», checkout en app). Bioenlace **no** compra stock ni opera logística: hace el **puente clínico** (receta válida + handoff).

**Ingreso:** tarifa por receta enrutada y/o **rev share** con farmacia o prepaga. **Quién paga:** cadena farmacéutica o grupo sanitario con farmacia; el efector sigue pagando el software clínico.

**No confundir** con el modelo China (consulta gratis + margen retail propio). Bioenlace cobra por conectar consulta → dispensación, no por vender medicamentos. Build pendiente: homologación receta nacional, API con cadenas piloto.

---

### Fórmula de ingreso (Argentina, siete vías)

Vista agregada; empaquetado comercial en [modelos-pricing-diferenciados.md](./modelos-pricing-diferenciados.md#propuesta-tres-modelos-diferenciados):

```
Ingreso Bioenlace (AR) ≈
  licencia clínica + add-ons por módulo              [Vía 1]
+ pack OS + autorizaciones digitales                 [Vía 2]
+ facturación RCM + % recupero opcional              [Vía 3]
+ analytics financiador + auditoría B2G              [Vía 4]
+ recetas enrutadas + rev share farmacia             [Vía 5]
+ licitación / contrato marco                        [Vía 6]
+ pathways + PMPM afiliado en programa               [Vía 7]
+ pathway fees completados                           [Vía 7]
− (IA + infra + ventas)
```

---

## Tabla resumen


| #   | Vía (casos país)                   | Encaje Bioenlace        | Comprador principal              | Modelo de ingreso                   | Horizonte               |
| --- | ---------------------------------- | ----------------------- | -------------------------------- | ----------------------------------- | ----------------------- |
| 1   | SaaS / HIS clínico                 | **Alto**                | Efector privado, red ambulatoria | Licencia + add-ons                  | Corto                   |
| 2   | Autorización + liquidación OS/EPS  | **Medio → alto**        | Prestador; prepaga/OS            | Módulo premium; fee por transacción | Mediano                 |
| 3   | RCM / facturación al acto clínico  | **Bajo hoy**            | Prestador                        | SaaS + % recupero (opcional)        | Mediano–largo           |
| 4   | Auditoría / antifraude / analytics | **Bajo → medio**        | Financiador; Estado              | B2G licitación; SaaS analytics      | Largo                   |
| 5   | Retail Rx + delivery (China)       | **Puente, no retailer** | Farmacia / plataforma            | API por receta enrutada             | Mediano (partner)       |
| 6   | Compra pública de prestación / IT  | **Indirecto**           | Estado / CCSS / SUS              | Licitación; módulo + reporting      | Largo                   |
| 7   | Capitación / UPC (Colombia, MA)    | **Medio**               | EPS / aseguradora                | PMPM + pathway fees completados     | Mediano (fuera AR foco) |


---

## Detalle por vía

### 1. SaaS / HIS clínico

**Ejemplos en casos país:** hospitales públicos que compran IT (China, Alemania); sanatorios AR; clínicas Chile/Brasil; GP systems UK (EMIS, TPP).

**Qué es:** licencia de software para operar agenda, atención, guardia, internación, receta y reporting sobre un registro clínico común.

**Encaje Bioenlace:** **Alto.** Es el core vendible hoy: ambulatorio (~~75%), agenda (~~81%), guardia (~~95%), internación (~~82%), receta (~~75%), planes de tratamiento (~~75%).

**Dónde reduce costos para el comprador:**

- Menos tiempo de documentación (captura asistida texto/voz).
- Menos no-shows y mejor uso de agenda (KPIs staff).
- Guardia con SLA: menos caos operativo y horas improductivas.

**Quién paga:** director médico / COO / IT del **efector** (sanatorio, policlínica, red ambulatoria). En público: licitación provincial (ciclo largo).

**Cómo generar ingresos:** fee mensual por efector, por profesional activo o por módulo (licencia + add-ons).

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

**Quién paga:** cadena farmacéutica o plataforma de delivery (B2B2C); efector paga SaaS clínico, no el margen retail.

**Cómo generar ingresos:**

- Fee por receta enrutada o revenue share con partner.
- White-label de receta + checkout para grupo sanitario con farmacia propia.

**Build faltante:** homologación receta nacional; API de dispensación; partners comerciales; farmacia con stock (~38%) si se quiere cerrar loop.

**Prioridad:** **Mediano plazo vía partners** — no competir con retail; ser infraestructura clínica.

---

### 6. Compra pública de prestación e IT

**Ejemplos en casos país:** UK (NHS → privados); Costa Rica (CCSS outsourcing); Brasil (SUS compra servicios); México (IMSS); Argentina (provincias).

**Qué es:** el Estado o el seguro social integrado **contrata** prestación privada o software con presupuesto público.

**Encaje Bioenlace:** **Indirecto.** Producto vendible como módulo (ambulatorio, guardia, reporting) en licitación; no como reemplazo total del hospital público en corto plazo.

**Dónde reduce costos:**

- Mejor triage y guardia → menos saturación.
- Reporting de SLA para justificar contratos de compra de servicios.
- Digitalización ambulatoria sin papel.

**Quién paga:** ministerio provincial, hospital público, programa (SUMAR, etc.).

**Cómo generar ingresos:** licitación; contrato marco; módulo licitado (ingreso en la fórmula, no impl./soporte como línea separada).

**Build faltante:** requisitos soberanía de datos; export/reporting regulatorio; certificaciones que pida cada jurisdicción `[pendiente normativa]`.

**Prioridad:** **Largo plazo** — complemento al wedge privado; ciclos de venta largos.

---

### 7. Capitación / UPC (control de costo en aseguramiento)

**Ejemplos en casos país:** Colombia (UPC a EPS); EE.UU. (Medicare Advantage); Brasil (capitación en algunos modelos).

**Qué es:** el financiador recibe un monto fijo por afiliado y asume riesgo; incentivo a **reducir costo por caso** sin perder calidad.

**Encaje Bioenlace:** **Medio** vía planes de tratamiento, adherencia, pathways y analytics de utilización. No es foco Argentina inmediato (modelo más por evento/OS que UPC pura).

**Dónde reduce costos:**

- Menos reingresos por mala adherencia.
- Menos estudios duplicados con registro clínico unificado.
- Priorización de seguimiento crónico (pathways, adherencia staff ~75%).

**Quién paga:** EPS / aseguradora con modelo capitado.

**Cómo generar ingresos:**

- **PMPM** por afiliado en programa del financiador (canal digital / control de costo).
- **Pathway fees completados:** fee cuando el afiliado cumple el pathway definido en contrato (ej. crónico controlado, renovación Rx en red).

**Distinción importante:** el pathway fee lo paga la **prepaga/OS** por gestión de riesgo y calidad en capitación. **No** es un cobro al sanatorio porque el paciente «sigue yendo ahí» ni porque el hospital «consiguió más pacientes».

**Build faltante:** adherencia vinculada a outcomes; reglas por pathway medibles; integración automática lab/farmacia en planes.

**Prioridad:** **Mediano plazo** para expansión Colombia/Brasil; referencia para prepagas AR con modelos de riesgo.

---

## Lectura estratégica


| Horizonte   | Vías a priorizar                                         | Mensaje comercial                                               |
| ----------- | -------------------------------------------------------- | --------------------------------------------------------------- |
| **Corto**   | 1 (SaaS clínico)                                         | «Ambulatorio + agenda + guardia con ROI operativo»               |
| **Mediano** | 2 (autorización OS), 5 (puente receta), 7 (pathways)     | «Del acto clínico al cobro y la receta enrutada, sin retail»    |
| **Largo**   | 3 (RCM pleno), 4 (auditoría B2G), 6 (licitación pública) | «Enterprise + financiador + Estado»                             |


Ver matriz operativa para Argentina: [matriz-argentina-modulos-precios.md](./matriz-argentina-modulos-precios.md).  
Modelos de pricing diferenciados (B2B clínico): [modelos-pricing-diferenciados.md](./modelos-pricing-diferenciados.md).