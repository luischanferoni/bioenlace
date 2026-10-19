import re

with open('infra-costos.md', 'r', encoding='utf-8') as f:
    content = f.read()

# Reemplazar texto de supuesto
content = content.replace('preprocess en cada mensaje + 2.ª IA solo conversacional; operativo con match PHP → **~2.660 llamadas/mes**',
                         'preprocess en cada mensaje + guide cuando routing_result = clara/incompletas → **~3.060 llamadas/mes** (2.000 preprocess + 1.060 guide)')

# Reemplazar valores numéricos
content = content.replace('Llamadas IA (chat) | **~2.660**',
                         'Llamadas IA (chat) | **~3.060**')

content = content.replace('2.660 × $0.021',
                         '3.060 × $0.021')

content = content.replace('**aprox. $55.90/médico/mes**',
                         '**aprox. $64.30/médico/mes**')

content = content.replace('2.660 × ($0.0035–0.0095)',
                         '3.060 × ($0.0035–0.0095)')

content = content.replace('**aprox. $9.30–25.30/médico/mes**',
                         '**aprox. $10.70–29.10/médico/mes**')

with open('infra-costos.md', 'w', encoding='utf-8') as f:
    f.write(content)

print('Updated infra-costos.md')
