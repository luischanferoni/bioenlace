/// Contrato de API: éxito `{data, meta}`, error `{error:{code,message,...}, meta?}`.
/// El texto de los errores lo define el backend; el cliente solo lo muestra.
class ApiContractException implements Exception {
  ApiContractException({
    required this.message,
    this.code,
    this.intent,
    this.retryable = false,
    this.fieldErrors = const {},
    this.meta = const {},
    this.statusCode,
  });

  final String message;
  final String? code;
  final String? intent;
  final bool retryable;
  final Map<String, dynamic> fieldErrors;
  final Map<String, dynamic> meta;
  final int? statusCode;

  Map<String, dynamic> toJson() => {
    if (code != null) 'code': code,
    'message': message,
    if (intent != null) 'intent': intent,
    'retryable': retryable,
    if (fieldErrors.isNotEmpty) 'field_errors': fieldErrors,
  };

  @override
  String toString() => message;
}

Map<String, dynamic> _asMap(dynamic value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

/// Devuelve `data` (con `meta.message` expuesto como `message`) o lanza [ApiContractException].
Map<String, dynamic> unwrapApiContract(dynamic decoded, int statusCode) {
  final body = _asMap(decoded);
  _throwIfError(body, statusCode);
  final data = _asMap(body['data']);
  final meta = _asMap(body['meta']);
  final message = meta['message']?.toString();
  if (message != null && message.isNotEmpty) {
    data['message'] = message;
  }
  return data;
}

/// Igual que [unwrapApiContract] pero para respuestas cuyo `data` es una lista.
List<Map<String, dynamic>> unwrapApiContractList(dynamic decoded, int statusCode) {
  final body = _asMap(decoded);
  _throwIfError(body, statusCode);
  final raw = body['data'];
  return raw is List ? raw.map(_asMap).toList() : <Map<String, dynamic>>[];
}

void _throwIfError(Map<String, dynamic> body, int statusCode) {
  final error = body['error'];
  if (statusCode >= 400 || error is Map) {
    final err = _asMap(error);
    final message = err['message']?.toString().trim() ?? '';
    throw ApiContractException(
      message: message.isNotEmpty
          ? message
          : 'Error de comunicación ($statusCode).',
      code: err['code']?.toString(),
      intent: err['intent']?.toString(),
      retryable: err['retryable'] == true,
      fieldErrors: _asMap(err['field_errors']),
      meta: _asMap(body['meta']),
      statusCode: statusCode,
    );
  }
}
