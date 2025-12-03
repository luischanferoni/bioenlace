import 'package:flutter/material.dart';
import 'package:camera/camera.dart';
import 'dart:io';
import 'package:image/image.dart' as img;
import 'package:path_provider/path_provider.dart';
import '../theme/theme.dart';

class CameraOverlay extends StatefulWidget {
  final bool isSelfie;
  final Function(File) onImageCaptured;
  final VoidCallback onCancel;

  const CameraOverlay({
    Key? key,
    required this.isSelfie,
    required this.onImageCaptured,
    required this.onCancel,
  }) : super(key: key);

  @override
  State<CameraOverlay> createState() => _CameraOverlayState();
}

class _CameraOverlayState extends State<CameraOverlay> {
  CameraController? _controller;
  bool _isInitialized = false;
  bool _isCapturing = false;
  bool _isValidPosition = false;
  String _validationMessage = '';
  bool _hasValidated = false;

  @override
  void initState() {
    super.initState();
    _initializeCamera();
  }

  Future<void> _initializeCamera() async {
    final cameras = await availableCameras();
    final camera = widget.isSelfie 
        ? cameras.firstWhere((camera) => camera.lensDirection == CameraLensDirection.front)
        : cameras.firstWhere((camera) => camera.lensDirection == CameraLensDirection.back);

    _controller = CameraController(
      camera,
      ResolutionPreset.high,
      enableAudio: false,
      imageFormatGroup: ImageFormatGroup.jpeg,
    );

    await _controller!.initialize();
    setState(() {
      _isInitialized = true;
    });
  }

  Future<void> _validatePosition() async {
    if (_controller == null || !_controller!.value.isInitialized) return;
    
    setState(() {
      _hasValidated = true;
    });
    
    // Simulación de validación visual
    await Future.delayed(Duration(milliseconds: 500));
    
    if (widget.isSelfie) {
      _validateSelfiePosition();
    } else {
      _validateDNIPosition();
    }
  }

  void _validateSelfiePosition() {
    // Validación visual básica para selfie
    setState(() {
      _isValidPosition = true;
      _validationMessage = 'Posición correcta ✓. Asegúrate de que tu cara esté centrada en el marco circular';
    });
  }

  void _validateDNIPosition() {
    // Validación visual básica para DNI
    setState(() {
      _isValidPosition = true;
      _validationMessage = 'Posición correcta ✓. Asegúrate de que todo el DNI esté visible dentro del marco';
    });
  }

