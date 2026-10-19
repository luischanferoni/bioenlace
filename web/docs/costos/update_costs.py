import re

with open('costos-api.md', 'r', encoding='utf-8') as f:
    content = f.read()

# Reemplazar la sección de supuestos base
old_supuestos = r'- \*\*§1 Conversación con el paciente:\*\* 5 mensajes por encounter \(\~2\.660 llamadas Vertex por mes\)\. Misma cifra si el canal es app móvil o WhatsApp \(paridad de uso; no duplicar\)\.'
new_supuestos = r'- **§1 Conversación con el paciente:** 5 mensajes por encounter (~3.060 llamadas Vertex por mes: 2.000 preprocess + 1.060 guide). Arquitectura actual: preprocess -> discovery -> routing; guide (2ª IA) cuando routing_result = clara / incompletas. Misma cifra si el canal es app movil o WhatsApp (paridad de uso; no duplicar).'

content = re.sub(old_supuestos, new_supuestos, content)

# Reemplazar onboarding
old_onboarding = r'- \*\*§3 Onboarding y día a día:\*\* 400 llamadas a la IA por mes \(`asistente-onboarding` en metadata; en código reutiliza preprocess/conversacional\)'
new_onboarding = r'- **§3 Onboarding y día a día:** 400 llamadas a la IA por mes (`asistente-onboarding` en metadata; en código reutiliza preprocess/guide)'

content = re.sub(old_onboarding, new_onboarding, content)

with open('costos-api.md', 'w', encoding='utf-8') as f:
    f.write(content)

print('Updated costos-api.md')
