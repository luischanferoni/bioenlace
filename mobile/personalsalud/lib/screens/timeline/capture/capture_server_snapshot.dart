/// Estado de una captura según la respuesta del servidor (`capture` en el contrato).
class CaptureServerSnapshot {
  final String? stage;
  final String? stageLabel;
  final String? stageIntent;
  final int? id;
  final bool hasAudio;
  final String transcript;
  final Map<String, dynamic> raw;

  const CaptureServerSnapshot({
    required this.stage,
    required this.stageLabel,
    required this.stageIntent,
    required this.id,
    required this.hasAudio,
    required this.transcript,
    required this.raw,
  });

  /// Devuelve null si la respuesta no trae el objeto `capture`.
  static CaptureServerSnapshot? fromResponse(Map<String, dynamic> response) {
    final capture = response['capture'];
    if (capture is! Map) return null;
    final map = Map<String, dynamic>.from(capture);
    final stage = map['stage']?.toString();
    final metadata = map['stage_metadata'];
    String? label;
    String? intent;
    if (metadata is Map && metadata['code'] == stage) {
      label = metadata['label']?.toString();
      intent = metadata['intent']?.toString();
    }
    final rawId = map['id'];
    return CaptureServerSnapshot(
      stage: stage,
      stageLabel: label,
      stageIntent: intent,
      id: rawId is int ? rawId : int.tryParse('$rawId'),
      hasAudio: map['has_audio'] == true,
      transcript: map['transcript']?.toString() ?? '',
      raw: map,
    );
  }
}
