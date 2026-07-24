/// Borrador / cola local de captura clínica alineada al pipeline servidor.
enum PendingEncounterCaptureStatus {
  /// Texto/audio solo en el dispositivo.
  draft,

  /// Falló subir audio al servidor.
  pendingUpload,

  /// Audio en servidor; falta STT (o falló).
  pendingStt,

  /// Falló analizar; se puede reintentar desde transcript.
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
    this.serverCaptureId,
    this.serverStage,
    this.audioFileName,
    this.stt,
    this.lastError,
    this.analysisResponse,
    this.stagedItemIds = const [],
    this.audioUploaded = false,
  });

  final String id;
  final int personaId;
  final String parent;
  final int parentId;
  final String texto;
  final PendingEncounterCaptureStatus status;
  final DateTime createdAt;
  final DateTime updatedAt;

  /// ID numérico en servidor (`encounter_capture.id`).
  final int? serverCaptureId;

  /// Stage servidor (UPLOADED, TRANSCRIBED, …).
  final String? serverStage;

  /// Nombre de archivo bajo el directorio del store (no ruta absoluta).
  final String? audioFileName;
  final Map<String, dynamic>? stt;
  final String? lastError;
  final Map<String, dynamic>? analysisResponse;
  final List<String> stagedItemIds;
  final bool audioUploaded;

  bool get hasAudio => audioFileName != null && audioFileName!.isNotEmpty;

  PendingEncounterCapture copyWith({
    String? texto,
    PendingEncounterCaptureStatus? status,
    DateTime? updatedAt,
    int? serverCaptureId,
    bool clearServerCaptureId = false,
    String? serverStage,
    bool clearServerStage = false,
    String? audioFileName,
    bool clearAudio = false,
    Map<String, dynamic>? stt,
    bool clearStt = false,
    String? lastError,
    bool clearError = false,
    Map<String, dynamic>? analysisResponse,
    bool clearAnalysis = false,
    List<String>? stagedItemIds,
    bool? audioUploaded,
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
      serverCaptureId: clearServerCaptureId
          ? null
          : (serverCaptureId ?? this.serverCaptureId),
      serverStage:
          clearServerStage ? null : (serverStage ?? this.serverStage),
      audioFileName: clearAudio ? null : (audioFileName ?? this.audioFileName),
      stt: clearStt ? null : (stt ?? this.stt),
      lastError: clearError ? null : (lastError ?? this.lastError),
      analysisResponse:
          clearAnalysis ? null : (analysisResponse ?? this.analysisResponse),
      stagedItemIds: stagedItemIds ?? this.stagedItemIds,
      audioUploaded: audioUploaded ?? this.audioUploaded,
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
        if (serverCaptureId != null) 'server_capture_id': serverCaptureId,
        if (serverStage != null) 'server_stage': serverStage,
        if (audioFileName != null) 'audio_file_name': audioFileName,
        if (stt != null) 'stt': stt,
        if (lastError != null) 'last_error': lastError,
        if (analysisResponse != null) 'analysis_response': analysisResponse,
        'staged_item_ids': stagedItemIds,
        'audio_uploaded': audioUploaded,
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
      serverCaptureId: json['server_capture_id'] == null
          ? null
          : _asInt(json['server_capture_id']),
      serverStage: json['server_stage']?.toString(),
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
      audioUploaded: json['audio_uploaded'] == true,
    );
  }

  /// Mapea un ítem de `captura/listar` o `capture` del API a borrador local.
  factory PendingEncounterCapture.fromServerCapture(
    Map<String, dynamic> capture, {
    PendingEncounterCapture? local,
  }) {
    final clientId =
        capture['client_capture_id']?.toString() ?? local?.id ?? '';
    final stage = capture['stage']?.toString() ?? '';
    final transcript =
        (capture['transcript'] ?? capture['texto'] ?? local?.texto ?? '')
            .toString();
    final analysis = capture['analysis'] is Map
        ? Map<String, dynamic>.from(capture['analysis'] as Map)
        : (local?.analysisResponse);
    final staged = capture['staged_item_ids'];
    final now = DateTime.now();
    return PendingEncounterCapture(
      id: clientId.isNotEmpty ? clientId : (local?.id ?? ''),
      personaId: _asInt(capture['subject_persona_id'] ?? local?.personaId),
      parent: (capture['parent'] ?? local?.parent ?? '').toString(),
      parentId: _asInt(capture['parent_id'] ?? local?.parentId),
      texto: transcript,
      status: statusFromServerStage(stage),
      createdAt: DateTime.tryParse(capture['created_at']?.toString() ?? '') ??
          local?.createdAt ??
          now,
      updatedAt: DateTime.tryParse(capture['updated_at']?.toString() ?? '') ??
          now,
      serverCaptureId: capture['id'] == null ? local?.serverCaptureId : _asInt(capture['id']),
      serverStage: stage.isEmpty ? local?.serverStage : stage,
      audioFileName: local?.audioFileName,
      stt: capture['stt'] is Map
          ? Map<String, dynamic>.from(capture['stt'] as Map)
          : local?.stt,
      lastError: capture['last_error']?.toString() ?? local?.lastError,
      analysisResponse: analysis,
      stagedItemIds: staged is List
          ? staged.map((e) => e.toString()).toList()
          : (local?.stagedItemIds ?? const []),
      audioUploaded: capture['has_audio'] == true || (local?.audioUploaded ?? false),
    );
  }

  static PendingEncounterCaptureStatus statusFromServerStage(String stage) {
    switch (stage) {
      case 'UPLOADED':
      case 'STT_FAILED':
        return PendingEncounterCaptureStatus.pendingStt;
      case 'TRANSCRIBED':
      case 'ANALYSIS_FAILED':
        return PendingEncounterCaptureStatus.pendingAnalyze;
      case 'READY_FOR_REVIEW':
        return PendingEncounterCaptureStatus.pendingSave;
      case 'SAVE_FAILED':
        return PendingEncounterCaptureStatus.failedSave;
      default:
        return PendingEncounterCaptureStatus.draft;
    }
  }

  static int _asInt(dynamic v) {
    if (v is int) return v;
    return int.tryParse('$v') ?? 0;
  }
}
