import 'package:flutter/material.dart';

import '../theme/tokens/tokens.dart';
import 'care_plan_local_reminder_service.dart';
import 'care_plan_reminder_pref_sync.dart';
import 'care_plan_reminder_preferences.dart';

/// Switch global de recordatorios de tratamiento (Configuración paciente).
class CarePlanReminderGlobalSwitch extends StatefulWidget {
  final String? authToken;

  const CarePlanReminderGlobalSwitch({super.key, this.authToken});

  @override
  State<CarePlanReminderGlobalSwitch> createState() => _CarePlanReminderGlobalSwitchState();
}

class _CarePlanReminderGlobalSwitchState extends State<CarePlanReminderGlobalSwitch> {
  bool _loading = true;
  bool _enabled = false;
  String? _status;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    await CarePlanReminderPrefSync.pullFromServer(authToken: widget.authToken);
    final on = await CarePlanReminderPreferences.isGlobalEnabled();
    if (!mounted) return;
    setState(() {
      _enabled = on;
      _loading = false;
    });
  }

  Future<void> _onChanged(bool value) async {
    setState(() {
      _loading = true;
      _status = null;
    });

    if (value) {
      final ok = await CarePlanLocalReminderService.instance.requestPermissionIfNeeded();
      if (!ok) {
        if (!mounted) return;
        setState(() {
          _enabled = false;
          _loading = false;
          _status = 'Activá las notificaciones en el sistema para usar recordatorios.';
        });
        return;
      }
    }

    await CarePlanReminderPreferences.setGlobalEnabled(value);
    await CarePlanReminderPrefSync.pushPartial(
      {'globalEnabled': value},
      authToken: widget.authToken,
    );
    if (!value) {
      await CarePlanLocalReminderService.instance.cancelAll();
      await CarePlanReminderPreferences.setLastSyncHash('');
    } else {
      final err = await CarePlanLocalReminderService.instance.syncFromApi(
        authToken: widget.authToken,
      );
      if (err != null && err.isNotEmpty) {
        _status = err;
      }
    }

    if (!mounted) return;
    setState(() {
      _enabled = value;
      _loading = false;
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
              : const Icon(Icons.medication_outlined),
          title: const Text('Recordatorios de tratamiento'),
          subtitle: const Text(
            'Alarmas locales según tus planes activos (medicación con horario cargado).',
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
