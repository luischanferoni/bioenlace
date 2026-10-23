import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config/api_config.dart';
import 'api_contract.dart';

/// Cliente HTTP único de los clientes móviles: arma headers, aplica timeout,
/// decodifica el JSON y traduce cualquier error al contrato ([ApiContractException]).
class ApiClient {
  ApiClient({this.authToken, this.appClient = 'flutter', http.Client? client})
      : _client = client;

  final String? authToken;
  final String appClient;
  final http.Client? _client;

  Map<String, String> get _headers =>
      AppConfig.jsonHeaders(bearerToken: authToken, appClient: appClient);

  Future<Map<String, dynamic>> getData(
    String path, {
    Map<String, String>? query,
  }) async {
    final response = await _send('GET', path, query: query);
    return unwrapApiContract(_decode(response), response.statusCode);
  }

  Future<List<Map<String, dynamic>>> getList(
    String path, {
    Map<String, String>? query,
  }) async {
    final response = await _send('GET', path, query: query);
    return unwrapApiContractList(_decode(response), response.statusCode);
  }

  Future<Map<String, dynamic>> postData(
    String path, {
    Map<String, dynamic>? body,
  }) async {
    final response = await _send('POST', path, body: body);
    return unwrapApiContract(_decode(response), response.statusCode);
  }

  Future<Map<String, dynamic>> putData(
    String path, {
    Map<String, dynamic>? body,
  }) async {
    final response = await _send('PUT', path, body: body);
    return unwrapApiContract(_decode(response), response.statusCode);
  }

  Uri _uri(String path, Map<String, String>? query) {
    final uri = Uri.parse('${AppConfig.apiUrl}$path');
    return query == null || query.isEmpty ? uri : uri.replace(queryParameters: query);
  }

  Future<http.Response> _send(
    String method,
    String path, {
    Map<String, String>? query,
    Map<String, dynamic>? body,
  }) {
    final uri = _uri(path, query);
    final timeout = Duration(seconds: AppConfig.httpTimeoutSeconds);
    final c = _client;
    final Future<http.Response> request;
    if (method == 'GET') {
      request = c != null
          ? c.get(uri, headers: _headers)
          : http.get(uri, headers: _headers);
    } else {
      final encoded = json.encode(body ?? const <String, dynamic>{});
      final isPut = method == 'PUT';
      request = c != null
          ? (isPut
              ? c.put(uri, headers: _headers, body: encoded)
              : c.post(uri, headers: _headers, body: encoded))
          : (isPut
              ? http.put(uri, headers: _headers, body: encoded)
              : http.post(uri, headers: _headers, body: encoded));
    }
    return request.timeout(timeout);
  }

  dynamic _decode(http.Response response) {
    final text = utf8.decode(response.bodyBytes).trim();
    if (text.isEmpty) {
      return const <String, dynamic>{};
    }
    try {
      return json.decode(text);
    } on FormatException {
      // Una respuesta de error no JSON (proxy, 5xx) igual debe salir como fallo del contrato.
      if (response.statusCode >= 400) {
        return const <String, dynamic>{};
      }
      rethrow;
    }
  }
}
