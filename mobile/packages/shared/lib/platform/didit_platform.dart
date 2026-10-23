import 'package:flutter/foundation.dart';

import '../text/shared_texts.dart';

/// Didit solo expone implementación nativa en Android e iOS (no web ni desktop).
bool get isDiditSupported =>
    !kIsWeb &&
    (defaultTargetPlatform == TargetPlatform.android ||
        defaultTargetPlatform == TargetPlatform.iOS);

const String diditUnsupportedPlatformMessage = SharedTexts.diditUnsupportedPlatform;

const String diditMissingPluginMessage = SharedTexts.diditMissingPlugin;
