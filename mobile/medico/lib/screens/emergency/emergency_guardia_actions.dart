// lib/screens/emergency/emergency_guardia_actions.dart
import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../../services/emergency_guardia_api.dart';
import 'emergency_triage_screen.dart';

/// Menú de acciones del tablero de guardia (derivar, egreso, tomar caso, re-triage).
class EmergencyGuardiaActions {
  EmergencyGuardiaActions._();

  static bool episodioCerrado(EmergencyBoardItem g) {
    final e = g.circuitoEstado ?? '';
    return e == 'finalizado' || e == 'derivado';
  }

  static Future<void> showActionSheet({
    required BuildContext context,
    required EmergencyBoardItem item,
    required EmergencyGuardiaApi api,
    required VoidCallback onChanged,
  }) async {
    if (episodioCerrado(item)) return;

    final actions = <_ActionDef>[];
    if (!item.needsTriage) {
      actions.add(_ActionDef(
        label: 'Actualizar triage',
        icon: Icons.medical_information_outlined,
        onTap: () => _retriage(context, item, api, onChanged),
      ));
      actions.add(_ActionDef(
        label: 'Tomar caso',
        icon: Icons.person_add_alt_1_outlined,
        onTap: () => _tomarCaso(context, item, api, onChanged),
      ));
      actions.add(_ActionDef(
        label: 'Derivar',
        icon: Icons.transfer_within_a_station,
        onTap: () => _derivar(context, item, api, onChanged),
      ));
      actions.add(_ActionDef(
        label: 'Egreso',
        icon: Icons.logout,
        onTap: () => _finalizar(context, item, api, onChanged),
      ));
    }

    if (actions.isEmpty) return;

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
    VoidCallback onChanged,
  ) async {
    final ok = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (context) => EmergencyTriageScreen(
          guardiaId: item.id,
          pacienteNombre: item.nombreCompleto,
          api: api,
          isRetriage: true,
          initialLevel: item.prioridadTriage,
          initialReason: item.triageReasonText,
        ),
      ),
    );
    if (ok == true) onChanged();
  }

  static Future<void> _tomarCaso(
    BuildContext context,
    EmergencyBoardItem item,
    EmergencyGuardiaApi api,
    VoidCallback onChanged,
  ) async {
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
          SnackBar(content: Text('No se pudo asignar: $e')),
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

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) {
        return AlertDialog(
          title: const Text('Derivar paciente'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                DropdownButtonFormField<int>(
                  value: selectedId,
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

    if (confirmed != true || selectedId == null) return;

    try {
      await api.derivar(
        guardiaId: item.id,
        idEfectorDerivacion: selectedId!,
        condicionesDerivacion: condicionesCtrl.text.trim(),
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
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Egreso de guardia'),
        content: Text(
          '¿Confirma el egreso de ${item.nombreCompleto}?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancelar'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Confirmar'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      await api.finalizar(item.id);
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Egreso registrado')),
        );
      }
      onChanged();
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('No se pudo egresar: $e')),
        );
      }
    }
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
