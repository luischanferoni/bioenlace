import 'package:shared/config/api_config.dart';

/// Origen del sitio (sin `/api/v1`), alineado a [AppConfig.apiUrl].
String mediaSiteOrigin() {
  final base = AppConfig.apiUrl.replaceAll(RegExp(r'/$'), '');
  return Uri.parse('$base/').origin;
}

/// Base API (`https://host/api/v1`).
String mediaApiBase() {
  final base = AppConfig.apiUrl.replaceAll(RegExp(r'/$'), '');
  return base;
}

/// Solo nombre de archivo (sin carpetas ni URL).
bool _isBareMediaFilename(String value) {
  final t = value.trim().replaceFirst(RegExp(r'^/+'), '');
  return t.isNotEmpty &&
      !t.contains('/') &&
      RegExp(r'^[a-zA-Z0-9._-]+$').hasMatch(t);
}

String _secureMediaApiPath({
  required String scope,
  required int encounterId,
  required String filename,
}) {
  final bare = filename.trim().replaceFirst(RegExp(r'^/+'), '');
  return '/api/v1/media/$scope/$encounterId/${Uri.encodeComponent(bare)}';
}

/// Convierte rutas legacy `/uploads/...` a URL protegida `/api/v1/media/...`.
String? _legacyUploadsToSecureApiPath(String path) {
  final p = path.startsWith('/') ? path : '/$path';
  final motivos = RegExp(
    r'^/uploads/motivos_consulta/(\d+)/([^/]+)$',
  ).firstMatch(p);
  if (motivos != null) {
    final id = int.parse(motivos.group(1)!);
    final file = motivos.group(2)!;
    return _secureMediaApiPath(
      scope: 'motivos-consulta',
      encounterId: id,
      filename: file,
    );
  }
  final chat = RegExp(
    r'^/uploads/consulta_chat/(\d+)/([^/]+)$',
  ).firstMatch(p);
  if (chat != null) {
    final id = int.parse(chat.group(1)!);
    final file = chat.group(2)!;
    return _secureMediaApiPath(
      scope: 'consulta-chat',
      encounterId: id,
      filename: file,
    );
  }
  return null;
}

/// Convierte `content` de mensajes en URL cargable con Bearer (API media o legacy uploads).
///
/// [mediaScope] y [encounterId] son necesarios si [content] es solo el nombre del archivo.
String resolveMediaContentUrl(
  String content, {
  String? mediaScope,
  int? encounterId,
}) {
  final trimmed = content.trim();
  if (trimmed.isEmpty) return trimmed;

  final origin = mediaSiteOrigin();

  if (trimmed.startsWith('http://') || trimmed.startsWith('https://')) {
    try {
      final uri = Uri.parse(trimmed);
      final path = uri.path;
      final secure = _legacyUploadsToSecureApiPath(path);
      if (secure != null) {
        return '$origin$secure';
      }
      if (path.contains('/api/v1/media/') || path.contains('/api/v')) {
        return trimmed;
      }
      if (path.contains('/uploads/')) {
        final mapped = _legacyUploadsToSecureApiPath(path);
        if (mapped != null) {
          return '$origin$mapped';
        }
      }
      // URL legacy con solo /archivo.jpg al final del host
      final lastSeg = path.split('/').where((s) => s.isNotEmpty).lastOrNull;
      if (lastSeg != null &&
          _isBareMediaFilename(lastSeg) &&
          mediaScope != null &&
          encounterId != null &&
          encounterId > 0) {
        return '$origin${_secureMediaApiPath(scope: mediaScope, encounterId: encounterId, filename: lastSeg)}';
      }
    } catch (_) {
      return trimmed;
    }
    return trimmed;
  }

  if (trimmed.startsWith('/api/v1/media/') || trimmed.startsWith('/api/v')) {
    return trimmed.startsWith('/api/')
        ? '$origin$trimmed'
        : trimmed;
  }

  if (trimmed.startsWith('/api/')) {
    return '$origin$trimmed';
  }

  // Solo nombre de archivo (p. ej. guardado así en BD)
  if (_isBareMediaFilename(trimmed) &&
      mediaScope != null &&
      encounterId != null &&
      encounterId > 0) {
    return '$origin${_secureMediaApiPath(scope: mediaScope, encounterId: encounterId, filename: trimmed)}';
  }

  var path = trimmed;
  if (!path.startsWith('/')) {
    path = '/$path';
  }

  final secure = _legacyUploadsToSecureApiPath(path);
  if (secure != null) {
    return '$origin$secure';
  }

  if (path.startsWith('/uploads/')) {
    return '$origin$path';
  }

  // Un segmento tipo /20260601_archivo.jpg — no concatenar a /api/v1/
  final segments = path.split('/').where((s) => s.isNotEmpty).toList();
  if (segments.length == 1 &&
      _isBareMediaFilename(segments.first) &&
      mediaScope != null &&
      encounterId != null &&
      encounterId > 0) {
    return '$origin${_secureMediaApiPath(scope: mediaScope, encounterId: encounterId, filename: segments.first)}';
  }

  // Sin contexto: no inventar URL bajo /api/v1/
  return trimmed;
}

/// Ruta local del dispositivo (vista previa antes de subir).
bool isLocalMediaFilePath(String value) {
  final t = value.trim();
  if (t.isEmpty ||
      t.startsWith('http://') ||
      t.startsWith('https://') ||
      t.startsWith('blob:')) {
    return t.startsWith('blob:');
  }
  if (t.startsWith('uploads/') || t.startsWith('/uploads/')) {
    return false;
  }
  if (t.contains('/api/v1/media/') || t.contains('/api/v')) {
    return false;
  }
  if (_isBareMediaFilename(t)) {
    return false;
  }
  return t.contains(':\\') ||
      t.startsWith('/data/') ||
      t.startsWith('/var/') ||
      t.startsWith('/private/') ||
      (t.startsWith('/') && !t.startsWith('/uploads'));
}

bool isImageMessageType(String? type) {
  final t = type?.toLowerCase() ?? '';
  return t == 'imagen' || t == 'image';
}

int? _encounterIdFromMessage(Map<String, dynamic> message) {
  final raw = message['encounter_id'] ?? message['consulta_id'];
  if (raw == null) return null;
  if (raw is int) return raw;
  return int.tryParse(raw.toString());
}

/// Normaliza `content` de mensajes con adjuntos para el origen de [AppConfig.apiUrl].
void normalizeChatMediaMessage(
  Map<String, dynamic> message, {
  String? mediaScope,
  int? encounterId,
}) {
  final type = message['message_type']?.toString() ?? '';
  if (!isImageMessageType(type) &&
      type != 'audio' &&
      type != 'video' &&
      type != 'documento') {
    return;
  }
  final content = message['content']?.toString() ?? '';
  if (content.isEmpty || isLocalMediaFilePath(content)) return;

  final id = encounterId ?? _encounterIdFromMessage(message);
  message['content'] = resolveMediaContentUrl(
    content,
    mediaScope: mediaScope,
    encounterId: id,
  );
}

List<dynamic> normalizeChatMediaMessages(
  List<dynamic> messages, {
  String? mediaScope,
  int? encounterId,
}) {
  return messages.map((m) {
    if (m is Map<String, dynamic>) {
      normalizeChatMediaMessage(
        m,
        mediaScope: mediaScope,
        encounterId: encounterId,
      );
      return m;
    }
    if (m is Map) {
      final copy = Map<String, dynamic>.from(m);
      normalizeChatMediaMessage(
        copy,
        mediaScope: mediaScope,
        encounterId: encounterId,
      );
      return copy;
    }
    return m;
  }).toList();
}
