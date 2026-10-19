import re

with open('costos-api.md', 'r', encoding='utf-8') as f:
    content = f.read()

with open('section1_new.md', 'r', encoding='utf-8') as f:
    new_section = f.read()

# Encontrar y reemplazar la sección 1 completa
pattern = r'### 1\. Conversación con el paciente \(chat asistente\).*?(?=### 2\. Motivos de consulta)'
content = re.sub(pattern, new_section.strip() + '\n\n### 2. Motivos de consulta', content, flags=re.DOTALL)

with open('costos-api.md', 'w', encoding='utf-8') as f:
    f.write(content)

print('Replaced section 1 in costos-api.md')
