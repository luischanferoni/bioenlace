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
  int? _idPiso;
  int? _idSala;
  /// Catálogo completo para los selects (no depende del filtro activo).
  List<Map<String, dynamic>> _pisosCatalog = const [];

  @override
  void initState() {
    super.initState();
    _load(bootstrapCatalog: true);
  }

  Future<void> _load({bool bootstrapCatalog = false}) async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      if (bootstrapCatalog || _pisosCatalog.isEmpty) {
        final full = await _api.mapaCamas();
        final pisos = (full['pisos'] as List<dynamic>? ?? [])
            .whereType<Map>()
            .map((e) => Map<String, dynamic>.from(e))
            .toList();
        if (!mounted) return;
        _pisosCatalog = pisos;
      }
      final mapa = await _api.mapaCamas(idPiso: _idPiso, idSala: _idSala);
      if (!mounted) return;
      setState(() {
        _mapa = mapa;
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

  List<DropdownMenuItem<int?>> _pisoItems() {
    final items = <DropdownMenuItem<int?>>[
      const DropdownMenuItem<int?>(value: null, child: Text('Todos los pisos')),
    ];
    for (final piso in _pisosCatalog) {
      final id = (piso['id'] as num?)?.toInt();
      if (id == null) continue;
      items.add(DropdownMenuItem<int?>(
        value: id,
        child: Text('${piso['descripcion'] ?? 'Piso $id'}'),
      ));
    }
    return items;
  }

  List<DropdownMenuItem<int?>> _salaItems() {
    final items = <DropdownMenuItem<int?>>[
      const DropdownMenuItem<int?>(value: null, child: Text('Todas las salas')),
    ];
    if (_idPiso == null) return items;
    for (final piso in _pisosCatalog) {
      if ((piso['id'] as num?)?.toInt() != _idPiso) continue;
      final salas = piso['salas'] as List<dynamic>? ?? [];
      for (final sala in salas) {
        if (sala is! Map) continue;
        final id = (sala['id'] as num?)?.toInt();
        if (id == null) continue;
        items.add(DropdownMenuItem<int?>(
          value: id,
          child: Text('${sala['descripcion'] ?? 'Sala $id'}'),
        ));
      }
    }
    return items;
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    final resumen = (_mapa?['resumen'] is Map)
        ? Map<String, dynamic>.from(_mapa!['resumen'] as Map)
        : const <String, dynamic>{};

    return Scaffold(
      appBar: AppBar(
        title: const Text('Mapa de camas'),
        actions: [
                          IconButton(icon: const Icon(Icons.refresh), onPressed: _loading ? null : () => _load(bootstrapCatalog: true)),
        ],
      ),
      body: _loading && _mapa == null
          ? const Center(child: CircularProgressIndicator())
          : _error != null && _mapa == null
              ? Center(
                  child: Padding(
                    padding: BioSpacing.pageAll,
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        BioAlert.danger(message: _error!),
                        BioSpacing.gapH(BioSpacing.md),
                        BioButton(label: 'Reintentar', onPressed: _load),
                      ],
                    ),
                  ),
                )
              : ListView(
                  padding: BioSpacing.pageAll,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: DropdownButtonFormField<int?>(
                            value: _idPiso,
                            isExpanded: true,
                            decoration: const InputDecoration(
                              labelText: 'Piso',
                              border: OutlineInputBorder(),
                              isDense: true,
                            ),
                            items: _pisoItems(),
                            onChanged: _loading
                                ? null
                                : (v) {
                                    setState(() {
                                      _idPiso = v;
                                      _idSala = null;
                                    });
                                    _load();
                                  },
                          ),
                        ),
                        BioSpacing.gapW(BioSpacing.sm),
                        Expanded(
                          child: DropdownButtonFormField<int?>(
                            value: _idSala,
                            isExpanded: true,
                            decoration: const InputDecoration(
                              labelText: 'Sala',
                              border: OutlineInputBorder(),
                              isDense: true,
                            ),
                            items: _salaItems(),
                            onChanged: _loading || _idPiso == null
                                ? null
                                : (v) {
                                    setState(() => _idSala = v);
                                    _load();
                                  },
                          ),
                        ),
                      ],
                    ),
                    if (resumen.isNotEmpty) ...[
                      BioSpacing.gapH(BioSpacing.md),
                      Wrap(
                        spacing: BioSpacing.sm,
                        runSpacing: BioSpacing.sm,
                        children: [
                          _kpiChip(tokens, 'Camas', '${resumen['camas_total'] ?? 0}'),
                          _kpiChip(tokens, 'Ocupadas', '${resumen['ocupadas'] ?? 0}'),
                          _kpiChip(
                            tokens,
                            'Ocupación',
                            resumen['ocupacion_pct'] != null
                                ? '${resumen['ocupacion_pct']}%'
                                : '—',
                          ),
                          _kpiChip(tokens, 'Libres', '${resumen['libres'] ?? 0}'),
                        ],
                      ),
                    ],
                    if (_loading) ...[
                      BioSpacing.gapH(BioSpacing.md),
                      const LinearProgressIndicator(),
                    ],
                    BioSpacing.gapH(BioSpacing.lg),
                    ..._buildPisos(tokens),
                  ],
                ),
    );
  }

  Widget _kpiChip(BioTokens tokens, String label, String value) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: tokens.paperBackground,
        borderRadius: BioRadius.all(BioRadius.sm),
        border: BioBorder.all(BorderWidth.thin, tokens.paperBorderDefault),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: BioTypography.caption.copyWith(color: tokens.textMuted)),
          Text(value, style: BioTypography.title),
        ],
      ),
    );
  }

  List<Widget> _buildPisos(BioTokens tokens) {
    final pisos = _mapa?['pisos'] as List<dynamic>? ?? [];
    if (pisos.isEmpty) {
      return [
        Text(
          'No hay camas para mostrar con el filtro actual.',
          style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
        ),
      ];
    }
    final widgets = <Widget>[];
    for (final piso in pisos) {
      if (piso is! Map) continue;
      widgets.add(
        Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
          decoration: BoxDecoration(
            color: IntentPalette.of(UiIntent.primary).softBg,
            borderRadius: BioRadius.all(BioRadius.sm),
          ),
          child: Text(
            '${piso['descripcion'] ?? 'Piso'}',
            style: BioTypography.bodySm.copyWith(
              color: IntentPalette.of(UiIntent.primary).base,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      );
      widgets.add(BioSpacing.gapH(BioSpacing.sm));
      final salas = piso['salas'] as List<dynamic>? ?? [];
      for (final sala in salas) {
        if (sala is! Map) continue;
        widgets.add(
          Container(
            width: double.infinity,
            margin: const EdgeInsets.only(bottom: 8),
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            decoration: BoxDecoration(
              color: IntentPalette.of(UiIntent.success).softBg,
              borderRadius: BioRadius.all(BioRadius.sm),
            ),
            child: Text(
              '${sala['descripcion'] ?? 'Sala'}',
              style: BioTypography.bodySm.copyWith(
                color: IntentPalette.of(UiIntent.success).base,
                fontWeight: FontWeight.w600,
              ),
            ),
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
                  nombre != null && nombre.isNotEmpty ? 'Cama $nro · $nombre' : 'Cama $nro · $estado',
                  style: const TextStyle(fontSize: 12),
                ),
                backgroundColor: _colorEstado(estado).withValues(alpha: 0.15),
                side: BorderSide(color: _colorEstado(estado)),
                onPressed: estado == 'ocupada' ? null : () => _accionesCama(camaId, estado),
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
    if (choice == null) return;
    try {
      await _api.marcarEstadoCama(camaId, choice);
      await _load();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString())),
      );
    }
  }
}
