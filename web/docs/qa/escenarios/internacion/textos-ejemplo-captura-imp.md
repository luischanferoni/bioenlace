# Textos de ejemplo — captura IMP (internación)

Para pegar o dictar en la **captura clínica** de un encounter de internación (`imp_standard`: evolución, medicación, indicaciones, régimen, balance hídrico).

Contexto de producto: [captura clínica](../../producto/captura-clinica.md) · [internación](../../producto/internacion.md) · escenario [episodio neumonía](./episodio-neumonia.md).

Abrí HC con `parent=INTERNACION` (Atender desde mapa/listado de camas). La integridad de campos la define el **modelo Yii / `EncounterDefinition`**, no la IA.

El **alta administrativa** (epicrisis, checklist, liberar cama) es el flow `internacion.alta-estructurada-flow`; estos textos documentan la evolución clínica del día. Podés anticipar el egreso en la captura y completar el alta estructurada después.

---

## 1. Ingreso / primera evolución (neumonía)

> Primer día de internación. Ingresa desde guardia por neumonía de la comunidad, lóbulo inferior derecho. TA 128/78, FC 92, FR 22, T 38,2, Sat 94 % con O2 2 L/min por bigotera, glucemia 118, Glasgow 15, peso 78 kg, talla 172 cm. Auscultación: crepitantes en base derecha. Hemograma con leucocitosis, PCR elevada. Continúa amoxicilina-clavulánico 1,2 g EV cada 8 horas, paracetamol 1 g EV si fiebre o dolor. Régimen: dieta blanda, hidratación EV. Balance hídrico: ingresos 2200 ml, egresos 1800 ml, balance +400. Indico gases en sangre, hemocultivos si fiebre > 38,5, Rx de tórax de control en 48 horas. Vigilancia de Sat y FR.

---

## 2. Evolución día 2 — mejoría (camino feliz)

> Segundo día de internación por neumonía. Sin fiebre hace veinticuatro horas. TA 120/70, FC 78, FR 18, T 36,6, Sat 96 % aire ambiente, glucemia 105, Glasgow 15. Menos crepitantes en base derecha. Deambula con ayuda. Continúa antibiótico EV. Régimen: dieta general, líquidos a demanda. Balance hídrico equilibrado. Laboratorio de control mañana (hemograma, PCR, urea, creatinina). Si sigue afebril y Sat estable, valorar alta en 24–48 horas con antibiótico oral.

---

## 3. Cambio de medicación + régimen

> Evolución: dolor postoperatorio de colecistectomía controlado. TA 118/72, FC 80, FR 16, T 36,8, Sat 98 %, glucemia 110, Glasgow 15. Suspendo morfínica EV. Paso a tramadol 50 mg VO cada 8 horas si dolor, y metoclopramida 10 mg VO SOS náuseas. Profilaxis con enoxaparina 40 mg SC diaria. Régimen: dieta líquida clara hoy, progresar a blanda mañana si tolera. Balance: ingresos 1800, egresos 1600, +200. Indico deambulación asistida y control de herida por enfermería.

---

## 4. Balance hídrico + Insuficiencia cardíaca

> Paciente con ICC descompensada, día 3. Disnea de esfuerzo leve. TA 110/70, FC 88 irregular (FA conocida), FR 20, T 36,5, Sat 93 % con O2 1 L, glucemia 130, Glasgow 15, peso 82 kg (bajó 1,5 kg desde ingreso). Crepitantes bibasales escasos, edemas ++ en miembros inferiores. Furosemida 40 mg EV cada 12 horas, digoxina según protocolo, bisoprolol. Régimen: hiposódico, líquidos restringidos a 1500 ml/día. Balance hídrico: ingresos 1400, egresos 2100, balance −700. Indico ionograma, creatinina y peso diario. Si diuresis cae o Sat < 90 %, reevaluar.

---

## 5. Deterioro — valorar UTI / intensificar

> Empeoramiento respiratorio en paciente con neumonía. Uso de musculatura accesoria. TA 100/60, FC 118, FR 30, T 38,8, Sat 88 % con O2 4 L, glucemia 145, Glasgow 14. Gases con hipoxemia. Intensifico O2, nebulizaciones, solicitar UTI / cuidados intermedios. Hemocultivos, gases, Rx de tórax urgente. Suspendo vía oral; acceso EV permeable. Familiar informado. Régimen: ayuno. Balance pendiente de UTI.

---

## 6. Con errores / autocorrección (útil para IA)

> Evolución: tercer día… perdón, segundo día de internación por celulitis de miembro inferior izquierdo… derecho. TA 125/80, FC 86, FR 16, T 37,1, Sat 98 %, glucemia 102, Glasgow 15. Eritema en retroceso. Cefazolina 1 g EV cada 8 horas… corrijo: clindamicina 600 mg EV cada 8 horas por alergia a penicilina documentada. Régimen general. Balance equilibrado. Si sigue bien, alta mañana con antibiótico oral.

---

## 7. Ambiguo / incompleto (para ver qué pide el sistema)

> Está mejor. Sigue el tratamiento. Control mañana.

---

## 8. Alta clínica (previa al flow de egreso)

> Egreso por mejoría. Neumonía de la comunidad tratada, afebril 48 horas, Sat 97 % aire ambiente, deambula solo. TA 118/70, FC 72, FR 16, T 36,4, glucemia 98, Glasgow 15. Paso a amoxicilina 875 mg VO cada 12 horas por 3 días más. Régimen libre. Indicaciones: control ambulatorio en 7 días, reposo relativo, hidratación, reconsulta si fiebre, disnea o dolor torácico. Epicrisis a completar en alta estructurada.

---

## 9. Pediátrico — gastroenteritis / hidratación

> Niño de 4 años, segundo día de internación por gastroenteritis aguda con deshidratación moderada. Tolera líquidos orales. T 36,9, FC 110, FR 22, Sat 98 %, glucemia 90, Glasgow 15, peso 16 kg (recuperó 400 g). Sin vómitos en 12 horas. Suspensión gradual de hidratación EV; ondansetrón SOS. Régimen: líquidos claros, progresar a dieta blanda. Balance: ingresos 1200, egresos 900, +300. Si mantiene tolerancia, alta hoy o mañana con consignas de rehidratación oral.

---

## 10. Interconsulta / práctica en piso

> Evolución: fractura de cadera operada, día 1 postquirúrgico. TA 130/80, FC 84, FR 16, T 36,7, Sat 97 %, glucemia 125, Glasgow 15. Dolor controlado con dipirona EV y tramadol SOS. Enoxaparina profiláctica. Solicito interconsulta a kinesiología para sedestación y deambulación asistida. Rx de control de cadera. Régimen blando. Balance equilibrado. Vigilancia de herida y signos de TVP.

---

## Tips al probar

- Empezá por el **1** (ingreso) y seguí con el **2** o el **8** (alta clínica) en el mismo episodio.
- Para **régimen** y **balance**, nombrá **ingresos** y **egresos** por separado (el modelo guarda filas `Ingreso` / `Egreso` en ml, no el “balance neto”). Ej.: «ingresos 2200 ml, egresos 1800 ml». El neto +400 es informativo; si la IA arma solo «balance +400», el sistema pedirá elegir Ingreso o Egreso.
- El **5** sirve para deterioro / pedido de UTI; el alta administrativa sigue siendo el flow de egreso.
- El **6** y el **7** prueban autocorrección y campos faltantes.
- Tras una evolución de alta (**8**), cerrá el episodio con [alta estructurada](../../producto/internacion.md#alta-estructurada-flow) (epicrisis + liberar cama).
