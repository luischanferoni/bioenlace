import 'package:flutter/material.dart';

import '../theme/tokens/tokens.dart';
import '../ui/bio_card.dart';
import 'care_pack_navigation.dart';
import 'encounter_journey_api.dart';
import 'encounter_journey_navigation.dart';

/// Hub de fases del recorrido encounter (pre o post consulta), leyendo solo `journey`.
class EncounterJourneyHubScreen extends StatefulWidget {
  final String title;
  final String intro;
  final Map<String, dynamic> turno;
  final List<String> phaseIds;
  final String? authToken;
  final int? subjectPersonaId;
  final AbrirMotivosConsulta onOpenMotivos;
  final String appClient;

  const EncounterJourneyHubScreen({
    super.key,
    required this.title,
    required this.intro,
    required this.turno,
    required this.phaseIds,
    this.authToken,
    this.subjectPersonaId,
    required this.onOpenMotivos,
    this.appClient = 'paciente-flutter',
  });

  @override
  State<EncounterJourneyHubScreen> createState() =>
      _EncounterJourneyHubScreenState();
}

class _EncounterJourneyHubScreenState extends State<EncounterJourneyHubScreen> {
  late Map<String, dynamic> _turno;
  bool _refreshing = false;

  @override
  void initState() {
    super.initState();
    _turno = Map<String, dynamic>.from(widget.turno);
    _refreshEstado();
  }

  Future<void> _refreshEstado() async {
    final turnoId = turnoIdDesdePayloadProducto(_turno);
    if (turnoId == null) return;
    setState(() => _refreshing = true);
    try {
      final estado = await EncounterJourneyApi(
        authToken: widget.authToken,
        appClient: widget.appClient,
      ).fetchEstado(
        turnoId: turnoId,
        subjectPersonaId: widget.subjectPersonaId,
      );
      if (!mounted || estado == null) return;
      setState(() {
        _turno = turnoConJourneyDesdeEstado(_turno, estado);
      });
    } finally {
      if (mounted) setState(() => _refreshing = false);
    }
  }

  void _onEntryTap(JourneyHubEntry entry) {
    if (!entry.enabled) {
      final msg = entry.subtitle ?? 'No disponible por ahora';
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(msg)),
      );
      return;
    }
    abrirJourneyHubEntry(
      context: context,
      turno: _turno,
      entry: entry,
      authToken: widget.authToken,
      subjectPersonaId: widget.subjectPersonaId,
      onOpenMotivos: widget.onOpenMotivos,
      appClient: widget.appClient,
    );
  }

  @override
  Widget build(BuildContext context) {
    final tokens = context.bio;
    final entries = journeyHubEntries(_turno, widget.phaseIds);
    final fecha = _turno['fecha']?.toString() ?? '';
    final hora = _turno['hora']?.toString() ?? '';
    final horaCorta = hora.length >= 5 ? hora.substring(0, 5) : hora;
    final subtituloTurno = fecha.isNotEmpty
        ? (horaCorta.isNotEmpty ? '$fecha · $horaCorta' : fecha)
        : null;

    return Scaffold(
      appBar: AppBar(
        title: Text(widget.title),
        actions: [
          if (_refreshing)
            const Padding(
              padding: EdgeInsets.only(right: BioSpacing.md),
              child: Center(
                child: SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                ),
              ),
            ),
        ],
      ),
      body: entries.isEmpty
          ? Center(
              child: Padding(
                padding: const EdgeInsets.all(BioSpacing.lg),
                child: Text(
                  'No hay acciones pendientes para este turno.',
                  textAlign: TextAlign.center,
                  style: BioTypography.body.copyWith(color: tokens.textMuted),
                ),
              ),
            )
          : ListView(
              padding: const EdgeInsets.all(BioSpacing.md),
              children: [
                if (subtituloTurno != null) ...[
                  Text(
                    'Turno del $subtituloTurno',
                    style: BioTypography.bodySm.copyWith(
                      color: tokens.textMuted,
                    ),
                  ),
                  BioSpacing.gapH(BioSpacing.md),
                ],
                Text(widget.intro, style: BioTypography.body),
                BioSpacing.gapH(BioSpacing.md),
                ...entries.map((entry) {
                  return Padding(
                    padding: const EdgeInsets.only(bottom: BioSpacing.sm),
                    // onTap en BioCard (no ListTile): en Flutter web el click de
                    // mouse falla con frecuencia si el InkWell queda anidado.
                    child: BioCard(
                      onTap: () => _onEntryTap(entry),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Icon(
                            _iconoFase(entry.phaseId),
                            color: entry.enabled
                                ? tokens.intentPalette(UiIntent.primary).base
                                : tokens.textMuted,
                          ),
                          BioSpacing.gapW(BioSpacing.md),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(entry.label, style: BioTypography.title),
                                if (entry.subtitle != null) ...[
                                  BioSpacing.gapH(BioSpacing.xs),
                                  Text(
                                    entry.subtitle!,
                                    style: BioTypography.bodySm.copyWith(
                                      color: tokens.textMuted,
                                    ),
                                  ),
                                ],
                              ],
                            ),
                          ),
                          if (entry.enabled)
                            Icon(
                              Icons.chevron_right,
                              color: tokens.textMuted,
                            ),
                        ],
                      ),
                    ),
                  );
                }),
              ],
            ),
    );
  }

  IconData _iconoFase(String phaseId) {
    switch (phaseId) {
      case kEncounterJourneyPhaseMotivos:
        return Icons.edit_note;
      case kEncounterJourneyPhaseAsistencia:
        return Icons.fact_check_outlined;
      case kEncounterJourneyPhasePostConsulta:
        return Icons.health_and_safety_outlined;
      default:
        return Icons.checklist_outlined;
    }
  }
}

