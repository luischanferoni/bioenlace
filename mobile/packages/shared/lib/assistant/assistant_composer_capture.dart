/// Placeholder por defecto del composer del asistente (sin paso `composer_capture` activo).
const String kAssistantComposerDefaultHint =
    'Escribe tu consulta aquí... Ejemplo: "Necesito ver mis consultas" o "Quiero agendar un turno"';

/// Resuelve la config activa de `composer_capture` (estado + último mensaje del flow).
Map<String, dynamic>? resolveActiveComposerCapture({
  Map<String, dynamic>? activeCapture,
  List<Map<String, dynamic>> chatHistory = const [],
  int? flowActivationSeq,
}) {
  if (activeCapture != null) {
    final route = activeCapture['route']?.toString().trim() ?? '';
    if (route.isNotEmpty) {
      return activeCapture;
    }
  }

  for (var i = chatHistory.length - 1; i >= 0; i--) {
    final m = chatHistory[i];
    if (m['type']?.toString() != 'bot') continue;
    if (m['flow_superseded'] == true) continue;
    if (flowActivationSeq != null &&
        m['flow_activation_seq'] != null &&
        m['flow_activation_seq'] != flowActivationSeq) {
      continue;
    }
    final cc = m['composer_capture'];
    if (cc is! Map) continue;
    final route = cc['route']?.toString().trim() ?? '';
    if (route.isEmpty) continue;
    return Map<String, dynamic>.from(cc);
  }

  return null;
}

/// Hint del composer: `composer_capture.placeholder` del YAML o texto por defecto.
String assistantComposerHintText({
  Map<String, dynamic>? activeCapture,
  List<Map<String, dynamic>> chatHistory = const [],
  int? flowActivationSeq,
}) {
  final cc = resolveActiveComposerCapture(
    activeCapture: activeCapture,
    chatHistory: chatHistory,
    flowActivationSeq: flowActivationSeq,
  );
  final placeholder = cc?['placeholder']?.toString().trim() ?? '';
  if (placeholder.isNotEmpty) {
    return placeholder;
  }
  return kAssistantComposerDefaultHint;
}
