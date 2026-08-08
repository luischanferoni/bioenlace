# Textos de ejemplo — captura EMER (guardia)

Para pegar o dictar en la **captura clínica** de un encounter de guardia (`emer_standard`: motivos, signos vitales, diagnóstico, medicación, prácticas, indicaciones, derivaciones).

Contexto de producto: [captura clínica](../../producto/captura-clinica.md) · [urgencias / guardia](../../producto/urgencias-guardia.md).

La completitud de signos vitales y demás campos obligatorios la define el **modelo Yii / `EncounterDefinition`**, no la IA. Si faltan FR, glucemia, Glasgow, peso o talla, el gate de dominio lo pide aunque el texto suene “completo”.

---

## 1. Alta a domicilio (leve, completo)

> Paciente de 34 años consulta por odinofagia de 48 horas, febrícula y malestar general. TA 118/72, FC 88, FR 16, T 37,4, Sat 98 % aire ambiente, glucemia 98, Glasgow 15, peso 72 kg, talla 170 cm. Faringe eritematosa sin exudado, adenopatías cervicales leves. Diagnóstico: faringitis aguda. Indico ibuprofeno 400 mg cada 8 horas si dolor o fiebre, y amoxicilina 500 mg cada 8 horas por 7 días. Hidratación y reposo relativo. Control ambulatorio en 48–72 horas si no mejora o aparecen dificultad para tragar, disnea o fiebre alta. Alta a domicilio.

---

## 2. Analgesia + laboratorio + observación

> Ingreso por dolor abdominal epigástrico de seis horas con náuseas y un vómito. Sin fiebre referida. TA 130/80, FC 96, FR 18, T 36,8, Sat 99 %, glucemia 110, Glasgow 15, peso 80 kg, talla 175 cm. Abdomen blando, dolor a la palpación en epigastrio, sin defensa ni rebote. Diagnóstico presuntivo: gastritis / dispepsia a descartar. Hidratación EV, metoclopramida 10 mg EV, ranitidina o IBP EV según disponibilidad, dipirona 1 g EV. Solicito hemograma, glucemia, urea, creatinina, ionograma, amilasa/lipasa y ECG. Reevaluación en dos horas; si el dolor cede y labs normales, alta con dieta blanda e IBP oral.

---

## 3. Con errores / autocorrección (útil para IA)

> Dolor en el pecho izquierdo… perdón, en hemitórax derecho, desde anoche, tipo puntada, empeora con la inspiración. No es opresivo. TA 125/78, FC 78, FR 16, Sat 97 %, glucemia 105, Glasgow 15, peso 70 kg, talla 168 cm. Auscultación: murmullo disminuido… no, murmullo conservado bilateral, sin crepitantes. Sospecha inicial de IAM, me corrijo: más orientado a dolor musculoesquelético o pleural leve. Paracetamol 1 g VO. ECG sin cambios agudos. Alta con AINE si no hay contraindicación, y consulta si aparece disnea, sudoración o dolor opresivo.

---

## 4. Derivación a otro efector

> Politraumatismo leve por accidente de moto a baja velocidad. Consciente, GCS 15. TA 110/70, FC 102, FR 20, Sat 96 %, glucemia 120, peso 78 kg, talla 178 cm. Contusión en costado izquierdo y herida cortante en antebrazo que suturé con tres puntos. Rx de tórax sin neumotórax evidente. Requiere evaluación traumatológica y eventual tomografía que no disponemos. Derivación al Hospital de referencia en ambulancia, estable, con acceso EV permeable, analgesia con ketorolac 30 mg EV, ayuno, y resumen para el efector receptor. Familiar informado.

---

## 5. Pase a internación / UCI (pedido de cama)

