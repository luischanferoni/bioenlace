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
  final bool isRetriage;
  final int? initialLevel;
  final String? initialReason;
  final int? initialBpSys;
  final int? initialBpDia;
  final int? initialHr;

  const EmergencyTriageScreen({
    Key? key,
    required this.guardiaId,
    required this.pacienteNombre,
    required this.api,
    this.isRetriage = false,
    this.initialLevel,
    this.initialReason,
    this.initialBpSys,
    this.initialBpDia,
    this.initialHr,
  }) : super(key: key);

  @override
  State<EmergencyTriageScreen> createState() => _EmergencyTriageScreenState();
}

class _EmergencyTriageScreenState extends State<EmergencyTriageScreen> {
  late int _level;
  late final TextEditingController _reasonController;
  late final TextEditingController _hrController;
  late final TextEditingController _sysController;
  late final TextEditingController _diaController;
  bool _saving = false;

  static const _levelColors = [
    Color(0xFFC0392B),
    Color(0xFFE67E22),
    Color(0xFFF1C40F),
    Color(0xFF27AE60),
    Color(0xFF3498DB),
  ];

  static final _vitalFormatters = <TextInputFormatter>[
    FilteringTextInputFormatter.digitsOnly,
    LengthLimitingTextInputFormatter(3),
  ];

  @override
  void initState() {
    super.initState();
    _level = widget.initialLevel ?? 3;
    _reasonController = TextEditingController(text: widget.initialReason ?? '');
    _sysController = TextEditingController(
      text: widget.initialBpSys != null ? '${widget.initialBpSys}' : '',
    );
    _diaController = TextEditingController(
      text: widget.initialBpDia != null ? '${widget.initialBpDia}' : '',
    );
    _hrController = TextEditingController(
      text: widget.initialHr != null ? '${widget.initialHr}' : '',
    );
  }

  @override
  void dispose() {
    _reasonController.dispose();
    _hrController.dispose();
    _sysController.dispose();
    _diaController.dispose();
    super.dispose();
  }

  String? _validateVital(String raw, String label, int min, int max) {
    final s = raw.trim();
    if (s.isEmpty) return null;
    if (!RegExp(r'^\d{2,3}$').hasMatch(s)) {
      return '$label: ingresá un entero de 2 o 3 dígitos.';
    }
    final n = int.parse(s);
    if (n < min || n > max) {
      return '$label debe estar entre $min y $max.';
    }
    return null;
  }

  /// Alineado con GuardiaTriageVitalsValidator (PHP).
  String? _validateVitalsClient() {
    final sysErr = _validateVital(_sysController.text, 'TA sistólica', 50, 250);
    if (sysErr != null) return sysErr;
    final diaErr = _validateVital(_diaController.text, 'TA diastólica', 30, 150);
    if (diaErr != null) return diaErr;
    final hrErr = _validateVital(_hrController.text, 'FC', 20, 250);
    if (hrErr != null) return hrErr;

    final sys = _sysController.text.trim();
    final dia = _diaController.text.trim();
    if (sys.isNotEmpty && dia.isNotEmpty) {
      final s = int.parse(sys);
      final d = int.parse(dia);
      if (s <= d) {
        return 'TA sistólica debe ser mayor que la diastólica.';
      }
    }
    return null;
  }

  Map<String, dynamic>? _buildVitals() {
    final out = <String, dynamic>{};
    final sys = _sysController.text.trim();
    final dia = _diaController.text.trim();
    final hr = _hrController.text.trim();
    if (sys.isNotEmpty) out['bp_sys'] = int.parse(sys);
    if (dia.isNotEmpty) out['bp_dia'] = int.parse(dia);
    if (hr.isNotEmpty) out['hr'] = int.parse(hr);
    return out.isEmpty ? null : out;
  }

  Future<void> _guardar() async {
    final reason = _reasonController.text.trim();
    if (reason.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Indicá el motivo de consulta.')),
      );
      return;
    }
    final vitalsErr = _validateVitalsClient();
    if (vitalsErr != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(vitalsErr)),
      );
      return;
    }
    setState(() => _saving = true);
    try {
      await widget.api.registrarTriage(
        guardiaId: widget.guardiaId,
        level: _level,
        reasonText: reason,
        vitals: _buildVitals(),
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
        title: Text(widget.isRetriage ? 'Actualizar triage' : 'Triage'),
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
          BioSpacing.gapH(BioSpacing.xs),
          Text(
            'Enteros de 2–3 dígitos. TA sys 50–250 · TA dia 30–150 · FC 20–250.',
            style: BioTypography.caption.copyWith(color: tokens.textMuted),
          ),
          BioSpacing.gapH(BioSpacing.sm),
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _sysController,
                  keyboardType: TextInputType.number,
                  inputFormatters: _vitalFormatters,
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
                  inputFormatters: _vitalFormatters,
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
            inputFormatters: _vitalFormatters,
            decoration: const InputDecoration(
              labelText: 'FC',
              border: OutlineInputBorder(),
            ),
          ),
          BioSpacing.gapH(BioSpacing.xl),
          BioButton(
            label: _saving
                ? 'Guardando…'
                : (widget.isRetriage ? 'Guardar cambios' : 'Registrar triage'),
            intent: UiIntent.primary,
            onPressed: _saving ? null : _guardar,
          ),
        ],
      ),
    );
  }
}
