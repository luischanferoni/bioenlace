import '../auth/auth_token_store.dart';
import '../http/api_client.dart';
import '../http/api_contract.dart';

/// Cliente HTTP para `/api/v1/person-representation/*`.
/// Devuelve `{success, data}` o `{success: false, message}` para no cambiar a los llamadores.
class PersonRepresentationApi {
  final String? authToken;
  final String appClient;

  PersonRepresentationApi({
    this.authToken,
    this.appClient = 'paciente-flutter',
  });

  Future<ApiClient> _api() async {
    var token = authToken;
    if (token == null || token.isEmpty) {
      token = await AuthTokenStore.instance.read();
    }
    return ApiClient(authToken: token, appClient: appClient);
  }

  Future<Map<String, dynamic>> _run(
    Future<Map<String, dynamic>> Function(ApiClient api) call,
  ) async {
    try {
      final data = await call(await _api());
      return {'success': true, 'data': data};
    } on ApiContractException catch (e) {
      return {'success': false, 'message': e.message};
    } catch (e) {
      return {'success': false, 'message': e.toString()};
    }
  }

  Future<Map<String, dynamic>> _post(String path, {Map<String, dynamic>? body}) =>
      _run((api) => api.postData(path, body: body));

  Future<Map<String, dynamic>> _get(String path) =>
      _run((api) => api.getData(path));
  Future<Map<String, dynamic>> fetchPacientesACargo() {
    return _post('/person-representation/pacientes-a-cargo');
  }

  Future<Map<String, dynamic>> fetchVinculosComoTutor({String? status}) {
    return _post(
      '/person-representation/mis-vinculos-como-tutor',
      body: status != null ? {'status': status} : null,
    );
  }

  Future<Map<String, dynamic>> fetchMisRepresentantes() {
    return _post('/person-representation/mis-representantes');
  }

  Future<Map<String, dynamic>> solicitarMenorComoTutor(
    Map<String, dynamic> body,
  ) {
    return _post(
      '/person-representation/solicitar-menor-como-tutor',
      body: body,
    );
  }

  Future<Map<String, dynamic>> designarRepresentante(
    Map<String, dynamic> body,
  ) {
    return _post('/person-representation/designar-representante', body: body);
  }

  Future<Map<String, dynamic>> revocarRepresentante(Map<String, dynamic> body) {
    return _post('/person-representation/revocar-representante', body: body);
  }

  Future<Map<String, dynamic>> establecerSujetoPaciente(int? subjectPersonaId) {
    return _post(
      '/person-representation/establecer-sujeto-paciente',
      body:
          subjectPersonaId != null && subjectPersonaId > 0
              ? {'subject_persona_id': subjectPersonaId}
              : {},
    );
  }

  Future<Map<String, dynamic>> fetchPreferencias() {
    return _get('/person-representation/preferencias-como-paciente');
  }

  Future<Map<String, dynamic>> guardarPreferencias({required bool notify}) {
    return _post(
      '/person-representation/preferencias-como-paciente',
      body: {'notify_on_representative_action': notify},
    );
  }
}
