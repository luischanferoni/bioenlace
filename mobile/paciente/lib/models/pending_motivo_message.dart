/// Cola local de mensajes de motivos (texto/audio) antes de subir al servidor.
enum PendingMotivoMessageStatus {
  /// Guardado local; listo para subir o reintentar.
  pendingUpload,

  /// Falló la subida; se puede reintentar o eliminar.
  failedUpload,
}

enum PendingMotivoMessageType { texto, audio }

class PendingMotivoMessage {
  PendingMotivoMessage({
    required this.id,
    required this.encounterId,
    required this.type,
    required this.status,
    required this.createdAt,
    required this.updatedAt,
    this.texto,
    this.audioFileName,
    this.transcript,
    this.sttMeta,
    this.lastError,
  });

  final String id;
  final int encounterId;
  final PendingMotivoMessageType type;
  final PendingMotivoMessageStatus status;
  final DateTime createdAt;
  final DateTime updatedAt;
  final String? texto;

  /// Nombre bajo el directorio del store (no ruta absoluta).
  final String? audioFileName;
  final String? transcript;
  final Map<String, dynamic>? sttMeta;
  final String? lastError;

  bool get hasAudio =>
      type == PendingMotivoMessageType.audio &&
      audioFileName != null &&
      audioFileName!.isNotEmpty;

  PendingMotivoMessage copyWith({
    PendingMotivoMessageStatus? status,
    DateTime? updatedAt,
    String? lastError,
    bool clearError = false,
  }) {
    return PendingMotivoMessage(
      id: id,
      encounterId: encounterId,
      type: type,
      status: status ?? this.status,
      createdAt: createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
      texto: texto,
      audioFileName: audioFileName,
      transcript: transcript,
      sttMeta: sttMeta,
      lastError: clearError ? null : (lastError ?? this.lastError),
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id,
    'encounter_id': encounterId,
    'type': type.name,
    'status': status.name,
    'created_at': createdAt.toIso8601String(),
    'updated_at': updatedAt.toIso8601String(),
    if (texto != null) 'texto': texto,
    if (audioFileName != null) 'audio_file_name': audioFileName,
    if (transcript != null) 'transcript': transcript,
    if (sttMeta != null) 'stt_meta': sttMeta,
    if (lastError != null) 'last_error': lastError,
  };

  factory PendingMotivoMessage.fromJson(Map<String, dynamic> json) {
    final typeName = json['type']?.toString() ?? 'audio';
    final statusName = json['status']?.toString() ?? 'pendingUpload';
    return PendingMotivoMessage(
      id: json['id']?.toString() ?? '',
      encounterId: _asInt(json['encounter_id']),
      type: PendingMotivoMessageType.values.firstWhere(
        (e) => e.name == typeName,
        orElse: () => PendingMotivoMessageType.audio,
      ),
      status: PendingMotivoMessageStatus.values.firstWhere(
        (e) => e.name == statusName,
        orElse: () => PendingMotivoMessageStatus.pendingUpload,
      ),
      createdAt:
          DateTime.tryParse(json['created_at']?.toString() ?? '') ??
          DateTime.now(),
      updatedAt:
          DateTime.tryParse(json['updated_at']?.toString() ?? '') ??
          DateTime.now(),
      texto: json['texto']?.toString(),
      audioFileName: json['audio_file_name']?.toString(),
      transcript: json['transcript']?.toString(),
      sttMeta:
          json['stt_meta'] is Map
              ? Map<String, dynamic>.from(json['stt_meta'] as Map)
              : null,
      lastError: json['last_error']?.toString(),
    );
  }

  static int _asInt(dynamic v) {
    if (v is int) return v;
    return int.tryParse('$v') ?? 0;
  }
}
