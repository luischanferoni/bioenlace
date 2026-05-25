// lib/screens/emergency/emergency_triage_screen.dart
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:shared/shared.dart';

import '../../services/emergency_guardia_api.dart';

/// Triage rápido (móvil) para un ingreso en guardia.
class EmergencyTriageScreen extends StatefulWidget {
  final int guardiaId;
  final String pacienteNombre;
  final EmergencyGuardiaApi api;

  const EmergencyTriageScreen({
    Key? key,
    required this.guardiaId,
    required this.pacienteNombre,
    required this.api,
  }) : super(key: key);

  @override
  State<EmergencyTriageScreen> createState() => _EmergencyTriageScreenState();
}

class _EmergencyTriageScreenState extends State<EmergencyTriageScreen> {
  int _level = 3;
  final _reasonController = TextEditingController();
  final _hrController = TextEditingController();
  final _sysController = TextEditingController();
  final _diaController = TextEditingController();
  bool _saving = false;

  static const _levelColors = [
    Color(0xFFC0392B),
    Color(0xFFE67E22),
    Color(0xFFF1C40F),
    Color(0xFF27AE60),
    Color(0xFF3498DB),
  ];

  @override
  void dispose() {
    _reasonController.dispose();
    _hrController.dispose();
    _sysController.dispose();
    _diaController.dispose();
    super.dispose();
  }

  Future<void> _guardar() async {
    final reason = _reasonController.text.trim();
    if (reason.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Indicá el motivo de consulta.')),
      );
      return;
    }
    setState(() => _saving = true);
    try {
      final vitals = <String, dynamic>{};
      if (_hrController.text.trim().isNotEmpty) {
        vitals['hr'] = int.tryParse(_hrController.text.trim());
      }
      if (_sysController.text.trim().isNotEmpty) {
        vitals['bp_sys'] = int.tryParse(_sysController.text.trim());
      }
      if (_diaController.text.trim().isNotEmpty) {
        vitals['bp_dia'] = int.tryParse(_diaController.text.trim());
      }
      await widget.api.registrarTriage(
        guardiaId: widget.guardiaId,
        level: _level,
        reasonText: reason,
        vitals: vitals.isEmpty ? null : vitals,
      );
      if (mounted) Navigator.of(context).pop(true);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('No se pudo guardar: $e')),
        );
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    return Scaffold(
      backgroundColor: tokens.paperBackground,
      appBar: AppBar(
        title: const Text('Triage'),
        backgroundColor: tokens.paperSurface,
      ),
      body: ListView(
        padding: BioSpacing.pageAll,
        children: [
          Text(widget.pacienteNombre, style: BioTypography.h3),
          BioSpacing.gapH(BioSpacing.lg),
          Text('Prioridad (Manchester)', style: BioTypography.title),
          BioSpacing.gapH(BioSpacing.sm),
          Wrap(
            spacing: BioSpacing.sm,
            children: List.generate(5, (i) {
              final level = i + 1;
              final selected = _level == level;
              return ChoiceChip(
                label: Text('$level'),
                selected: selected,
                selectedColor: _levelColors[i].withOpacity(0.35),
                onSelected: (_) => setState(() => _level = level),
              );
            }),
          ),
          BioSpacing.gapH(BioSpacing.lg),
          TextField(
            controller: _reasonController,
            decoration: const InputDecoration(
              labelText: 'Motivo de consulta',
              border: OutlineInputBorder(),
            ),
            maxLines: 2,
          ),
          BioSpacing.gapH(BioSpacing.md),
          Text('Signos vitales (opcional)', style: BioTypography.title),
          BioSpacing.gapH(BioSpacing.sm),
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _sysController,
                  keyboardType: TextInputType.number,
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                  decoration: const InputDecoration(
                    labelText: 'TA sist.',
                    border: OutlineInputBorder(),
                  ),
                ),
              ),
              BioSpacing.gapW(BioSpacing.sm),
              Expanded(
                child: TextField(
                  controller: _diaController,
                  keyboardType: TextInputType.number,
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                  decoration: const InputDecoration(
                    labelText: 'TA diast.',
                    border: OutlineInputBorder(),
                  ),
                ),
              ),
            ],
          ),
          BioSpacing.gapH(BioSpacing.sm),
          TextField(
            controller: _hrController,
            keyboardType: TextInputType.number,
            inputFormatters: [FilteringTextInputFormatter.digitsOnly],
            decoration: const InputDecoration(
              labelText: 'FC',
              border: OutlineInputBorder(),
            ),
          ),
          BioSpacing.gapH(BioSpacing.xl),
          BioButton(
            label: _saving ? 'Guardando…' : 'Registrar triage',
            intent: UiIntent.primary,
            onPressed: _saving ? null : _guardar,
          ),
        ],
      ),
    );
  }
}
