/// Borrador / cola local de captura clínica (nota + audio) antes de subir al servidor.
enum PendingEncounterCaptureStatus {
  /// Texto/audio guardados localmente; aún no hay análisis usable.
  draft,

  /// Falló analizar (red u otro error); se puede reintentar.
  pendingAnalyze,

  /// Análisis listo; falta confirmar/guardar en servidor.
  pendingSave,

  /// Falló guardar en servidor; se puede reintentar.
  failedSave,
}

class PendingEncounterCapture {
  PendingEncounterCapture({
    required this.id,
    required this.personaId,
    required this.parent,
    required this.parentId,
    required this.texto,
    required this.status,
    required this.createdAt,
    required this.updatedAt,
    this.audioFileName,
    this.stt,
    this.lastError,
    this.analysisResponse,
    this.stagedItemIds = const [],
  });

  final String id;
  final int personaId;
  final String parent;
  final int parentId;
  final String texto;
  final PendingEncounterCaptureStatus status;
  final DateTime createdAt;
  final DateTime updatedAt;

  /// Nombre de archivo bajo el directorio del store (no ruta absoluta).
  final String? audioFileName;
  final Map<String, dynamic>? stt;
  final String? lastError;
  final Map<String, dynamic>? analysisResponse;
  final List<String> stagedItemIds;

  bool get hasAudio => audioFileName != null && audioFileName!.isNotEmpty;

  PendingEncounterCapture copyWith({
    String? texto,
    PendingEncounterCaptureStatus? status,
    DateTime? updatedAt,
    String? audioFileName,
    bool clearAudio = false,
    Map<String, dynamic>? stt,
    bool clearStt = false,
    String? lastError,
    bool clearError = false,
    Map<String, dynamic>? analysisResponse,
    bool clearAnalysis = false,
    List<String>? stagedItemIds,
  }) {
    return PendingEncounterCapture(
      id: id,
      personaId: personaId,
      parent: parent,
      parentId: parentId,
      texto: texto ?? this.texto,
      status: status ?? this.status,
      createdAt: createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
      audioFileName: clearAudio ? null : (audioFileName ?? this.audioFileName),
      stt: clearStt ? null : (stt ?? this.stt),
      lastError: clearError ? null : (lastError ?? this.lastError),
      analysisResponse:
          clearAnalysis ? null : (analysisResponse ?? this.analysisResponse),
      stagedItemIds: stagedItemIds ?? this.stagedItemIds,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'persona_id': personaId,
        'parent': parent,
        'parent_id': parentId,
        'texto': texto,
        'status': status.name,
        'created_at': createdAt.toIso8601String(),
        'updated_at': updatedAt.toIso8601String(),
        if (audioFileName != null) 'audio_file_name': audioFileName,
        if (stt != null) 'stt': stt,
        if (lastError != null) 'last_error': lastError,
        if (analysisResponse != null) 'analysis_response': analysisResponse,
        'staged_item_ids': stagedItemIds,
      };

  factory PendingEncounterCapture.fromJson(Map<String, dynamic> json) {
    final statusName = json['status']?.toString() ?? 'draft';
    final status = PendingEncounterCaptureStatus.values.firstWhere(
      (e) => e.name == statusName,
      orElse: () => PendingEncounterCaptureStatus.draft,
    );
    final staged = json['staged_item_ids'];
    return PendingEncounterCapture(
      id: json['id']?.toString() ?? '',
      personaId: _asInt(json['persona_id']),
      parent: json['parent']?.toString() ?? '',
      parentId: _asInt(json['parent_id']),
      texto: json['texto']?.toString() ?? '',
      status: status,
      createdAt: DateTime.tryParse(json['created_at']?.toString() ?? '') ??
          DateTime.now(),
      updatedAt: DateTime.tryParse(json['updated_at']?.toString() ?? '') ??
          DateTime.now(),
      audioFileName: json['audio_file_name']?.toString(),
      stt: json['stt'] is Map
          ? Map<String, dynamic>.from(json['stt'] as Map)
          : null,
      lastError: json['last_error']?.toString(),
      analysisResponse: json['analysis_response'] is Map
          ? Map<String, dynamic>.from(json['analysis_response'] as Map)
          : null,
      stagedItemIds: staged is List
          ? staged.map((e) => e.toString()).toList()
          : const [],
    );
  }

  static int _asInt(dynamic v) {
    if (v is int) return v;
    return int.tryParse('$v') ?? 0;
  }
}
