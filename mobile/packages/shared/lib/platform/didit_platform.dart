import 'package:flutter/foundation.dart';

/// Didit solo expone implementación nativa en Android e iOS (no web ni desktop).
bool get isDiditSupported =>
    !kIsWeb &&
    (defaultTargetPlatform == TargetPlatform.android ||
        defaultTargetPlatform == TargetPlatform.iOS);

const String diditUnsupportedPlatformMessage =
    'La verificación de identidad (Didit) solo funciona en la app móvil '
    'Android o iOS. Usá un dispositivo o emulador; no está disponible en '
    'navegador ni en escritorio.';

const String diditMissingPluginMessage =
    'No se pudo iniciar Didit en este dispositivo. Detené la app, ejecutá '
    '«flutter clean», volvé a compilar e instalá de nuevo (no uses hot reload '
    'ni Chrome).';