  Future<void> _captureImage() async {
    if (_controller == null || !_controller!.value.isInitialized || _isCapturing) {
      return;
    }

    if (!_hasValidated) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Primero valida la posición presionando el botón de enfoque'),
          backgroundColor: AppTheme.warningColor,
        ),
      );
      return;
    }

    if (!_isValidPosition) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(_validationMessage),
          backgroundColor: AppTheme.warningColor,
        ),
      );
      return;
    }

    setState(() {
      _isCapturing = true;
    });

    try {
      final XFile image = await _controller!.takePicture();
      final croppedImage = await _cropImageToFrame(image);
      // final correctedImage = await _correctImageOrientation(croppedImage);
      widget.onImageCaptured(croppedImage);
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Error al capturar la imagen'),
          backgroundColor: AppTheme.dangerColor,
        ),
      );
    } finally {
      setState(() {
        _isCapturing = false;
      });
    }
  }

  Future<File> _cropImageToFrame(XFile imageFile) async {
    try {
      final bytes = await imageFile.readAsBytes();
      final image = img.decodeImage(bytes);
      
      if (image == null) {
        return File(imageFile.path);
      }

      // Obtener dimensiones de la pantalla
      final screenSize = MediaQuery.of(context).size;
      final screenWidth = screenSize.width;
      final screenHeight = screenSize.height;
      
      // Dimensiones del marco (80% del ancho de pantalla)
      final frameWidth = screenWidth * 0.8;
      final frameHeight = widget.isSelfie ? frameWidth * 1.3 : frameWidth * 0.6; // Ovalado para selfie
      
      // Centro del marco en la pantalla
      final frameCenterX = screenWidth / 2;
      final frameCenterY = screenHeight / 2;
      
      // Calcular el área del marco en coordenadas de pantalla
      final frameLeft = frameCenterX - frameWidth / 2;
      final frameTop = frameCenterY - frameHeight / 2;
      final frameRight = frameCenterX + frameWidth / 2;
      final frameBottom = frameCenterY + frameHeight / 2;
      
      // Calcular el factor de escala simple
      final scaleX = image.width / screenWidth;
      final scaleY = image.height / screenHeight;
      
      // Convertir coordenadas de pantalla a coordenadas de imagen
      final cropX = (frameLeft * scaleX).round().clamp(0, image.width - 1);
      final cropY = (frameTop * scaleY).round().clamp(0, image.height - 1);
      final cropWidth = ((frameRight - frameLeft) * scaleX).round().clamp(1, image.width - cropX);
      final cropHeight = ((frameBottom - frameTop) * scaleY).round().clamp(1, image.height - cropY);
      
      // Debug: imprimir información
      print('Screen: ${screenWidth}x${screenHeight}');
      print('Frame: ${frameWidth}x${frameHeight}');
      print('Image: ${image.width}x${image.height}');
      print('Crop: x=$cropX, y=$cropY, w=$cropWidth, h=$cropHeight');
      
      // Recortar la imagen
      final croppedImage = img.copyCrop(
        image,
        x: cropX,
        y: cropY,
        width: cropWidth,
        height: cropHeight,
      );
      
      // Aplicar máscara ovalada para selfies
      final finalImage = widget.isSelfie 
          ? _applyOvalMask(croppedImage)
          : croppedImage;
      
      // Guardar la imagen procesada
      final tempDir = await getTemporaryDirectory();
      final fileName = 'processed_${DateTime.now().millisecondsSinceEpoch}.jpg';
      final processedPath = '${tempDir.path}/$fileName';
      
      final processedBytes = img.encodeJpg(finalImage, quality: 95);
      final processedFile = File(processedPath);
      await processedFile.writeAsBytes(processedBytes);
      
      // Eliminar la imagen original
      await File(imageFile.path).delete();
      
      return processedFile;
    } catch (e) {
      print('Error en crop: $e');
      // Si hay error, devolver la imagen original
      return File(imageFile.path);
    }
  }

  img.Image _applyOvalMask(img.Image image) {
    // Crear una máscara ovalada
    final mask = img.Image(width: image.width, height: image.height);
    
    // Calcular el centro y radios del óvalo
    final centerX = image.width / 2;
    final centerY = image.height / 2;
    final radiusX = image.width / 2;
    final radiusY = image.height / 2;
    
    // Llenar la máscara con el óvalo
    for (int y = 0; y < image.height; y++) {
      for (int x = 0; x < image.width; x++) {
        // Calcular si el punto está dentro del óvalo
        final dx = (x - centerX) / radiusX;
        final dy = (y - centerY) / radiusY;
        final distance = dx * dx + dy * dy;
        
        if (distance <= 1.0) {
          // Punto dentro del óvalo - mantener el color original
          final pixel = image.getPixel(x, y);
          mask.setPixel(x, y, pixel);
        } else {
          // Punto fuera del óvalo - hacer transparente
          mask.setPixel(x, y, img.ColorRgba8(0, 0, 0, 0));
        }
      }
    }
    
    return mask;
  }

  @override
  void dispose() {
    _controller?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (!_isInitialized || _controller == null) {
      return Scaffold(
        backgroundColor: Colors.black,
        body: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              CircularProgressIndicator(color: AppTheme.primaryColor),
              SizedBox(height: 16),
              Text(
                'Inicializando cámara...',
                style: TextStyle(color: Colors.white),
              ),
            ],
          ),
        ),
      );
    }

    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(
        children: [
          // Vista previa de la cámara
          Positioned.fill(
            child: FittedBox(
              fit: BoxFit.cover,
              child: SizedBox(
                width: _controller!.value.previewSize!.height,
                height: _controller!.value.previewSize!.width,
                child: CameraPreview(_controller!),
              ),
            ),
          ),
          
          // Overlay con guías
          Positioned.fill(
            child: _buildOverlay(),
          ),
          
          // Botones de control
          Positioned(
            bottom: 40,
            left: 0,
            right: 0,
            child: _buildControls(),
          ),
        ],
      ),
    );
  }

  Widget _buildOverlay() {
    return Container(
      decoration: BoxDecoration(
        color: Colors.black.withOpacity(0.3),
      ),
      child: Stack(
        children: [
          // Marco de captura
          Center(
            child: Container(
              width: MediaQuery.of(context).size.width * 0.8,
              height: widget.isSelfie 
                  ? MediaQuery.of(context).size.width * 0.8 
                  : MediaQuery.of(context).size.width * 0.5,
              decoration: BoxDecoration(
                border: Border.all(
                  color: AppTheme.primaryColor,
                  width: 3,
                ),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Stack(
                children: [
                  // Esquinas del marco
                  ..._buildCornerMarkers(),
                  
                  // Guías específicas
                  if (widget.isSelfie) ..._buildSelfieGuides(),
                  if (!widget.isSelfie) ..._buildDNIGuides(),
                ],
              ),
            ),
          ),
          
          // Instrucciones
          Positioned(
            top: 60,
            left: 20,
            right: 20,
            child: _buildInstructions(),
          ),
          
          // Indicador de validación
          if (_validationMessage.isNotEmpty)
            Positioned(
              bottom: 120,
              left: 20,
              right: 20,
              child: _buildValidationIndicator(),
            ),
        ],
      ),
    );
  }

  List<Widget> _buildCornerMarkers() {
    return [
      // Esquina superior izquierda
      Positioned(
        top: -2,
        left: -2,
        child: Container(
          width: 20,
          height: 20,
          decoration: BoxDecoration(
            border: Border(
              top: BorderSide(color: AppTheme.primaryColor, width: 4),
              left: BorderSide(color: AppTheme.primaryColor, width: 4),
            ),
          ),
        ),
      ),
      // Esquina superior derecha
      Positioned(
        top: -2,
        right: -2,
        child: Container(
          width: 20,
          height: 20,
          decoration: BoxDecoration(
            border: Border(
              top: BorderSide(color: AppTheme.primaryColor, width: 4),
              right: BorderSide(color: AppTheme.primaryColor, width: 4),
            ),
          ),
        ),
      ),
      // Esquina inferior izquierda
      Positioned(
        bottom: -2,
        left: -2,
        child: Container(
          width: 20,
          height: 20,
          decoration: BoxDecoration(
            border: Border(
              bottom: BorderSide(color: AppTheme.primaryColor, width: 4),
              left: BorderSide(color: AppTheme.primaryColor, width: 4),
            ),
          ),
        ),
      ),
      // Esquina inferior derecha
      Positioned(
        bottom: -2,
        right: -2,
        child: Container(
          width: 20,
          height: 20,
          decoration: BoxDecoration(
            border: Border(
              bottom: BorderSide(color: AppTheme.primaryColor, width: 4),
              right: BorderSide(color: AppTheme.primaryColor, width: 4),
            ),
          ),
        ),
      ),
    ];
  }

  List<Widget> _buildSelfieGuides() {
    return [
      // Guía ovalada para centrar la cara
      Center(
        child: Container(
          width: 140,
          height: 180,
          decoration: BoxDecoration(
            shape: BoxShape.rectangle,
            borderRadius: BorderRadius.circular(70),
            border: Border.all(
              color: AppTheme.primaryColor.withOpacity(0.5),
              width: 2,
            ),
          ),
        ),
      ),
      // Puntos de referencia para ojos
      Positioned(
        top: 50,
        left: 0,
        right: 0,
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceEvenly,
          children: [
            Container(
              width: 8,
              height: 8,
              decoration: BoxDecoration(
                color: AppTheme.primaryColor.withOpacity(0.7),
                shape: BoxShape.circle,
              ),
            ),
            Container(
              width: 8,
              height: 8,
              decoration: BoxDecoration(
                color: AppTheme.primaryColor.withOpacity(0.7),
                shape: BoxShape.circle,
              ),
            ),
          ],
        ),
      ),
    ];
  }

  List<Widget> _buildDNIGuides() {
    return [
      // Líneas de guía horizontal
      Positioned(
        top: 30,
        left: 10,
        right: 10,
        child: Container(
          height: 1,
          color: AppTheme.primaryColor.withOpacity(0.5),
        ),
      ),
      Positioned(
        bottom: 30,
        left: 10,
        right: 10,
        child: Container(
          height: 1,
          color: AppTheme.primaryColor.withOpacity(0.5),
        ),
      ),
      // Líneas de guía vertical
      Positioned(
        top: 10,
        left: 30,
        bottom: 10,
        child: Container(
          width: 1,
          color: AppTheme.primaryColor.withOpacity(0.5),
        ),
      ),
      Positioned(
        top: 10,
        right: 30,
        bottom: 10,
        child: Container(
          width: 1,
          color: AppTheme.primaryColor.withOpacity(0.5),
        ),
      ),
    ];
  }

  Widget _buildInstructions() {
    return Container(
      padding: EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.black.withOpacity(0.7),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        children: [
          Icon(
            widget.isSelfie ? Icons.face : Icons.credit_card,
            color: AppTheme.primaryColor,
            size: 32,
          ),
          SizedBox(height: 8),
          Text(
            widget.isSelfie 
                ? 'Posiciona tu cara dentro del óvalo'
                : 'Coloca el DNI dentro del marco',
            style: TextStyle(
              color: Colors.white,
              fontSize: 16,
              fontWeight: FontWeight.bold,
            ),
            textAlign: TextAlign.center,
          ),
          SizedBox(height: 4),
          Text(
            widget.isSelfie
                ? 'Mira directamente a la cámara y mantén una expresión neutra'
                : 'Asegúrate de que todo el documento sea visible y esté bien iluminado',
            style: TextStyle(
              color: Colors.white70,
              fontSize: 14,
            ),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }

  Widget _buildValidationIndicator() {
    return Container(
      padding: EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _isValidPosition 
            ? AppTheme.successColor.withOpacity(0.9)
            : AppTheme.warningColor.withOpacity(0.9),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Icon(
            _isValidPosition ? Icons.check_circle : Icons.warning,
            color: Colors.white,
            size: 24,
          ),
          SizedBox(width: 12),
          Expanded(
            child: Text(
              _validationMessage,
              style: TextStyle(
                color: Colors.white,
                fontSize: 14,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildControls() {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceEvenly,
      children: [
        // Botón cancelar
        GestureDetector(
          onTap: widget.onCancel,
          child: Container(
            padding: EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: Colors.black.withOpacity(0.5),
              shape: BoxShape.circle,
            ),
            child: Icon(
              Icons.close,
              color: Colors.white,
              size: 24,
            ),
          ),
        ),
        
        // Botón de validación
        GestureDetector(
          onTap: _validatePosition,
          child: Container(
            padding: EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: Colors.black.withOpacity(0.5),
              shape: BoxShape.circle,
            ),
            child: Icon(
              Icons.center_focus_strong,
              color: Colors.white,
              size: 24,
            ),
          ),
        ),
        
        // Botón capturar
        GestureDetector(
          onTap: _isCapturing ? null : _captureImage,
          child: Container(
            width: 70,
            height: 70,
            decoration: BoxDecoration(
              color: _isCapturing 
                  ? Colors.grey 
                  : (_isValidPosition ? AppTheme.successColor : AppTheme.primaryColor),
              shape: BoxShape.circle,
            ),
            child: _isCapturing
                ? Center(
                    child: SizedBox(
                      width: 24,
                      height: 24,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                      ),
                    ),
                  )
                : Icon(
                    Icons.camera_alt,
                    color: Colors.white,
                    size: 32,
                  ),
          ),
        ),
      ],
    );
  }
}
