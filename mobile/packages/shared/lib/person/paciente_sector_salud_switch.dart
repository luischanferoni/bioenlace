import 'package:flutter/material.dart';

import '../theme/tokens/tokens.dart';
import 'paciente_context_scope.dart';

/// Switch de sector público / privado (Configuración paciente).
class PacienteSectorSaludSwitch extends StatefulWidget {
  final String? authToken;

  const PacienteSectorSaludSwitch({super.key, this.authToken});

  @override
  State<PacienteSectorSaludSwitch> createState() => _PacienteSectorSaludSwitchState();
}

class _PacienteSectorSaludSwitchState extends State<PacienteSectorSaludSwitch> {
  bool _saving = false;

  Future<void> _onChanged(bool privado) async {
    if (_saving) return;
    setState(() => _saving = true);
    final sector = privado ? 'privado' : 'publico';
    final ok = await PacienteContextScope.instance.actualizarSector(
      sector,
      authToken: widget.authToken,
    );
    if (!mounted) return;
    setState(() => _saving = false);
    if (!ok) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No se pudo actualizar el sector')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: PacienteContextScope.instance,
      builder: (context, _) {
        final esPrivado =
            PacienteContextScope.instance.state.sectorSalud == 'privado';
        return SwitchListTile(
          contentPadding: const EdgeInsets.symmetric(horizontal: BioSpacing.md),
          secondary: _saving
              ? const SizedBox(
                  width: 22,
                  height: 22,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Icon(Icons.account_balance_outlined),
          title: const Text('Sector privado'),
          subtitle: Text(
            esPrivado
                ? 'Ves centros y profesionales del sistema privado de tu provincia.'
                : 'Ves centros y profesionales del sistema público de tu provincia.',
          ),
          value: esPrivado,
          onChanged: _saving ? null : _onChanged,
        );
      },
    );
  }
}
