/// Helpers para el paso `ui_step: impacto` del flujo de licencia (flow_submit).
class FlowLicenciaImpact {
  FlowLicenciaImpact._();

  static const String stepImpacto = 'impacto';

  static bool isImpactUiDefinition(Map<String, dynamic> envelope) {
    if (envelope['kind']?.toString() != 'ui_definition') {
      return false;
    }
    if (envelope['success'] == false) {
      return false;
    }
    final data = envelope['data'];
    if (data is! Map) {
      return false;
    }
    return data['ui_step']?.toString() == stepImpacto;
  }

  static Map<String, dynamic> augmentFlowSubmitBody(
    Map<String, dynamic> body, {
    required bool licenciaImpactStep,
  }) {
    if (!licenciaImpactStep) {
      return body;
    }
    final out = Map<String, dynamic>.from(body);
    out['ui_step'] = stepImpacto;
    out['confirmar_impacto_turnos'] = '1';
    return out;
  }
}
