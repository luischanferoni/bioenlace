import re

with open('estrategias-reduccion/matriz-casos-uso.md', 'r', encoding='utf-8') as f:
    content = f.read()

# Agregar nota sobre volumen al final de la sección de flujo real
if 'Volumen por profesional' not in content:
    content = content.replace('**Nota:** incompletas y charla usan el mismo contexto `asistente-guide` (`GuideChannel` / `GuidePromptAssembler`).',
                         '**Nota:** incompletas y charla usan el mismo contexto `asistente-guide` (`GuideChannel` / `GuidePromptAssembler`). Volumen por profesional: ~3.060 llamadas/mes (2.000 preprocess + 1.060 guide).')

with open('estrategias-reduccion/matriz-casos-uso.md', 'w', encoding='utf-8') as f:
    f.write(content)

print('Updated matriz-casos-uso.md')
