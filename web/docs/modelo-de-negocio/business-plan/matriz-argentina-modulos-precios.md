# Matriz Argentina — módulos × precio × build faltante

**Tipo:** business plan · go-to-market Argentina  
**Última actualización:** 2026-05-27  
**Alcance:** solo Argentina por ahora; otros países cuando haya validación local.

Precios **orientativos** en USD; no incluyen IVA ni implementación salvo donde se indica. Validar con tamaño de efector, cantidad de profesionales y integraciones.

**Referencia de madurez:** [`../../his-completo/informe-ejecutivo.md`](../../his-completo/informe-ejecutivo.md) (niveles 0–4).

**Alcance comercial:** precios por **licencia, add-ons e integraciones** al comprador institucional. No se cotiza por retención del paciente ni por hitos de uso del paciente (resumen abierto, adherencia, etc.). Ver [modelos-pricing-diferenciados.md](./modelos-pricing-diferenciados.md) y [mapa de vías](./mapa-vias-ingreso-bioenlace.md) (siete vías; sin ex vía copagos/bolsillo).

---

## Compradores típicos en Argentina

| Comprador | Motivación de compra | Ciclo de venta |
|-----------|----------------------|----------------|
| **Sanatorio privado mediano** | Operación ambulatoria + guardia; OS en red; eficiencia y cobro a financiador | 3–6 meses |
| **Clínica / policlínica ambulatoria** | Agenda, atención, receta; poco internación | 1–3 meses |
| **Grupo sanitario / prepaga** | Canal digital paciente; autorización; no reemplazar liquidación legacy de golpe | 6–12 meses |
| **Obra social mediana** | Autorizaciones; auditoría; portal prestadores | 6–12 meses |
| **Hospital público provincial** | Licitación; módulos acotados | 12–24 meses |

---

## Matriz principal

| Módulo Bioenlace | Madurez actual | Comprador AR típico | Precio orientativo | Build faltante (principal) | Plazo venta |
|------------------|----------------|---------------------|--------------------|----------------------------|-------------|
| **Ambulatorio + Encounter** | ~75% | Sanatorio, clínica | **USD 8–25/prof./mes** o **USD 1.5–4k/mes** por efector chico | HC longitudinal médica; derivaciones estructuradas; retiro UI legacy «consulta» | Corto |
| **Agenda y turnos** | ~81% | Todos los efectores | Incluido en base o **USD 2–6k/mes** standalone grande | Autorización OS en reserva; teleconsulta nativa; lista espera inter-servicio | Corto |
| **Paciente digital** (app + resumen post-atención) | ~75% | Sanatorio, prepaga white-label | **USD 1–3k/mes** o **USD 0.5–1.5/afiliado activo/mes** (prepaga) | Más acciones self-service; integración medios de pago (opcional) | Corto |
| **Guardia / urgencias** | ~95% | Sanatorio con guardia | **USD 3–8k/mes** add-on | SLA admin UI; aviso sonoro; catálogo LIS en pedidos guardia | Corto |
| **Internación** | ~82% | Sanatorio con camas | **USD 2–6k/mes** add-on | Firma digital alta; facturación episodio; handoff post-alta ambulatorio | Mediano |
| **Planes de tratamiento + adherencia** | ~75% | Sanatorio crónicos; prepaga | **USD 1–3k/mes** | Adherencia → outcomes (labs); IA sugerencias con aprobación médica | Corto–mediano |
| **Receta electrónica** | ~75% | Todos | Incluido o **USD 500–2k/mes** | **Homologación receta nacional**; integración redes farmacia | Corto (bloqueante mercado) |
| **Laboratorio** (integración externa) | ~63% | Sanatorio, clínica | **USD 2–8k** one-shot + **USD 200–800/mes** mant. | Lab propio in-house (fuera de scope); más conectores LIS | Corto |
| **Captura asistida / asistente** | Alto (diferenciador) | Todos | Incluido o **USD 3–10/prof./mes** premium IA | Costos IA acotados por institución; más intents operativos | Corto |
| **Autorización OS / prepaga** | ~0–25% (gap) | Sanatorio, prepaga, OS | **USD 5–15k/mes** prepaga; **USD 2–5k/mes** sanatorio | Reglas PMO/cartilla; flujo en agenda y Encounter; conectores por financiador | Mediano |
| **Facturación / liquidación OS** | ~38% | Sanatorio alto volumen OS | **USD 3–10k/mes** + posible fee transaccional | Ciclo factura–cobro; nomenclador completo; glosas y conciliación | Mediano–largo |
| **Analytics financiador** (utilización, glosas) | Gap | OS, prepaga grande | **USD 8–25k/mes** o **USD 0.1–0.3/afiliado/mes** | Data pipeline; reglas auditoría; anonimización | Largo |
| **Farmacia + dispensación** | ~38% | Sanatorio con farmacia propia | Proyecto **USD 30–80k** + **USD 2–5k/mes** | Stock, dispensación, validación, cierre con receta nacional | Largo (bajo demanda) |
| **Quirófano enterprise** | ~50% | Sanatorio quirúrgico | Proyecto **USD 40–100k** | Partes formales, checklist OMS, tablero salas, stock pabellón | Largo (bajo demanda) |
| **API / white-label prepaga** | API v1 existente | Prepaga, grupo sanitario | **USD 10–40k/mes** según volumen API | SLAs; sandbox; documentación comercial; acuerdos de datos | Mediano |

