// lib/screens/internacion/internacion_mapa_screen.dart
import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

import '../../services/internacion_api.dart';

/// Mapa operativo de camas (staff IMP).
class InternacionMapaScreen extends StatefulWidget {
  final String? authToken;
  final String? userId;

  const InternacionMapaScreen({super.key, this.authToken, this.userId});

  @override
  State<InternacionMapaScreen> createState() => _InternacionMapaScreenState();
}

class _InternacionMapaScreenState extends State<InternacionMapaScreen> {
  late final InternacionApi _api = InternacionApi(
    authToken: widget.authToken,
    userId: widget.userId,
  );

  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _mapa;
  Map<String, dynamic>? _indicadores;

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
    try {
      final mapa = await _api.mapaCamas();
      final ind = await _api.indicadoresResumen();
      if (!mounted) return;
      setState(() {
        _mapa = mapa;
        _indicadores = ind;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  Color _colorEstado(String estado) {
    switch (estado) {
      case 'ocupada':
        return Colors.red.shade700;
      case 'bloqueada':
        return Colors.grey.shade600;
      case 'aislamiento':
        return Colors.orange.shade800;
      default:
        return Colors.green.shade700;
    }
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Mapa de camas'),
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: _loading ? null : _load),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Padding(
                    padding: BioSpacing.pageAll,
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        BioAlert.danger(message: _error!),
                        BioSpacing.gapH(BioSpacing.md),
                        BioButton(
                          label: 'Reintentar',
                          onPressed: _load,
                        ),
                      ],
                    ),
                  ),
                )
              : ListView(
                  padding: BioSpacing.pageAll,
                  children: [
                    if (_mapa?['resumen_texto'] != null)
                      Text(
                        _mapa!['resumen_texto'].toString(),
                        style: BioTypography.body,
                      ),
                    if (_indicadores?['resumen_texto'] != null) ...[
                      BioSpacing.gapH(BioSpacing.sm),
                      Text(
                        _indicadores!['resumen_texto'].toString(),
                        style: BioTypography.caption,
                      ),
                    ],
                    BioSpacing.gapH(BioSpacing.lg),
                    ..._buildPisos(tokens),
                  ],
                ),
    );
  }

  List<Widget> _buildPisos(BioTokens tokens) {
    final pisos = _mapa?['pisos'] as List<dynamic>? ?? [];
    final widgets = <Widget>[];
    for (final piso in pisos) {
      if (piso is! Map) continue;
      widgets.add(
        Text(
          'Piso: ${piso['descripcion'] ?? ''}',
          style: BioTypography.h4,
        ),
      );
      widgets.add(BioSpacing.gapH(BioSpacing.sm));
      final salas = piso['salas'] as List<dynamic>? ?? [];
      for (final sala in salas) {
        if (sala is! Map) continue;
        widgets.add(
          Padding(
            padding: const EdgeInsets.only(left: 8, bottom: 8),
            child: Text('Sala: ${sala['descripcion'] ?? ''}', style: BioTypography.bodyBold),
          ),
        );
        final camas = sala['camas'] as List<dynamic>? ?? [];
        widgets.add(
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: camas.map<Widget>((c) {
              if (c is! Map) return const SizedBox.shrink();
              final estado = (c['estado_mapa'] ?? 'libre').toString();
              final nro = c['nro_cama']?.toString() ?? '?';
              final nombre = c['paciente_nombre']?.toString();
              final camaId = (c['id'] as num?)?.toInt() ?? 0;
              return ActionChip(
                label: Text(
                  nombre != null && nombre.isNotEmpty ? '$nro · $nombre' : '$nro · $estado',
                  style: const TextStyle(fontSize: 12),
                ),
                backgroundColor: _colorEstado(estado).withValues(alpha: 0.15),
                side: BorderSide(color: _colorEstado(estado)),
                onPressed: estado == 'ocupada'
                    ? null
                    : () => _accionesCama(camaId, estado),
              );
            }).toList(),
          ),
        );
        widgets.add(BioSpacing.gapH(BioSpacing.md));
      }
    }
    return widgets;
  }

  Future<void> _accionesCama(int camaId, String estadoActual) async {
    final choice = await showModalBottomSheet<String>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.block),
              title: const Text('Bloquear'),
              onTap: () => Navigator.pop(ctx, 'bloqueada'),
            ),
            ListTile(
              leading: const Icon(Icons.coronavirus_outlined),
              title: const Text('Aislamiento'),
              onTap: () => Navigator.pop(ctx, 'aislamiento'),
            ),
            if (estadoActual != 'libre')
              ListTile(
                leading: const Icon(Icons.check_circle_outline),
                title: const Text('Liberar'),
                onTap: () => Navigator.pop(ctx, 'libre'),
              ),
          ],
        ),
      ),
    );
    if (choice == null || !mounted) return;
    try {
      await _api.marcarEstadoCama(camaId, choice);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Cama actualizada')),
      );
      _load();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString())),
      );
    }
  }
}
