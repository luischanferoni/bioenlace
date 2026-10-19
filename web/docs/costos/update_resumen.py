import re

with open('resumen-costos-bioenlace.md', 'r', encoding='utf-8') as f:
    content = f.read()

# Reemplazar costo de conversación
content = content.replace('**Costo mensual (5.000 profesionales):** **USD 2.250**',
                         '**Costo mensual (5.000 profesionales):** **USD 3.850**')

# Reemplazar tabla escenario base
content = content.replace('Conversación con el paciente                   | 2.250',
                         'Conversación con el paciente                   | 3.850')

content = content.replace('**Subtotal**                                        | **11.800**',
                         '**Subtotal**                                        | **13.400**')

# Reemplazar tabla escenario intensivo
content = content.replace('Conversación con el paciente                   | 2.250',
                         'Conversación con el paciente                   | 3.850')

content = content.replace('**Total**                                      | **18.250**',
                         '**Total**                                      | **19.850**')

# Reemplazar nota de por profesional
content = content.replace('Por profesional y mes (escenario intensivo, COGS base sin caché): **~USD 3,65**',
                         'Por profesional y mes (escenario intensivo, COGS base sin caché): **~USD 3,97**')

with open('resumen-costos-bioenlace.md', 'w', encoding='utf-8') as f:
    f.write(content)

print('Updated resumen-costos-bioenlace.md')
