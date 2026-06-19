import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

/// Mensaje de error desde cuerpo JSON típico de la API Bioenlace.
String? apiMessageFromJson(dynamic json) {
  if (json is! Map) {
    return null;
  }
  final message = json['message']?.toString().trim();
  if (message != null && message.isNotEmpty) {
    return message;
  }
  final error = json['error']?.toString().trim();
  if (error != null && error.isNotEmpty) {
    return error;
  }
  final errors = json['errors'];
  if (errors is Map) {
    final generic = errors['_error'];
    if (generic is List && generic.isNotEmpty) {
      final first = generic.first?.toString().trim();
      if (first != null && first.isNotEmpty) {
        return first;
      }
    }
  }
  return null;
}

/// Mensaje legible a partir de status HTTP + cuerpo de respuesta.
String messageFromHttpResponse(http.Response res) {
  return messageFromHttpResponseBody(res.statusCode, utf8.decode(res.bodyBytes));
}

String messageFromHttpResponseBody(int statusCode, String bodyText) {
  String? bodyMsg;
  final trimmed = bodyText.trim();
  if (trimmed.isNotEmpty) {
    try {
      bodyMsg = apiMessageFromJson(jsonDecode(trimmed));
    } catch (_) {
      // ignore
    }
  }
  return userFriendlyHttpStatusMessage(statusCode, bodyMessage: bodyMsg);
}

/// Mensajes de error legibles para el usuario final (release).
String userFriendlyErrorMessage(
  Object error, {
  String? serverMessage,
  String? fallback,
}) {
  final server = serverMessage?.trim();
  if (server != null && server.isNotEmpty && _looksUserFacing(server)) {
    return server;
  }

  if (error is String) {
    final direct = error.trim();
    if (direct.isNotEmpty && _looksUserFacing(direct)) {
      return direct;
    }
  }

  final unwrapped = _unwrapExceptionMessage(error);
  if (unwrapped != null && _looksUserFacing(unwrapped)) {
    return unwrapped;
  }

  if (error is TimeoutException) {
    return 'La conexión tardó demasiado. Verificá tu internet e intentá de nuevo.';
  }

  final raw = error.toString();
  final lower = raw.toLowerCase();

  if (lower.contains('connection reset') ||
      lower.contains('connection refused') ||
      lower.contains('connection closed') ||
      lower.contains('broken pipe') ||
      lower.contains('socketexception') ||
      lower.contains('failed host lookup') ||
      lower.contains('network is unreachable') ||
      lower.contains('software caused connection abort')) {
    return 'No pudimos conectar con el servidor. Revisá tu conexión e intentá de nuevo.';
  }

  if (lower.contains('clientexception') ||
      lower.contains('handshake') ||
      lower.contains('certificate') ||
      lower.contains('tls')) {
    return 'Hubo un problema de conexión segura. Intentá de nuevo en unos segundos.';
  }

  if (error is SocketException) {
    return 'No hay conexión a internet o el servidor no responde. Intentá de nuevo.';
  }

  if (lower.startsWith('http 5') || RegExp(r'\b5\d{2}\b').hasMatch(lower)) {
    return 'El servidor no está disponible en este momento. Intentá de nuevo más tarde.';
  }

  if (lower.startsWith('http 4') || RegExp(r'\b4\d{2}\b').hasMatch(lower)) {
    return server ??
        'No se pudo completar la operación. Verificá los datos e intentá de nuevo.';
  }

  if (lower.contains('format exception') ||
      lower.contains('respuesta inválida') ||
      lower.contains('no es ui_definition')) {
    return 'Recibimos una respuesta inesperada del servidor. Intentá de nuevo.';
  }

  return fallback ??
      'Ocurrió un error inesperado. Intentá de nuevo en unos segundos.';
}

bool isRetryableNetworkError(Object error) {
  if (error is TimeoutException || error is SocketException) {
    return true;
  }
  final lower = error.toString().toLowerCase();
  return lower.contains('connection reset') ||
      lower.contains('connection refused') ||
      lower.contains('connection closed') ||
      lower.contains('broken pipe') ||
      lower.contains('failed host lookup') ||
      lower.contains('network is unreachable') ||
      lower.contains('clientexception') ||
      lower.contains('software caused connection abort');
}

bool _looksUserFacing(String message) {
  final m = message.trim();
  if (m.isEmpty) return false;
  if (m.startsWith('HTTP ')) return false;
  if (m.startsWith('Exception:')) return false;
  if (m.contains('SocketException')) return false;
  if (m.contains('ClientException')) return false;
  if (RegExp(r'^[A-Za-z]+Exception\b').hasMatch(m)) return false;
  return true;
}

String? _unwrapExceptionMessage(Object error) {
  final raw = error.toString().trim();
  const prefix = 'Exception:';
  if (raw.startsWith(prefix)) {
    final inner = raw.substring(prefix.length).trim();
    if (inner.isNotEmpty) {
      return inner;
    }
  }
  return null;
}

String userFriendlyHttpStatusMessage(int statusCode, {String? bodyMessage}) {
  final body = bodyMessage?.trim();
  if (body != null && body.isNotEmpty) {
    return body;
  }
  if (statusCode >= 500) {
    return 'El servidor no está disponible en este momento. Intentá de nuevo más tarde.';
  }
  if (statusCode == 401 || statusCode == 403) {
    return 'Tu sesión expiró o no tenés permiso. Volvé a iniciar sesión.';
  }
  if (statusCode == 404) {
    return 'No encontramos la información solicitada.';
  }
  if (statusCode >= 400) {
    return 'No se pudo completar la operación. Verificá los datos e intentá de nuevo.';
  }
  return 'Ocurrió un error al procesar la solicitud.';
}
