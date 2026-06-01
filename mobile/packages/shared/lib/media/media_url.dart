import 'package:shared/config/api_config.dart';

/// Origen del sitio (sin `/api/v1`), alineado a [AppConfig.apiUrl].
String mediaSiteOrigin() {
  final base = AppConfig.apiUrl.replaceAll(RegExp(r'/$'), '');
  return Uri.parse('$base/').origin;
}

/// Convierte `content` de mensajes (ruta relativa o URL del host del request) en URL
/// cargable por la app móvil contra el mismo origen que la API.
String resolveMediaContentUrl(String content) {
  final trimmed = content.trim();
  if (trimmed.isEmpty) return trimmed;

  final origin = mediaSiteOrigin();

  if (trimmed.startsWith('http://') || trimmed.startsWith('https://')) {
    try {
      final uri = Uri.parse(trimmed);
      final path = uri.path;
      if (path.contains('/uploads/')) {
        return '$origin$path${uri.hasQuery ? '?${uri.query}' : ''}';
      }
    } catch (_) {
      return trimmed;
    }
    return trimmed;
  }

  var path = trimmed;
  if (!path.startsWith('/')) {
    path = '/$path';
  }
  return '$origin$path';
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
