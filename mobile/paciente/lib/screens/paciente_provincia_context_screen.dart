import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

/// Permite elegir la provincia de contexto (no modifica el domicilio RENAPER).
class PacienteProvinciaContextScreen extends StatefulWidget {
  final String? authToken;

  const PacienteProvinciaContextScreen({
    super.key,
    this.authToken,
  });

  @override
  State<PacienteProvinciaContextScreen> createState() =>
      _PacienteProvinciaContextScreenState();
}

class _PacienteProvinciaContextScreenState
    extends State<PacienteProvinciaContextScreen> {
  late final PacienteContextApi _api =
      PacienteContextApi(authToken: widget.authToken);

  List<Map<String, dynamic>> _provincias = [];
  bool _loading = true;
  bool _saving = false;
  String? _error;

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
      final res = await _api.sugerirProvincias();
      if (!mounted) return;
      if (res['success'] == true) {
        final list = res['data']?['provincias'];
        _provincias = list is List
            ? list
                .whereType<Map>()
                .map((e) => Map<String, dynamic>.from(e))
                .toList()
            : [];
      } else {
        _error = res['message']?.toString() ?? 'No se pudieron cargar provincias';
      }
    } catch (e) {
      _error = e.toString();
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _elegir(int idProvincia) async {
    setState(() => _saving = true);
    final ok = await PacienteContextScope.instance.actualizarProvincia(
      idProvincia,
      authToken: widget.authToken,
    );
    if (!mounted) return;
    setState(() => _saving = false);
    if (ok) {
      Navigator.pop(context, true);
      return;
    }
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('No se pudo guardar la provincia')),
    );
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    return Scaffold(
      backgroundColor: tokens.paperBackground,
      appBar: const BioAppBar(title: 'Provincia de contexto'),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: BioSpacing.pageAll,
              children: [
                Text(
                  'Esta provincia define qué servicios y respuestas verás en la app. '
                  'No cambia tu domicilio registrado.',
                  style: BioTypography.bodySm.copyWith(color: tokens.textMuted),
                ),
                BioSpacing.gapH(BioSpacing.lg),
                if (_error != null)
                  Text(
                    _error!,
                    style: BioTypography.bodySm.copyWith(
                      color: IntentPalette.of(UiIntent.danger).base,
                    ),
                  ),
                if (_provincias.isEmpty && _error == null)
                  const Text('No hay provincias sugeridas disponibles.'),
                ..._provincias.map((p) {
                  final id = p['id_provincia'] as int? ?? 0;
                  final nombre = p['nombre']?.toString() ?? 'Provincia';
                  return BioCard(
                    margin: const EdgeInsets.only(bottom: BioSpacing.sm),
                    child: ListTile(
                      contentPadding: EdgeInsets.zero,
                      title: Text(nombre, style: BioTypography.title),
                      trailing: _saving
                          ? const SizedBox(
                              width: 24,
                              height: 24,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : Icon(Icons.chevron_right, color: tokens.textMuted),
                      onTap: _saving || id <= 0 ? null : () => _elegir(id),
                    ),
                  );
                }),
              ],
            ),
    );
  }
}
