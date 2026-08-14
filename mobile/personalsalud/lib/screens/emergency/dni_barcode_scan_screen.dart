// lib/screens/emergency/dni_barcode_scan_screen.dart
import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:shared/shared.dart';

/// Escaneo del código PDF417 / QR del DNI con la cámara del dispositivo.
class DniBarcodeScanScreen extends StatefulWidget {
  const DniBarcodeScanScreen({super.key});

  @override
  State<DniBarcodeScanScreen> createState() => _DniBarcodeScanScreenState();
}

class _DniBarcodeScanScreenState extends State<DniBarcodeScanScreen> {
  final MobileScannerController _controller = MobileScannerController(
    detectionSpeed: DetectionSpeed.noDuplicates,
    formats: const [
      BarcodeFormat.pdf417,
      BarcodeFormat.qrCode,
      BarcodeFormat.dataMatrix,
      BarcodeFormat.aztec,
    ],
  );
  bool _handled = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _onDetect(BarcodeCapture capture) {
    if (_handled) return;
    for (final barcode in capture.barcodes) {
      final raw = barcode.rawValue?.trim();
      if (raw == null || raw.isEmpty) continue;
      _handled = true;
      Navigator.pop(context, raw);
      return;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Escanear DNI'),
        actions: [
          IconButton(
            tooltip: 'Linterna',
            onPressed: () => _controller.toggleTorch(),
            icon: ValueListenableBuilder(
              valueListenable: _controller,
              builder: (context, state, _) {
                final on = state.torchState == TorchState.on;
                return Icon(on ? Icons.flash_on : Icons.flash_off);
              },
            ),
          ),
        ],
      ),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.all(BioSpacing.md),
            child: Text(
              'Apuntá la cámara al código de barras del dorso del DNI.',
              style: BioTypography.bodySm,
            ),
          ),
          Expanded(
            child: MobileScanner(
              controller: _controller,
              onDetect: _onDetect,
            ),
          ),
        ],
      ),
    );
  }
}
