import 'package:flutter/material.dart';

import '../theme/tokens/tokens.dart';
import 'assistant_patient_preferences_api.dart';

/// Switch: enviar extracto acotado de HC al asistente conversacional.
class AssistantHcContextSwitch extends StatefulWidget {
  final String? authToken;

  const AssistantHcContextSwitch({super.key, this.authToken});

  @override
  State<AssistantHcContextSwitch> createState() => _AssistantHcContextSwitchState();
}

class _AssistantHcContextSwitchState extends State<AssistantHcContextSwitch> {
  bool _loading = true;
  bool _enabled = true;
  String? _status;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final res = await AssistantPatientPreferencesApi(authToken: widget.authToken).fetch();
    if (!mounted) return;
    final data = res['data'];
    final on = data is Map && data['usa_resumen_hc_en_asistente'] == false
        ? false
        : true;
    setState(() {
      _enabled = on;
      _loading = false;
      if (res['success'] != true) {
        _status = 'No se pudo cargar la preferencia. Queda el valor por defecto.';
      }
    });
  }

  Future<void> _onChanged(bool value) async {
    setState(() {
      _loading = true;
      _status = null;
    });
    final res = await AssistantPatientPreferencesApi(authToken: widget.authToken).save(
      usaResumenHcEnAsistente: value,
    );
    if (!mounted) return;
    if (res['success'] == true) {
      setState(() {
        _enabled = value;
        _loading = false;
      });
      return;
    }
    setState(() {
      _loading = false;
      _status = (res['message'] as String?)?.trim().isNotEmpty == true
          ? res['message'] as String
          : 'No se pudo guardar. Probá de nuevo.';
    });
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SwitchListTile(
          contentPadding: const EdgeInsets.symmetric(horizontal: BioSpacing.md),
          secondary: _loading
              ? const SizedBox(
                  width: 22,
                  height: 22,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Icon(Icons.health_and_safety_outlined),
          title: const Text('Resumen de historia en el asistente'),
          subtitle: const Text(
            'Si está activo, el chat puede usar un extracto de tus alergias, condiciones y medicación. No cambia lo que ve el médico en la consulta.',
          ),
          value: _enabled,
          onChanged: _loading ? null : _onChanged,
        ),
        if (_status != null && _status!.isNotEmpty)
          Padding(
            padding: const EdgeInsets.fromLTRB(BioSpacing.md, 0, BioSpacing.md, BioSpacing.sm),
            child: Text(
              _status!,
              style: BioTypography.bodySm.copyWith(color: context.bio.textMuted),
            ),
          ),
      ],
    );
  }
}
