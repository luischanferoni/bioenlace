import 'package:flutter/material.dart';

/// Antes mostraba «A cargo de: Yo» al operar por uno mismo con otros sujetos.
/// Se dejó de mostrar: el cambio de sujeto entra por Configuración → Representación
/// o por la barra de modo parental cuando ya se opera por otro.
class PersonRepresentationSubjectChip extends StatelessWidget {
  final String? authToken;
  final VoidCallback? onSubjectChanged;

  const PersonRepresentationSubjectChip({
    super.key,
    this.authToken,
    this.onSubjectChanged,
  });

  @override
  Widget build(BuildContext context) => const SizedBox.shrink();
}
