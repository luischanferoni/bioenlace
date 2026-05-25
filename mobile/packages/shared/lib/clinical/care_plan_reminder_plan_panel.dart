import 'package:flutter/material.dart';

import '../theme/tokens/tokens.dart';
import '../ui/ui.dart';
import 'care_plan_local_reminder_service.dart';
import 'care_plan_reminder_api.dart';
import 'care_plan_reminder_pref_sync.dart';
import 'care_plan_reminder_preferences.dart';

/// Panel de recordatorios por plan (detalle de tratamiento).
class CarePlanReminderPlanPanel extends StatefulWidget {
  final int carePlanId;
  final String? authToken;

  const CarePlanReminderPlanPanel({
    super.key,
    required this.carePlanId,
    this.authToken,
  });

  @override
  State<CarePlanReminderPlanPanel> createState() => _CarePlanReminderPlanPanelState();
}

class _CarePlanReminderPlanPanelState extends State<CarePlanReminderPlanPanel> {
  bool _loading = true;
  bool _globalOn = false;
  bool _planOn = true;
  String? _error;
  List<Map<String, dynamic>> _items = [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    await CarePlanReminderPrefSync.pullFromServer(authToken: widget.authToken);
    _globalOn = await CarePlanReminderPreferences.isGlobalEnabled();
    _planOn = await CarePlanReminderPreferences.isPlanEnabled(
      widget.carePlanId,
      globalDefault: _globalOn,
    );

    final api = CarePlanReminderApi(authToken: widget.authToken);
    final r = await api.fetchSchedule(carePlanId: widget.carePlanId);
    if (!mounted) return;

    if (r['success'] == true && r['data'] is Map) {
      final data = Map<String, dynamic>.from(r['data'] as Map);
      final raw = data['items'];
      _items = raw is List
          ? raw.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList()
          : [];
    } else {
      _error = r['message']?.toString();
    }

    setState(() => _loading = false);
  }

  Future<void> _setPlan(bool value) async {
    await CarePlanReminderPreferences.setPlanEnabled(widget.carePlanId, value);
    await CarePlanReminderPrefSync.pushPartial(
      {
        'plans': {widget.carePlanId.toString(): value},
      },
      authToken: widget.authToken,
    );
    setState(() => _planOn = value);
    if (_globalOn) {
      await CarePlanReminderPreferences.setLastSyncHash('');
      await CarePlanLocalReminderService.instance.syncFromApi(authToken: widget.authToken);
    }
  }

  Future<void> _setItem(int activityId, bool value) async {
    await CarePlanReminderPreferences.setItemEnabled(activityId, value);
    await CarePlanReminderPrefSync.pushPartial(
      {
        'items': {
          activityId.toString(): {
            'enabled': value,
            'carePlanId': widget.carePlanId,
          },
        },
      },
      authToken: widget.authToken,
    );
    if (_globalOn) {
      await CarePlanReminderPreferences.setLastSyncHash('');
      await CarePlanLocalReminderService.instance.syncFromApi(authToken: widget.authToken);
    }
    setState(() {});
  }

  Future<void> _configureCustomTimes(Map<String, dynamic> item) async {
    final activityId = int.tryParse(item['activityId']?.toString() ?? '') ?? 0;
    if (activityId <= 0) return;

    final existing = await CarePlanReminderPreferences.getCustomTimes(activityId);
    final controller = TextEditingController(
      text: existing.isNotEmpty ? existing.join(', ') : '08:00, 20:00',
    );

    if (!mounted) return;
    final saved = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Horarios de recordatorio'),
        content: TextField(
          controller: controller,
          decoration: const InputDecoration(
            labelText: 'Horas (HH:mm, separadas por coma)',
            hintText: '08:00, 20:00',
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancelar')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Guardar')),
        ],
      ),
    );

    if (saved != true) return;

    final times = controller.text
        .split(',')
        .map((s) => s.trim())
        .where((s) => s.contains(':'))
        .toList();

    await CarePlanReminderPreferences.setCustomTimes(activityId, times);
    await CarePlanReminderPreferences.setItemEnabled(activityId, true);
    await CarePlanReminderPrefSync.pushPartial(
      {
        'items': {
          activityId.toString(): {
            'enabled': true,
            'carePlanId': widget.carePlanId,
            'customTimes': times,
          },
        },
      },
      authToken: widget.authToken,
    );
    if (_globalOn) {
      await CarePlanReminderPreferences.setLastSyncHash('');
      await CarePlanLocalReminderService.instance.syncFromApi(authToken: widget.authToken);
    }
    await _load();
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: BioSpacing.md),
        child: Center(child: CircularProgressIndicator()),
      );
    }

    if (!CarePlanLocalReminderService.isSupported) {
      return BioAlert.info(
        message:
            'Las alarmas de recordatorio están disponibles en la app para Android o iPhone. '
            'En el navegador podés ver el plan; las preferencias se sincronizan al usar el celular.',
      );
    }

    if (!_globalOn) {
      return BioAlert.info(
        message: 'Activá "Recordatorios de tratamiento" en Configuración para usar alarmas de este plan.',
      );
    }

    return BioCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Recordatorios', style: BioTypography.title),
          BioSpacing.gapH(BioSpacing.sm),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('Recordatorios de este tratamiento'),
            value: _planOn,
            onChanged: _setPlan,
          ),
          if (_error != null) ...[
            BioSpacing.gapH(BioSpacing.sm),
            BioAlert.danger(message: _error!),
          ],
          if (_items.isEmpty && _error == null)
            Text(
              'No hay actividades con horarios para recordar.',
              style: BioTypography.bodySm.copyWith(color: context.bio.textMuted),
            ),
          ..._items.map((item) => _itemTile(item)),
        ],
      ),
    );
  }

  Widget _itemTile(Map<String, dynamic> item) {
    final activityId = int.tryParse(item['activityId']?.toString() ?? '') ?? 0;
    final title = item['title']?.toString() ?? 'Medicación';
    final requiresSetup = item['requiresPatientSetup'] == true;

    return FutureBuilder<bool>(
      future: CarePlanReminderPreferences.isItemEnabled(activityId, planDefault: _planOn),
      builder: (context, snap) {
        final itemOn = snap.data ?? _planOn;
        return Column(
          children: [
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(title, style: BioTypography.body),
              subtitle: requiresSetup
                  ? const Text('Configurá horarios manualmente')
                  : Text(item['subtitle']?.toString() ?? ''),
              value: itemOn && _planOn,
              onChanged: !_planOn
                  ? null
                  : (v) => _setItem(activityId, v),
            ),
            if (requiresSetup && _planOn)
              Align(
                alignment: Alignment.centerLeft,
                child: TextButton(
                  onPressed: () => _configureCustomTimes(item),
                  child: const Text('Elegir horarios'),
                ),
              ),
          ],
        );
      },
    );
  }
}
