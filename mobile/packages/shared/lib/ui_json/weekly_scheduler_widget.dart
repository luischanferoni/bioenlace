import 'package:flutter/material.dart';

/// Grilla semanal mínima (7 días × 24 h) ↔ strings CSV `lunes_2`…`domingo_2` (índices 0–23), alineado a web/scheduler.
class WeeklySchedulerWidget extends StatefulWidget {
  final List<String> fieldNames;
  final Map<String, String> values;
  final ValueChanged<Map<String, String>> onChanged;
  final Map<String, Set<int>> busyHours;

  const WeeklySchedulerWidget({
    Key? key,
    required this.fieldNames,
    required this.values,
    required this.onChanged,
    this.busyHours = const {},
  }) : super(key: key);

  @override
  State<WeeklySchedulerWidget> createState() => _WeeklySchedulerWidgetState();
}

class _WeeklySchedulerWidgetState extends State<WeeklySchedulerWidget> {
  late List<Set<int>> _slots;

  static const _labels = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

  @override
  void initState() {
    super.initState();
    _slots = _buildSlotsFromValues();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        _emit();
      }
    });
  }

  @override
  void didUpdateWidget(covariant WeeklySchedulerWidget oldWidget) {
    super.didUpdateWidget(oldWidget);
    var changed = oldWidget.fieldNames != widget.fieldNames;
    if (!changed) {
      for (var i = 0; i < widget.fieldNames.length; i++) {
        final k = widget.fieldNames[i];
        if ((widget.values[k] ?? '') != (oldWidget.values[k] ?? '')) {
          changed = true;
          break;
        }
      }
    }
    if (!changed && oldWidget.busyHours != widget.busyHours) {
      changed = true;
    }
    if (changed) {
      _slots = _buildSlotsFromValues();
    }
  }

  bool _isBusy(int day, int hour) {
    if (day < 0 || day >= widget.fieldNames.length) {
      return false;
    }
    final name = widget.fieldNames[day];
    return widget.busyHours[name]?.contains(hour) ?? false;
  }

  List<Set<int>> _buildSlotsFromValues() {
    final n = widget.fieldNames.length.clamp(0, 7);
    return List.generate(7, (i) {
      if (i >= n) return {};
      final raw = widget.values[widget.fieldNames[i]] ?? '';
      final parsed = _parseCsv(raw);
      parsed.removeWhere((h) => _isBusy(i, h));
      return parsed;
    });
  }

  Set<int> _parseCsv(String s) {
    if (s.trim().isEmpty) return {};
    return s
        .split(',')
        .map((e) => int.tryParse(e.trim()))
        .whereType<int>()
        .where((h) => h >= 0 && h < 24)
        .toSet();
  }

  void _emit() {
    final m = <String, String>{};
    for (var i = 0; i < widget.fieldNames.length && i < 7; i++) {
      final sorted = _slots[i].toList()..sort();
      m[widget.fieldNames[i]] = sorted.join(',');
    }
    widget.onChanged(m);
  }

  void _toggle(int day, int hour) {
    if (_isBusy(day, hour)) {
      return;
    }
    setState(() {
      if (_slots[day].contains(hour)) {
        _slots[day].remove(hour);
      } else {
        _slots[day].add(hour);
      }
    });
    _emit();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: List.generate(7, (d) {
        return Padding(
          padding: const EdgeInsets.only(bottom: 10),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SizedBox(
                width: 36,
                child: Text(
                  d < _labels.length ? _labels[d] : '$d',
                  style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
                ),
              ),
              Expanded(
                child: Wrap(
                  spacing: 3,
                  runSpacing: 3,
                  children: List.generate(24, (h) {
                    final busy = _isBusy(d, h);
                    final on = !busy && _slots[d].contains(h);
                    return FilterChip(
                      label: Text('$h', style: const TextStyle(fontSize: 10)),
                      selected: on,
                      onSelected: busy ? null : (_) => _toggle(d, h),
                      visualDensity: VisualDensity.compact,
                      materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    );
                  }),
                ),
              ),
            ],
          ),
        );
      }),
    );
  }
}
