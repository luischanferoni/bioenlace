import '../auth/auth_token_store.dart';
import '../http/api_client.dart';
import '../http/api_contract.dart';

/// Cliente HTTP para `/api/v1/paciente-contexto/*`.
/// Devuelve `{success, data}` o `{success: false, message}` para no cambiar a los llamadores.
class PacienteContextApi {
  final String? authToken;
  final String appClient;

  PacienteContextApi({this.authToken, this.appClient = 'paciente-flutter'});

  Future<ApiClient> _api() async {
    var token = authToken;
    if (token == null || token.isEmpty) {
      token = await AuthTokenStore.instance.read();
    }
    return ApiClient(authToken: token, appClient: appClient);
  }

  Future<Map<String, dynamic>> _request(
    String path, {
    String method = 'GET',
    Map<String, dynamic>? body,
  }) async {
    try {
      final api = await _api();
      final data = method == 'POST'
          ? await api.postData(path, body: body)
          : await api.getData(path);
      return {'success': true, 'data': data};
    } on ApiContractException catch (e) {
      return {'success': false, 'message': e.message};
    } catch (e) {
      return {'success': false, 'message': e.toString()};
    }
  }
  Future<Map<String, dynamic>> fetchContexto() {
    return _request('/paciente-contexto/obtener-como-paciente');
  }

  Future<Map<String, dynamic>> actualizarContexto({
    String? sectorSalud,
    int? idProvinciaContexto,
  }) {
    final body = <String, dynamic>{};
    if (sectorSalud != null) body['sector_salud'] = sectorSalud;
    if (idProvinciaContexto != null) {
      body['id_provincia_contexto'] = idProvinciaContexto;
    }
    return _request(
      '/paciente-contexto/actualizar-como-paciente',
      method: 'POST',
      body: body,
    );
  }

  Future<Map<String, dynamic>> sugerirProvincias() {
    return _request('/paciente-contexto/sugerir-provincias-como-paciente');
  }

  Future<Map<String, dynamic>> buscarRecursoProvincial({
    String? query,
    String? tipo,
  }) {
    final body = <String, dynamic>{};
    if (query != null && query.isNotEmpty) body['q'] = query;
    if (tipo != null && tipo.isNotEmpty) body['tipo'] = tipo;
    return _request(
      '/paciente-contexto/buscar-recurso-provincial-como-paciente',
      method: 'POST',
      body: body,
    );
  }
}