---

## Paquetes sugeridos (Argentina)

### Pack «Ambulatorio digital» — clínica / consultorio

**Para:** clínica 5–30 profesionales, sin guardia.

| Incluye | Precio orientativo |
|---------|-------------------|
| Encounter ambulatorio, agenda, receta (PDF), resumen paciente, app paciente básica | **USD 1.5–3k/mes** |
| Implementación + capacitación | **USD 3–8k** one-shot |
| Integración 1 laboratorio externo | **USD 2–5k** one-shot |

**Build crítico antes de escalar:** receta nacional homologada.

---

### Pack «Sanatorio operativo» — ambulatorio + guardia

**Para:** sanatorio 50–200 camas, guardia activa, mix OS/prepaga/particular.

| Incluye | Precio orientativo |
|---------|-------------------|
| Pack ambulatorio + guardia + internación básica + KPIs agenda + adherencia | **USD 8–20k/mes** |
| Implementación | **USD 15–40k** one-shot |
| Add-on captura IA premium | **+USD 500–2k/mes** |

**Build crítico:** autorización OS en agenda (upsell); firma alta internación.

---

### Pack «Financiador digital» — prepaga / OS mediana

**Para:** prepaga u OS que quiere canal paciente + autorización sin reemplazar liquidación core.

| Incluye | Precio orientativo |
|---------|-------------------|
| Portal/API autorización, reglas cartilla, app paciente white-label, reporting utilización | **USD 10–30k/mes** |
| Integración con sistema liquidación legacy (batch/API) | **USD 20–60k** one-shot |

**Build crítico:** módulo autorización (vía #2 del mapa); acuerdos de datos por financiador.

---

## Priorización comercial (Argentina)

Orden alineado al [informe ejecutivo](../../his-completo/informe-ejecutivo.md) y al [mapa de vías](./mapa-vias-ingreso-bioenlace.md):

| Orden | Iniciativa comercial | Módulo | Efecto en ticket |
|-------|----------------------|--------|------------------|
| 1 | Vender **Pack ambulatorio** a clínicas y sanatorios | Ambulatorio + agenda + paciente | Base recurrente |
| 2 | **Receta nacional** como requisito y add-on regulatorio | Receta electrónica | Desbloquea LATAM; habilita receta enrutada (vía 5) |
| 3 | Add-on **guardia** donde ya hay cliente ambulatorio | Guardia | +USD 3–8k/mes |
| 4 | **Autorización OS** a sanatorios con dolor de glosas | Autorización | +USD 2–5k/mes; puerta a prepaga |
| 5 | **Pack financiador** a 1 prepaga piloto | API + autorización + app | Ticket alto, pocas cuentas |
| 6 | Facturación integrada bajo demanda enterprise | Facturación | Proyecto, no volumen |

---

## Supuestos y límites

- No incluye **comisiones por acto médico** ni modelo «% del cobro OS» salvo acuerdo explícito en RCM.
- **Hospital público provincial:** usar rangos de licitación solo como referencia; precio final muy variable; ciclo >12 meses.
- **PAMI** y **fuerzas:** segmento institucional aparte; requisitos específicos `[pendiente]`.
- Cifras en USD; para ARS aplicar tipo de cambio y ajuste inflacionario al cotizar.

---

## Referencias

- [Argentina — vías de ingreso privado](../argentina/sistema-salud-publico-y-sector-privado.md)
- [Mapa vías × Bioenlace](./mapa-vias-ingreso-bioenlace.md) (siete vías)
- [Modelos de pricing](./modelos-pricing-diferenciados.md)
- [Informe ejecutivo madurez](../../his-completo/informe-ejecutivo.md)
