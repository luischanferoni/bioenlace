// Sobre v3 de POST /api/v1/asistente/enviar (`message` | `interactive` | `flow`).

import 'assistant_composer_capture.dart';

/// Vista de un sobre `kind: flow` para el renderer del chat / acciones.
class AssistantFlowView {
  final String text;
  final String intentId;
  final String subintentId;
  final Map<String, dynamic> draftDelta;
  final Map<String, dynamic>? manifest;
  final List<Map<String, dynamic>> hints;
  final Map<String, dynamic>? openUi;
  final Map<String, dynamic>? flowSubmit;
  final Map<String, dynamic>? flowDismiss;
  final Map<String, dynamic>? composerCapture;
  final List<String> provides;
  final List<String> pendingFields;

  const AssistantFlowView({
    required this.text,
    required this.intentId,
    required this.subintentId,
    required this.draftDelta,
    this.manifest,
    this.hints = const [],
    this.openUi,
    this.flowSubmit,
    this.flowDismiss,
    this.composerCapture,
    this.provides = const [],
    this.pendingFields = const [],
  });

  static AssistantFlowView? fromEnvelope(Map<String, dynamic> envelope) {
    if (envelope['kind']?.toString() != 'flow') {
      return null;
    }

    final session = envelope['session'];
    final step = envelope['step'];
    final submit = envelope['submit'];
    final dismissRaw = envelope['dismiss'];
    final sess = session is Map ? Map<String, dynamic>.from(session) : <String, dynamic>{};
    final st = step is Map ? Map<String, dynamic>.from(step) : <String, dynamic>{};
    final sub = submit is Map ? Map<String, dynamic>.from(submit) : <String, dynamic>{};
    final dis = dismissRaw is Map ? Map<String, dynamic>.from(dismissRaw) : <String, dynamic>{};

    Map<String, dynamic>? openUi;
    if (st['active'] == true) {
      openUi = <String, dynamic>{
        'action_id': st['action_id']?.toString() ?? '',
        if (st['client_open'] is Map)
          'client_open': Map<String, dynamic>.from(st['client_open'] as Map),
      };
    }

    Map<String, dynamic>? flowSubmit;
    if (sub['active'] == true) {
      flowSubmit = <String, dynamic>{
        'route': sub['route']?.toString() ?? '',
        'method': sub['method']?.toString() ?? 'POST',
        if ((sub['label']?.toString().trim() ?? '').isNotEmpty)
          'label': sub['label'].toString().trim(),
        if (sub['body_template'] is Map)
          'body_template': Map<String, dynamic>.from(sub['body_template'] as Map),
      };
    }

    Map<String, dynamic>? flowDismiss;
    if (dis['active'] == true) {
      flowDismiss = <String, dynamic>{
        'label': dis['label']?.toString() ?? 'Entendido',
        if (dis['actions'] is List) 'actions': dis['actions'],
      };
    }

    Map<String, dynamic>? composerCapture;
    final cc = st['composer_capture'];
    if (cc is Map && isComposerCaptureActive(cc['active'])) {
      composerCapture = composerCaptureFromMap(Map<dynamic, dynamic>.from(cc));
    }

    final hintsRaw = envelope['hints'];
    final hints = hintsRaw is List
        ? hintsRaw
            .whereType<Map>()
            .map((e) => Map<String, dynamic>.from(e))
            .toList()
        : <Map<String, dynamic>>[];

    return AssistantFlowView(
      text: envelope['text']?.toString() ?? '',
      intentId: sess['intent_id']?.toString() ?? '',
      subintentId: sess['subintent_id']?.toString() ?? '',
      draftDelta: sess['draft_delta'] is Map
          ? Map<String, dynamic>.from(sess['draft_delta'] as Map)
          : <String, dynamic>{},
      manifest: envelope['manifest'] is Map
          ? Map<String, dynamic>.from(envelope['manifest'] as Map)
          : null,
      hints: hints,
      openUi: openUi,
      flowSubmit: flowSubmit,
      flowDismiss: flowDismiss,
      composerCapture: composerCapture,
      provides: st['provides'] is List
          ? List<String>.from((st['provides'] as List).map((e) => e.toString()))
          : <String>[],
      pendingFields: st['pending_fields'] is List
          ? List<String>.from((st['pending_fields'] as List).map((e) => e.toString()))
          : <String>[],
    );
  }
}
