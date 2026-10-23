import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

import '../http/api_contract.dart';

/// Mensaje de error desde cuerpo JSON típico de la API Bioenlace.
String? apiMessageFromJson(dynamic json) {
  if (json is! Map) {
    return null;
  }
  final error = json['error'];
  if (error is Map) {
    final contractMessage = error['message']?.toString().trim();
    if (contractMessage != null && contractMessage.isNotEmpty) {
      return contractMessage;
    }
  }
  final message = json['message']?.toString().trim();
  if (message != null && message.isNotEmpty) {
    return message;
  }
  final errorText = error is Map ? null : error?.toString().trim();
  if (errorText != null && errorText.isNotEmpty) {
    return errorText;
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
  // Un error del contrato trae el texto del backend: se muestra tal cual.
  if (error is ApiContractException) {
    return error.message;
  }

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

  // Los fallos de transporte se clasifican por tipo, no por el texto de la excepción.
  if (error is TimeoutException) {
    return 'La conexión tardó demasiado. Verificá tu internet e intentá de nuevo.';
  }
  if (error is TlsException) {
    return 'Hubo un problema de conexión segura. Intentá de nuevo en unos segundos.';
  }
  if (error is SocketException) {
    return 'No hay conexión a internet o el servidor no responde. Intentá de nuevo.';
  }
  if (error is http.ClientException) {
    return 'No pudimos conectar con el servidor. Revisá tu conexión e intentá de nuevo.';
  }
  if (error is FormatException) {
    return 'Recibimos una respuesta inesperada del servidor. Intentá de nuevo.';
  }

  final unwrapped = _unwrapExceptionMessage(error);
  if (unwrapped != null && _looksUserFacing(unwrapped)) {
    return unwrapped;
  }

  return fallback ??
      'Ocurrió un error inesperado. Intentá de nuevo en unos segundos.';
}

bool isRetryableNetworkError(Object error) {
  if (error is ApiContractException) {
    return error.retryable;
  }
  return error is TimeoutException ||
      error is SocketException ||
      error is http.ClientException;
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
  String inner = raw;
  if (raw.startsWith(prefix)) {
    inner = raw.substring(prefix.length).trim();
  }
  if (inner.isEmpty) {
    return null;
  }
  final jsonStart = inner.indexOf('{');
  if (jsonStart >= 0) {
    try {
      final decoded = jsonDecode(inner.substring(jsonStart));
      final apiMsg = apiMessageFromJson(decoded);
      if (apiMsg != null && apiMsg.isNotEmpty) {
        return apiMsg;
      }
    } catch (_) {
      // ignore
    }
  }
  return inner;
}

String userFriendlyHttpStatusMessage(int statusCode, {String? bodyMessage}) {
  final body = bodyMessage?.trim();
  if (body != null && body.isNotEmpty) {
    return body;
  }
  if (statusCode >= 500) {
    return 'El servidor no está disponible en este momento. Intentá de nuevo más tarde.';
  }
  if (statusCode == 401) {
    return 'Tu sesión expiró. Volvé a iniciar sesión.';
  }
  if (statusCode == 403) {
    return 'No tenés permiso para esta acción.';
  }
  if (statusCode == 404) {
    return 'No encontramos la información solicitada.';
  }
  if (statusCode >= 400) {
    return 'No se pudo completar la operación. Verificá los datos e intentá de nuevo.';
  }
  return 'Ocurrió un error al procesar la solicitud.';
}
