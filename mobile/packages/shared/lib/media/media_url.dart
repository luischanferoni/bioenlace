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

/// Convierte rutas legacy `/uploads/...` a URL protegida `/api/v1/media/...`.
String? _legacyUploadsToSecureApiPath(String path) {
  final p = path.startsWith('/') ? path : '/$path';
  final motivos = RegExp(
    r'^/uploads/motivos_consulta/(\d+)/([^/]+)$',
  ).firstMatch(p);
  if (motivos != null) {
    final id = motivos.group(1)!;
    final file = Uri.encodeComponent(motivos.group(2)!);
    return '/api/v1/media/motivos-consulta/$id/$file';
  }
  final chat = RegExp(
    r'^/uploads/consulta_chat/(\d+)/([^/]+)$',
  ).firstMatch(p);
  if (chat != null) {
    final id = chat.group(1)!;
    final file = Uri.encodeComponent(chat.group(2)!);
    return '/api/v1/media/consulta-chat/$id/$file';
  }
  return null;
}

/// Convierte `content` de mensajes en URL cargable con Bearer (API media o legacy uploads).
String resolveMediaContentUrl(String content) {
  final trimmed = content.trim();
  if (trimmed.isEmpty) return trimmed;

  final origin = mediaSiteOrigin();
  final apiBase = mediaApiBase();

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
    } catch (_) {
      return trimmed;
    }
    return trimmed;
  }

  if (trimmed.startsWith('/api/')) {
    return '$origin$trimmed';
  }

  var path = trimmed;
  if (!path.startsWith('/')) {
    path = '/$path';
  }

  final secure = _legacyUploadsToSecureApiPath(path);
  if (secure != null) {
    return '$origin$secure';
  }

  if (path.startsWith('/api/v1/media/')) {
    return '$origin$path';
  }

  // Fallback: path bajo el host (no debería usarse para uploads clínicos).
  if (path.startsWith('/uploads/')) {
    return '$origin$path';
  }

  return '$apiBase$path';
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

/// Normaliza `content` de mensajes con adjuntos para el origen de [AppConfig.apiUrl].
void normalizeChatMediaMessage(Map<String, dynamic> message) {
  final type = message['message_type']?.toString() ?? '';
  if (!isImageMessageType(type) &&
      type != 'audio' &&
      type != 'video' &&
      type != 'documento') {
    return;
  }
  final content = message['content']?.toString() ?? '';
  if (content.isEmpty || isLocalMediaFilePath(content)) return;
  message['content'] = resolveMediaContentUrl(content);
}

List<dynamic> normalizeChatMediaMessages(List<dynamic> messages) {
  return messages.map((m) {
    if (m is Map<String, dynamic>) {
      normalizeChatMediaMessage(m);
      return m;
    }
    if (m is Map) {
      final copy = Map<String, dynamic>.from(m);
      normalizeChatMediaMessage(copy);
      return copy;
    }
    return m;
  }).toList();
}
