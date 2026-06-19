import 'package:flutter/material.dart';

/// Leyenda de duración (días inclusive) entre [fechaInicio] y [fechaFin] (ISO Y-m-d).
class LicenciaRangoDiasLegend extends StatelessWidget {
  const LicenciaRangoDiasLegend({
    super.key,
    required this.fechaInicio,
    required this.fechaFin,
  });

  final String? fechaInicio;
  final String? fechaFin;

  static int? countInclusiveCalendarDays(String? fi, String? ff) {
    final start = _parseYmd(fi);
    final end = _parseYmd(ff);
    if (start == null || end == null) {
      return null;
    }
    final diff = end.difference(start).inDays;
    if (diff <= 0) {
      return null;
    }
    return diff + 1;
  }

  static String leyendaFromDates(String? fi, String? ff) {
    final n = countInclusiveCalendarDays(fi, ff);
    if (n == null || n < 1) {
      return '';
    }
    return n == 1 ? '1 día' : '$n días';
  }

  static String hintFormulario(String? fi, String? ff) {
    final leyenda = leyendaFromDates(fi, ff);
    if (leyenda.isNotEmpty) {
      return 'Duración: $leyenda';
    }
    final fiT = (fi ?? '').trim();
    final ffT = (ff ?? '').trim();
    if (fiT.isNotEmpty && ffT.isEmpty) {
      return 'Indicá la fecha de fin para ver la duración.';
    }
    if (fiT.isEmpty && ffT.isNotEmpty) {
      return 'Indicá la fecha de inicio para ver la duración.';
    }
    return 'Seleccioná fecha de inicio y fin para ver la duración.';
  }

  static DateTime? _parseYmd(String? iso) {
    final s = (iso ?? '').trim();
    final m = RegExp(r'^(\d{4})-(\d{2})-(\d{2})').firstMatch(s);
    if (m == null) {
      return null;
    }
    final y = int.tryParse(m.group(1)!);
    final mo = int.tryParse(m.group(2)!);
    final d = int.tryParse(m.group(3)!);
    if (y == null || mo == null || d == null) {
      return null;
    }
    return DateTime(y, mo, d);
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 4),
      child: Text(
        hintFormulario(fechaInicio, fechaFin),
        style: TextStyle(
          fontSize: 13,
          color: Colors.grey.shade700,
        ),
      ),
    );
  }
}