void abrirEncounterJourneyHub({
  required BuildContext context,
  required String title,
  required String intro,
  required Map<String, dynamic> turno,
  required List<String> phaseIds,
  String? authToken,
  int? subjectPersonaId,
  required AbrirMotivosConsulta onOpenMotivos,
  String appClient = 'paciente-flutter',
}) {
  Navigator.of(context).push(
    MaterialPageRoute(
      builder: (_) => EncounterJourneyHubScreen(
        title: title,
        intro: intro,
        turno: turno,
        phaseIds: phaseIds,
        authToken: authToken,
        subjectPersonaId: subjectPersonaId,
        onOpenMotivos: onOpenMotivos,
        appClient: appClient,
      ),
    ),
  );
}

void abrirPrepararConsultaHub({
  required BuildContext context,
  required Map<String, dynamic> turno,
  String? authToken,
  int? subjectPersonaId,
  required AbrirMotivosConsulta onOpenMotivos,
  String appClient = 'paciente-flutter',
}) {
  abrirEncounterJourneyHub(
    context: context,
    title: 'Preparar tu consulta',
    intro: 'Completá estos pasos antes de tu consulta.',
    turno: turno,
    phaseIds: kEncounterJourneyPreTurnoPhases,
    authToken: authToken,
    subjectPersonaId: subjectPersonaId,
    onOpenMotivos: onOpenMotivos,
    appClient: appClient,
  );
}

void abrirSeguimientoPostConsultaHub({
  required BuildContext context,
  required Map<String, dynamic> turno,
  String? authToken,
  int? subjectPersonaId,
  required AbrirMotivosConsulta onOpenMotivos,
  String appClient = 'paciente-flutter',
}) {
  abrirEncounterJourneyHub(
    context: context,
    title: 'Seguimiento post-consulta',
    intro: 'Contanos cómo seguís después de tu atención.',
    turno: turno,
    phaseIds: kEncounterJourneyPostTurnoPhases,
    authToken: authToken,
    subjectPersonaId: subjectPersonaId,
    onOpenMotivos: onOpenMotivos,
    appClient: appClient,
  );
}
