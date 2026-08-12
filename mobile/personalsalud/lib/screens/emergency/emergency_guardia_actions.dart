// lib/screens/emergency/emergency_guardia_actions.dart
import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../../services/emergency_guardia_api.dart';
import 'emergency_ingreso_screen.dart';
import 'emergency_triage_screen.dart';

/// Menú y CTAs del tablero de guardia (médico: Atender + egreso contextual).
class EmergencyGuardiaActions {
  EmergencyGuardiaActions._();

  static bool episodioCerrado(EmergencyBoardItem g) {
    final e = g.circuitoEstado ?? '';
    return e == 'finalizado' || e == 'derivado' || e == 'atendido';
  }

  static Future<void> openTriage({
    required BuildContext context,
    required EmergencyBoardItem item,
    required EmergencyGuardiaApi api,
    required VoidCallback onChanged,
    bool isRetriage = false,
  }) =>
      _retriage(
        context,
        item,
        api,
        onChanged,
        isRetriage: isRetriage,
        initialLevel: isRetriage ? item.prioridadTriage : null,
        initialReason: isRetriage ? item.triageReasonText : null,
      );

  static Future<void> openIngreso({
    required BuildContext context,
    required EmergencyGuardiaApi api,
    required VoidCallback onChanged,
  }) async {
    final ok = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => EmergencyIngresoScreen(api: api),
      ),
    );
    if (ok == true) onChanged();
  }

  static Future<void> openCama({
    required BuildContext context,
    required EmergencyBoardItem item,
    required EmergencyGuardiaApi api,
    required VoidCallback onChanged,
  }) async {
    if (!item.internacionPendiente) {
      await _solicitarInternacion(context, item, api, onChanged);
      return;
    }
    if (item.idPersona <= 0) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Falta el paciente para el ingreso.')),
        );
      }
      return;
    }
    final uri = Uri.parse(
      resolveApiAbsoluteUrl(
        '/clinical/internacion/ingreso-formulario'
        '?id_persona=${item.idPersona}&id_guardia=${item.id}',
      ),
    );
    if (!context.mounted) return;
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => UiJsonScreen(
          apiAbsoluteUrl: uri.toString(),
          authToken: api.authToken,
          appClient: 'bioenlace-personalsalud',
          title: 'Ingreso a internación',
          onSubmitSuccess: (_) async {
            onChanged();
          },
        ),
      ),
    );
    onChanged();
  }

  /// Menú ⋮ del médico: solo «Paciente se retiró» (cierra circuito).
  /// La atención clínica es la captura del encounter; no hay egreso clínico.
  static Future<void> showActionSheet({
    required BuildContext context,
    required EmergencyBoardItem item,
    required EmergencyGuardiaApi api,
    required VoidCallback onChanged,
    bool sessionTieneCobertura = true,
    bool puedeTriage = false,
  }) async {
    if (episodioCerrado(item)) return;

    final actions = <_ActionDef>[];
    final enAtencion = (item.circuitoEstado ?? '') == 'en_atencion';
    if (item.needsTriage && puedeTriage) {
      actions.add(_ActionDef(
        label: item.prioridadTriage != null ? 'Actualizar triage' : 'Registrar triage',
        icon: Icons.assignment_outlined,
        onTap: () => openTriage(
          context: context,
          item: item,
          api: api,
          onChanged: onChanged,
          isRetriage: item.prioridadTriage != null,
        ),
      ));
    }
    if (enAtencion && !item.needsTriage) {
      actions.add(_ActionDef(
        label: 'Paciente se retiró',
        icon: Icons.logout,
        onTap: () => _finalizar(context, item, api, onChanged),
      ));
    }

    if (actions.isEmpty) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            item.needsTriage
                ? 'Pendiente de triage. Lo registra admisión o enfermería.'
                : 'Atendé al paciente para egresar. La derivación se carga en la consulta.',
          ),
        ),
      );
      return;
    }

    await showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (ctx) {
        return SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(
                  BioSpacing.md,
                  BioSpacing.sm,
                  BioSpacing.md,
                  BioSpacing.xs,
                ),
                child: Text(
                  item.nombreCompleto,
                  style: BioTypography.title,
                ),
              ),
              ...actions.map((a) {
                return ListTile(
                  leading: Icon(a.icon),
                  title: Text(a.label),
                  onTap: () {
                    Navigator.pop(ctx);
                    a.onTap();
                  },
                );
              }),
            ],
          ),
        );
      },
    );
  }

  static Future<void> _retriage(
    BuildContext context,
    EmergencyBoardItem item,
    EmergencyGuardiaApi api,
    VoidCallback onChanged, {
    bool isRetriage = true,
    int? initialLevel,
    String? initialReason,
  }) async {
    final ok = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (context) => EmergencyTriageScreen(
          guardiaId: item.id,
          pacienteNombre: item.nombreCompleto,
          api: api,
          isRetriage: isRetriage,
          initialLevel: initialLevel ?? (isRetriage ? item.prioridadTriage : null),
          initialReason: initialReason ?? (isRetriage ? item.triageReasonText : null),
        ),
      ),
    );
    if (ok == true) onChanged();
  }

  static Future<void> _solicitarInternacion(
    BuildContext context,
    EmergencyBoardItem item,
    EmergencyGuardiaApi api,
    VoidCallback onChanged,
  ) async {
    try {
      await api.solicitarInternacion(item.id);
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Solicitud de cama registrada')),
        );
      }
      onChanged();
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e')),
        );
      }
    }
  }

  static Future<void> _tomarCaso(
    BuildContext context,
    EmergencyBoardItem item,
    EmergencyGuardiaApi api,
    VoidCallback onChanged, {
    bool sessionTieneCobertura = true,
  }) async {
    if (!sessionTieneCobertura) {
      final cont = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('Sin horario de plantel'),
          content: const Text(
            'No tenés horario de plantel de guardia cargado. '
            'Para atender, configurá tus horarios en el Asistente («Configurar mis horarios») '
            'o pedile a coordinación / administración del centro que te los asigne. '
            'Podés intentar igual, pero el servidor puede rechazar la asignación.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Cancelar'),
            ),
            TextButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Intentar igual'),
            ),
          ],
        ),
      );
      if (cont != true) return;
    }
    try {
      await api.asignar(guardiaId: item.id);
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Caso asignado')),
        );
      }
      onChanged();
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(userFriendlyErrorMessage(e))),
        );
      }
    }
  }

  static Future<void> _derivar(
    BuildContext context,
    EmergencyBoardItem item,
    EmergencyGuardiaApi api,
    VoidCallback onChanged,
  ) async {
    List<EfectorDerivacionItem> efectores;
    try {
      efectores = await api.listarEfectoresDerivacion();
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error al cargar efectores: $e')),
        );
      }
      return;
    }
    if (!context.mounted) return;

    int? selectedId = efectores.isNotEmpty ? efectores.first.idEfector : null;
    final condicionesCtrl = TextEditingController();
    var solicitarCama = false;

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setState) {
            return AlertDialog(
          title: const Text('Derivar paciente'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                DropdownButtonFormField<int>(
                  initialValue: selectedId,
                  decoration: const InputDecoration(labelText: 'Efector destino'),
                  items: efectores
                      .map(
                        (ef) => DropdownMenuItem(
                          value: ef.idEfector,
                          child: Text(ef.nombre),
                        ),
                      )
                      .toList(),
                  onChanged: (v) => selectedId = v,
                ),
                BioSpacing.gapH(BioSpacing.md),
                TextField(
                  controller: condicionesCtrl,
                  decoration: const InputDecoration(
                    labelText: 'Condiciones / motivo',
                  ),
                  maxLines: 2,
                ),
                CheckboxListTile(
                  value: solicitarCama,
                  onChanged: (v) => setState(() => solicitarCama = v ?? false),
                  title: const Text('Solicitar internación (cama)'),
                  contentPadding: EdgeInsets.zero,
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Cancelar'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Derivar'),
            ),
          ],
            );
          },
        );
      },
    );

    if (confirmed != true || selectedId == null) return;

    try {
      await api.derivar(
        guardiaId: item.id,
        idEfectorDerivacion: selectedId!,
        condicionesDerivacion: condicionesCtrl.text.trim(),
        solicitarInternacion: solicitarCama,
      );
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Derivación registrada')),
        );
      }
      onChanged();
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('No se pudo derivar: $e')),
        );
      }
    }
  }

  static Future<void> _finalizar(
    BuildContext context,
    EmergencyBoardItem item,
    EmergencyGuardiaApi api,
    VoidCallback onChanged,
  ) async {
    final uri = Uri.parse(
      resolveApiAbsoluteUrl(
        '/clinical/emergency-guardia/${item.id}/egreso-formulario',
      ),
    );
    if (!context.mounted) return;
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => UiJsonScreen(
          apiAbsoluteUrl: uri.toString(),
          authToken: api.authToken,
          appClient: 'bioenlace-personalsalud',
          title: 'Paciente se retiró',
          onSubmitSuccess: (_) async {
            onChanged();
          },
        ),
      ),
    );
    onChanged();
  }
}

class _ActionDef {
  final String label;
  final IconData icon;
  final VoidCallback onTap;

  _ActionDef({
    required this.label,
    required this.icon,
    required this.onTap,
  });
}