> Crisis asmática moderada-grave. Disnea de 4 horas, uso de musculatura accesoria. TA 140/90, FC 118, FR 32, Sat 88 % aire ambiente, mejora a 93 % con O2 por bigotera, glucemia 130, Glasgow 15, peso 65 kg, talla 165 cm. Sibilancias difusas. Salbutamol nebulizado reiterado, bromuro de ipratropio, hidrocortisona 100 mg EV, O2. Diagnóstico: exacerbación asmática. No cede lo suficiente para alta: indico internación en sala / observación con monitoreo, nebulizaciones cada 20 minutos según protocolo, corticoides sistémicos y gases en sangre. Si hay deterioro, valorar UTI.

---

## 6. Medicación compleja + contraindicaciones

> Mujer de 68 años con HTA y FA anticoagulada con acenocumarol. Caída en domicilio, traumatismo craneano leve sin pérdida de conocimiento. TA 150/90, FC 84 irregular, FR 16, Sat 98 %, glucemia 115, Glasgow 15, peso 62 kg, talla 158 cm. Sin focalidad. ECG: FA conocida. No indico AINE por anticoagulación. Paracetamol 1 g VO. Solicito coagulación (RIN), hemograma y TC de cerebro por protocolo de TCE en anticoagulado. Suspendo dosis de hoy de acenocumarol hasta resultado. Observación neurológica horaria. Si TC normal y estable, alta con consignas de alarma y control con clínico.

---

## 7. Pedidos / prácticas (lab + imagen)

> Lumbociatalgia aguda derecha de 12 horas, sin déficit motor ni esfinteriano. TA 128/76, FC 80, FR 16, T 36,5, Sat 98 %, glucemia 100, Glasgow 15, peso 85 kg, talla 180 cm. Lasègue positivo a 40°. Diclofenac 75 mg IM, dexametasona 8 mg IM. Rx de columna lumbar. Si no hay banderas rojas y mejora parcial, alta con reposo relativo, AINE 3–5 días, y consulta por dolor si aparece paresia, anestesia en silla de montar o retención urinaria. No pido RMN en guardia.

---

## 8. Ambiguo / incompleto (para ver qué pide el sistema)

> Le duele la panza desde ayer. Le di algo para el dolor. Está mejor. Control.

---

## 9. Urgencia cardiovascular (más denso)

> Hombre de 55 años, tabaquista, dolor precordial opresivo de 40 minutos irradiado a mandíbula, con sudoración. TA 160/100, FC 110, FR 22, Sat 94 %, glucemia 140, Glasgow 15, peso 90 kg, talla 175 cm. ECG con supradesnivel en cara inferior. AAS 300 mg masticable, clopidogrel según protocolo, heparina, nitroglicerina sublingual si TA lo permite, O2. Diagnóstico: SCA con elevación del ST. Coordinar angioplastia / derivación inmediata a centro con hemodinamia. Ayuno, acceso EV, monitorización continua.

---

## 10. Pediátrico / febril + criterio de alta

> Niño de 3 años, fiebre de 39 °C de un día, decaído pero hidratado, sin vómitos incoercibles. T 38,8, FC 130, FR 28, Sat 98 %, glucemia 95, Glasgow 15, peso 14 kg, talla 95 cm, buen llenado capilar. Faringe congestiva, otoscopia normal, pulmón limpio. Paracetamol 15 mg/kg. Orientación: síndrome febril / faringitis. Alta con antipiréticos, abundantes líquidos, y reconsulta inmediata si letargo, rechaza líquidos, petequias o dificultad respiratoria.

---

## Tips al probar

- Para **internación/cama**, usá formulaciones como en el **5** («indico internación…», «pase a UTI»).
- Para **derivación**, nombrá destino + estado + qué llevás (como en el **4** y **9**).
- El **3** y el **8** sirven para ver correcciones y campos faltantes.
- Camino feliz post-triaje demo: empezá por el **1** o el **2** (con SV completos).
- Pedidos/lab van en la captura del encounter; el tablero de guardia no tiene atajo de alta rápida.
