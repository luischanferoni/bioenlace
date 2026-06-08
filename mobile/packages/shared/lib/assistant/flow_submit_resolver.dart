/// Resuelve `body_template` del `flow_submit` con el draft del chat.
///
/// Valores `draft.<campo>?` son opcionales: si el draft no los trae, se omiten del body
/// (p. ej. teleconsulta hub sin `id_efector`; el API expande desde `slot_id`).
class FlowSubmitResolveResult {
  const FlowSubmitResolveResult({required this.body, required this.missing});

  final Map<String, dynamic> body;
  final List<String> missing;
}

FlowSubmitResolveResult resolveFlowSubmitBody(
  Map<String, dynamic> bodyTemplate,
  Map<String, dynamic> draft,
) {
  final body = <String, dynamic>{};
  final missing = <String>[];

  bodyTemplate.forEach((k, v) {
    final s = v?.toString().trim() ?? '';
    if (!s.startsWith('draft.')) {
      if (s.isNotEmpty) {
        body[k] = s;
      }
      return;
    }

    final optional = s.endsWith('?');
    final field = (optional ? s.substring(6, s.length - 1) : s.substring(6)).trim();
    if (field.isEmpty) {
      if (!optional) {
        missing.add(k);
      }
      return;
    }

    final str = draft[field]?.toString().trim() ?? '';
    if (str.isEmpty) {
      if (!optional) {
        missing.add(field);
      }
    } else {
      body[k] = str;
    }
  });

  return FlowSubmitResolveResult(body: body, missing: missing);
}
