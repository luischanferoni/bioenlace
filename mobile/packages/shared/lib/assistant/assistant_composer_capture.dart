/// Placeholder por defecto del composer del asistente (sin paso `composer_capture` activo).
/// Corto: cabe en ~2 líneas del input.
const String kAssistantComposerDefaultHint =
    'Escribí tu consulta… Ej.: Quiero agendar un turno';

bool isComposerCaptureActive(dynamic active) {
  if (active == true || active == 1) return true;
  return active?.toString().toLowerCase() == 'true';
}

Map<String, dynamic>? composerCaptureFromMap(Map<dynamic, dynamic> raw) {
  final route = raw['route']?.toString().trim() ?? '';
  final field = raw['draft_field']?.toString().trim() ?? '';
  if (route.isEmpty || field.isEmpty) {
    return null;
  }
  final bodyTemplate = raw['body_template'];
  return <String, dynamic>{
    'draft_field': field,
    'placeholder': raw['placeholder']?.toString() ?? '',
    'min_length': raw['min_length'] is int
        ? raw['min_length'] as int
        : int.tryParse(raw['min_length']?.toString() ?? '') ?? 1,
    'action_id': raw['action_id']?.toString() ?? '',
    'route': route,
    'method': raw['method']?.toString().trim().isNotEmpty == true
        ? raw['method'].toString().trim()
        : 'POST',
    if (bodyTemplate is Map)
      'body_template': Map<String, dynamic>.from(bodyTemplate),
  };
}

/// Resuelve la config activa de `composer_capture` (estado, historial o manifiesto del flow).
Map<String, dynamic>? resolveActiveComposerCapture({
  Map<String, dynamic>? activeCapture,
  List<Map<String, dynamic>> chatHistory = const [],
  int? flowActivationSeq,
  Map<String, dynamic>? flowManifest,
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
    if (cc is Map) {
      final parsed = composerCaptureFromMap(Map<dynamic, dynamic>.from(cc));
      if (parsed != null) return parsed;
    }
    final fm = m['flow_manifest'];
    if (fm is Map) {
      final fromManifest = _composerCaptureFromManifest(Map<String, dynamic>.from(fm));
      if (fromManifest != null) return fromManifest;
    }
  }

  if (flowManifest != null) {
    return _composerCaptureFromManifest(flowManifest);
  }

  return null;
}

Map<String, dynamic>? _composerCaptureFromManifest(Map<String, dynamic> manifest) {
  final activeStep = manifest['active_step'];
  if (activeStep is! Map) return null;
  final cc = activeStep['composer_capture'];
  if (cc is! Map) return null;
  return composerCaptureFromMap(Map<dynamic, dynamic>.from(cc));
}

/// Hint del composer: `composer_capture.placeholder` del YAML o texto por defecto.
String assistantComposerHintText({
  Map<String, dynamic>? activeCapture,
  List<Map<String, dynamic>> chatHistory = const [],
  int? flowActivationSeq,
  Map<String, dynamic>? flowManifest,
}) {
  final cc = resolveActiveComposerCapture(
    activeCapture: activeCapture,
    chatHistory: chatHistory,
    flowActivationSeq: flowActivationSeq,
    flowManifest: flowManifest,
  );
  final placeholder = cc?['placeholder']?.toString().trim() ?? '';
  if (placeholder.isNotEmpty) {
    return placeholder;
  }
  return kAssistantComposerDefaultHint;
}
