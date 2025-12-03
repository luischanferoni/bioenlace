# Flujo de Datos del Índice 'count' en SymSpellCorrector

## Ubicación del Índice 'count'

El índice `count` se agrega en el método `lookup()` del `SymSpellCorrector`, específicamente en las líneas 378-387:

```php
private function lookup($word)
{
    $suggestions = [];
    
    // Buscar en el diccionario
    foreach ($this->dictionary as $dictWord => $entry) {
        $distance = $this->levenshteinDistance($word, $dictWord);
        
        if ($distance <= $this->maxEditDistance) {
            $suggestions[] = [
                'term' => $entry['expansion'],
                'distance' => $distance,
                'frequency' => $entry['frequency'],
                'count' => $entry['frequency'], // ← AQUÍ SE AGREGA EL ÍNDICE 'count'
                'type' => $entry['type'] ?? 'unknown',
                'category' => $entry['category'] ?? 'unknown',
                'original_length' => strlen($word),
                'term_length' => strlen($entry['expansion'])
            ];
        }
    }
    
    return $suggestions;
}
```

## Flujo Completo de Datos

```
1. Diccionario Médico (BD)
   ↓
2. loadMedicalDictionary()
   ↓
3. $this->dictionary['palabra'] = [
       'frequency' => 150,
       'expansion' => 'palabra_expandida',
       'type' => 'termino_medico',
       'category' => 'oftalmologia'
   ]
   ↓
4. lookup($word) - Busca sugerencias
   ↓
5. Crea array de sugerencias con:
   - 'frequency' = $entry['frequency'] (150)
   - 'count' = $entry['frequency'] (150) ← ALIAS
   ↓
6. correct($word) - Selecciona mejor sugerencia
   ↓
7. calculateConfidence() - Calcula confianza usando 'frequency'
   ↓
8. correctText() - Verifica si requiere validación usando 'count'
```

## Uso del Índice 'count'

### En calculateConfidence():
```php
$frequencyBonus = min(0.2, $suggestion['frequency'] / 1000) * 0.2;
```

### En correctText() - Detección de cambios problemáticos:
```php
$esProblematico = $result['confidence'] < 0.7 || 
                 (isset($result['metadata']['distance']) && $result['metadata']['distance'] > 2) ||
                 (isset($result['metadata']['count']) && $result['metadata']['count'] < 50) || // ← AQUÍ
                 strlen($result['original']) < 4;
```

### En correct() - Logging detallado:
```php
$cambiosDetallados[] = ($i + 1) . '. ' . $cleanWord . ' → ' . $sug['term'] . 
                      ' (distancia: ' . $sug['distance'] . ', frecuencia: ' . $sug['count'] . ')';
```

## Propósito del Índice 'count'

1. **Compatibilidad**: Alias de 'frequency' para mantener consistencia
2. **Validación**: Se usa para detectar cambios de baja frecuencia (< 50)
3. **Logging**: Se muestra en los logs detallados de correcciones
4. **Debugging**: Facilita la identificación de términos poco frecuentes

## Ejemplo Práctico

```php
// Entrada del diccionario
$entry = [
    'frequency' => 25,  // Baja frecuencia
    'expansion' => 'catarata',
    'type' => 'termino_medico'
];

// Salida del lookup()
$suggestion = [
    'term' => 'catarata',
    'distance' => 1,
    'frequency' => 25,
    'count' => 25,  // ← Mismo valor que frequency
    'type' => 'termino_medico'
];

// En correctText() se detecta como problemático:
if ($result['metadata']['count'] < 50) { // 25 < 50 = true
    $esProblematico = true; // ← Requiere validación
}
```
