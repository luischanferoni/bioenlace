# Configuración para Extracción de Datos del DNI Argentino

## 🛠️ **Dependencias Necesarias**

### **1. ZBar (para códigos PDF417)**
```bash
# En WSL
sudo apt update
sudo apt install zbar-tools

# Verificar instalación
zbarimg --version
```

### **2. Tesseract OCR**
```bash
# En WSL
sudo apt install tesseract-ocr tesseract-ocr-spa

# Verificar instalación
tesseract --version
```

### **3. OpenCV (opcional, para preprocesamiento)**
```bash
# En WSL
sudo apt install python3-opencv

# O instalar con pip
pip3 install opencv-python
```

### **4. Python (para scripts de OpenCV)**
```bash
# En WSL
sudo apt install python3 python3-pip
```

## 📋 **Instalación en Windows (XAMPP)**

### **1. ZBar para Windows**
- Descargar desde: https://github.com/mchehab/zbar/releases
- Instalar y agregar al PATH

### **2. Tesseract para Windows**
- Descargar desde: https://github.com/UB-Mannheim/tesseract/wiki
- Instalar y agregar al PATH

### **3. OpenCV para Windows**
```bash
pip install opencv-python
```

## 🔧 **Configuración del Sistema**

### **1. Variables de Entorno**
Agregar al PATH de Windows:
- `C:\Program Files\Tesseract-OCR`
- `C:\Program Files\ZBar\bin`

### **2. Verificar Instalación**
```bash
# En PowerShell
zbarimg --version
tesseract --version
```

## 📊 **Métodos de Extracción Implementados**

### **1. PDF417 (ZBar) - MÁS PRECISO**
- ✅ Lee códigos de barras 2D del DNI
- ✅ Extrae datos estructurados
- ✅ Formato: `@dni@apellido@nombre@sexo@...`

### **2. OCR (Tesseract) - FALLBACK**
- ✅ Lee texto de la imagen
- ✅ Usa patrones regex para extraer datos
- ✅ Funciona cuando no hay código PDF417

### **3. OpenCV + OCR - MEJORADO**
- ✅ Preprocesa imagen para mejorar OCR
- ✅ Aplica filtros de mejora
- ✅ Redimensiona si es necesario

## 🎯 **Estrategia de Extracción**

El sistema intenta en este orden:

1. **ZBar PDF417** (más preciso)
2. **Tesseract OCR** (fallback)
3. **OpenCV + OCR** (mejorado)

## 📝 **Formato de Respuesta**

```json
{
    "success": true,
    "message": "Usuario registrado",
    "user_id": "user_123",
    "dni_data": {
        "dni": "12345678",
        "apellido": "GARCIA",
        "nombre": "JUAN CARLOS",
        "sexo": "M",
        "nacionalidad": "ARG",
        "fecha_nacimiento": "01/01/1990",
        "fecha_emision": "01/01/2020",
        "fecha_vencimiento": "01/01/2030",
        "ejemplar": "A",
        "method": "pdf417"
    }
}
```

## 🚀 **Uso del API**

### **Endpoint**
```
POST /api/v1/signup
```

### **Parámetros**
- `dni_photo`: Archivo de imagen del DNI
- `selfie_photo`: Archivo de selfie del usuario

### **Respuesta Exitosa**
```json
{
    "success": true,
    "message": "Usuario registrado",
    "user_id": "user_123",
    "dni_data": {
        "dni": "12345678",
        "apellido": "GARCIA",
        "nombre": "JUAN CARLOS",
        "method": "pdf417"
    }
}
```

### **Respuesta de Error**
```json
{
    "success": false,
    "message": "No se pudo extraer información del DNI"
}
```

## 🔍 **Debugging**

### **1. Verificar Logs**
```php
// En Yii2
Yii::info("ZBar output: " . $output);
Yii::error("Error en ZBar: " . $e->getMessage());
```

### **2. Probar Comandos Manualmente**
```bash
# En WSL
zbarimg -q --raw /mnt/d/ruta/a/imagen.jpg
tesseract /mnt/d/ruta/a/imagen.jpg stdout -l spa
```

### **3. Verificar Rutas**
```php
// Verificar que las rutas se conviertan correctamente
$wslPath = str_replace('D:', '/mnt/d', $imagePath);
```

## 📈 **Mejoras Futuras**

1. **Machine Learning**: Usar modelos entrenados específicamente para DNIs
2. **API Externa**: Integrar con servicios como Google Vision API
3. **Validación**: Verificar datos contra bases oficiales
4. **Cache**: Almacenar resultados para evitar reprocesamiento

## 🛡️ **Seguridad**

- ✅ Validar formato de archivos
- ✅ Limitar tamaño de archivos
- ✅ Sanitizar datos extraídos
- ✅ No almacenar imágenes permanentemente
- ✅ Usar HTTPS para transmisión
