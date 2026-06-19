import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

import '../config/api_config.dart';
import '../text/user_friendly_error.dart';
import 'ui_json_screen.dart';

/// Preview de turnos afectados por licencia (widget UI JSON `licencia_turnos_impact_preview`).
class LicenciaTurnosImpactPreviewWidget extends StatefulWidget {
  const LicenciaTurnosImpactPreviewWidget({
    super.key,
    required this.fieldValues,
    this.authToken,
    this.appClient = 'bioenlace-medico',
  });

  final Map<String, String> fieldValues;
  final String? authToken;
  final String appClient;

  @override
  State<LicenciaTurnosImpactPreviewWidget> createState() =>
      _LicenciaTurnosImpactPreviewWidgetState();
}

class _LicenciaTurnosImpactPreviewWidgetState extends State<LicenciaTurnosImpactPreviewWidget> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _data;

  @override
  void initState() {
    super.initState();
    _fetchPreview();
  }

  Future<void> _fetchPreview() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final uri = Uri.parse(resolveApiAbsoluteUrl('/api/v1/profesional-efector-servicio/preview-impacto-licencia'));
      final body = Map<String, dynamic>.from(widget.fieldValues);
      body['preview'] = '1';
      final headers = AppConfig.jsonHeaders(
        bearerToken: widget.authToken,
        appClient: widget.appClient,
      );
      final res = await http
          .post(uri, headers: headers, body: json.encode(body))
          .timeout(Duration(seconds: AppConfig.httpTimeoutSeconds));
      if (!mounted) return;
      if (res.statusCode < 200 || res.statusCode >= 300) {
        setState(() {
          _loading = false;
          _error = messageFromHttpResponse(res);
        });
        return;
      }
      final decoded = json.decode(utf8.decode(res.bodyBytes));
      if (decoded is Map && decoded['data'] is Map) {
        setState(() {
          _loading = false;
          _data = Map<String, dynamic>.from(decoded['data'] as Map);
        });
      } else {
        setState(() {
          _loading = false;
          _error = 'Respuesta inválida';
        });
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = userFriendlyErrorMessage(e);
      });
    }
  }

  String _formatFechaEs(String iso) {
    final m = RegExp(r'^(\d{4})-(\d{2})-(\d{2})').firstMatch(iso.trim());
    if (m == null) return iso;
    return '${m.group(3)}/${m.group(2)}/${m.group(1)!.substring(2)}';
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    if (_loading) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 8),
        child: Row(
          children: [
            SizedBox(
              width: 18,
              height: 18,
              child: CircularProgressIndicator(strokeWidth: 2),
            ),
            SizedBox(width: 10),
            Expanded(child: Text('Calculando impacto en turnos…')),
          ],
        ),
      );
    }
    if (_error != null) {
      return Text(
        _error!,
        style: TextStyle(color: theme.colorScheme.error, fontSize: 13),
      );
    }
    final data = _data;
    if (data == null) {
      return Text(
        'Sin datos de preview.',
        style: TextStyle(color: Colors.grey.shade700, fontSize: 13),
      );
    }
    final total = data['turnos_afectados_total'] is int
        ? data['turnos_afectados_total'] as int
        : int.tryParse(data['turnos_afectados_total']?.toString() ?? '') ?? 0;
    final mensaje = data['mensaje']?.toString() ?? '';
    final turnos = data['turnos'] is List ? data['turnos'] as List : const [];
    final alertColor = total > 0
        ? theme.colorScheme.errorContainer.withValues(alpha: 0.45)
        : theme.colorScheme.primaryContainer.withValues(alpha: 0.35);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: alertColor,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (mensaje.isNotEmpty)
            Text(
              mensaje,
              style: theme.textTheme.bodySmall?.copyWith(height: 1.35),
            ),
          if (total > 0) ...[
            const SizedBox(height: 8),
            Text(
              'Turnos pendientes ($total):',
              style: theme.textTheme.labelLarge,
            ),
            const SizedBox(height: 4),
            ...turnos.take(15).map((raw) {
              final t = raw is Map ? Map<String, dynamic>.from(raw) : <String, dynamic>{};
              final fecha = _formatFechaEs(t['fecha']?.toString() ?? '');
              final hora = t['hora']?.toString() ?? '';
              final pac = t['paciente']?.toString() ?? '';
              var line = '$fecha $hora'.trim();
              if (pac.isNotEmpty) line += ' — $pac';
              return Padding(
                padding: const EdgeInsets.only(left: 8, top: 2),
                child: Text('• $line', style: theme.textTheme.bodySmall),
              );
            }),
            if (total > turnos.length)
              Padding(
                padding: const EdgeInsets.only(left: 8, top: 2),
                child: Text(
                  '… y ${total - turnos.length} más',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: Colors.grey.shade700,
                  ),
                ),
              ),
            const SizedBox(height: 8),
            Text(
              'Confirmá solo si aceptás que esos turnos pasen a resolución.',
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.error,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ],
      ),
    );
  }
}
