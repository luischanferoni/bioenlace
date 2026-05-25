import 'package:flutter/material.dart';
import 'package:shared/shared.dart';

/// Detalle de resumen de atención (texto IA + enlaces a receta/lab).
class EncounterSummaryDetailScreen extends StatefulWidget {
  final int encounterId;
  final String? authToken;
  final Map<String, dynamic>? initialDetail;

  const EncounterSummaryDetailScreen({
    super.key,
    required this.encounterId,
    this.authToken,
    this.initialDetail,
  });

  @override
  State<EncounterSummaryDetailScreen> createState() =>
      _EncounterSummaryDetailScreenState();
}

class _EncounterSummaryDetailScreenState extends State<EncounterSummaryDetailScreen> {
  late EncounterPatientSummaryApi _api;
  Map<String, dynamic>? _detail;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _api = EncounterPatientSummaryApi(authToken: widget.authToken);
    if (widget.initialDetail != null) {
      _detail = widget.initialDetail;
      _loading = false;
    } else {
      _load();
    }
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      _detail = await _api.fetchDetail(widget.encounterId);
    } catch (e) {
      _error = e.toString();
    }
    if (!mounted) return;
    setState(() => _loading = false);
  }

  void _openUiJson(String path) {
    final normalized = AppConfig.normalizeApiV1Path(path);
    final uri = normalized.startsWith('http')
        ? Uri.parse(normalized)
        : Uri.parse('${AppConfig.apiUrl}$normalized');
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => UiJsonScreen(
          apiAbsoluteUrl: uri.toString(),
          authToken: widget.authToken,
          appClient: 'paciente-flutter',
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    return Scaffold(
      backgroundColor: tokens.paperBackground,
      appBar: const BioAppBar(title: 'Resumen de atención'),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: BioAlert.danger(message: _error!))
              : _buildBody(context),
    );
  }

  Widget _buildBody(BuildContext context) {
    final d = _detail!;
    final narrative = d['narrativeText']?.toString() ?? '';
    final efector = d['efector'] is Map
        ? (d['efector'] as Map)['nombre']?.toString()
        : null;
    final profesional = d['profesional'] is Map
        ? (d['profesional'] as Map)['display']?.toString()
        : null;
    final fecha = d['periodEnd']?.toString() ?? d['publishedAt']?.toString();

    final prescriptions = d['prescriptions'] is List ? d['prescriptions'] as List : [];
    final labs = d['laboratoryReports'] is List ? d['laboratoryReports'] as List : [];
    final orders = d['orders'] is List ? d['orders'] as List : [];

    return ListView(
      padding: BioSpacing.pageAll,
      children: [
        if (efector != null && efector.isNotEmpty)
          Text(efector, style: BioTypography.title.copyWith(fontWeight: FontWeight.w700)),
        if (profesional != null && profesional.isNotEmpty) ...[
          BioSpacing.gapH(BioSpacing.xs),
          Text('Profesional: $profesional', style: BioTypography.body),
        ],
        if (fecha != null && fecha.isNotEmpty) ...[
          BioSpacing.gapH(BioSpacing.xs),
          Text(fecha, style: BioTypography.bodySm.copyWith(color: context.bio.textMuted)),
        ],
        BioSpacing.gapH(BioSpacing.lg),
        BioCard(
          child: Padding(
            padding: BioSpacing.pageAll,
            child: Text(
              narrative.isNotEmpty
                  ? narrative
                  : 'Sin resumen narrativo disponible para esta atención.',
              style: BioTypography.body,
            ),
          ),
        ),
        if (prescriptions.isNotEmpty) ...[
          BioSpacing.gapH(BioSpacing.lg),
          Text('Recetas', style: BioTypography.title),
          BioSpacing.gapH(BioSpacing.sm),
          ...prescriptions.whereType<Map>().map((p) {
            final route = p['detailApiRoute']?.toString() ??
                '/clinical/electronic-prescription/ver-receta-como-paciente?prescription_id=${p['id']}';
            return _linkTile(
              context,
              'Receta electrónica #${p['id']}',
              'Ver detalle y PDF',
              () => _openUiJson(route),
            );
          }),
        ],
        if (labs.isNotEmpty) ...[
          BioSpacing.gapH(BioSpacing.lg),
          Text('Resultados de laboratorio', style: BioTypography.title),
          BioSpacing.gapH(BioSpacing.sm),
          ...labs.whereType<Map>().map((lr) {
            final route = lr['detailApiRoute']?.toString() ??
                '/clinical/laboratory-result/ver-informe-como-paciente?report_id=${lr['id']}';
            return _linkTile(
              context,
              lr['display']?.toString() ?? 'Informe',
              lr['issuedAt']?.toString() ?? '',
              () => _openUiJson(route),
            );
          }),
        ],
        if (orders.isNotEmpty) ...[
          BioSpacing.gapH(BioSpacing.lg),
          Text('Pedidos e indicaciones', style: BioTypography.title),
          BioSpacing.gapH(BioSpacing.sm),
          ...orders.whereType<Map>().map((o) {
            final status = o['resultStatus']?.toString() ?? '';
            final statusLabel = status == 'available'
                ? ' · resultado disponible'
                : (status == 'pending' ? ' · pendiente' : '');
            return Padding(
              padding: const EdgeInsets.only(bottom: BioSpacing.sm),
              child: BioCard(
                child: ListTile(
                  title: Text(o['display']?.toString() ?? 'Pedido'),
                  subtitle: Text(
                    '${o['category'] ?? ''}$statusLabel',
                    style: BioTypography.bodySm,
                  ),
                ),
              ),
            );
          }),
        ],
      ],
    );
  }

  Widget _linkTile(
    BuildContext context,
    String title,
    String subtitle,
    VoidCallback onTap,
  ) {
    return Padding(
      padding: const EdgeInsets.only(bottom: BioSpacing.sm),
      child: BioCard(
        child: ListTile(
          title: Text(title),
          subtitle: subtitle.isNotEmpty ? Text(subtitle) : null,
          trailing: const Icon(Icons.chevron_right),
          onTap: onTap,
        ),
      ),
    );
  }
}
