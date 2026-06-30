// lib/services/consulta_guardar_service.dart
import 'package:shared/shared.dart';

/// Guardado de captura clínica vía `POST /api/v1/clinical/encounter/guardar`.
@Deprecated('Usar EncounterCaptureApi.guardar desde shared')
class ConsultaGuardarService {
  String? authToken;
  final EncounterCaptureApi _api = EncounterCaptureApi();

  ConsultaGuardarService({this.authToken});

  /// [parent] ej. TURNO, CIRUGIA, GUARDIA (mismas claves que web).
  Future<Map<String, dynamic>> guardar({
    required int idPersona,
    required String parent,
    required int parentId,
    required String texto,
    int? idConfiguracion,
    Map<String, dynamic>? datosExtraidos,
  }) async {
    _api.authToken = authToken;
    return _api.guardar(
      idPersona: idPersona,
      parent: parent,
      parentId: parentId,
      idConfiguracion: idConfiguracion,
      datosExtraidos: datosExtraidos ?? {},
      textoOriginal: texto,
      textoProcesado: texto,
    );
  }
}
