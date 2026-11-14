<?php

/**
 * Genera la sección «Especialidades clínicas» del sitio institucional desde
 * {@see \common\components\Domain\Clinical\Specialty\Domain\Catalog\ClinicalSpecialtyCatalog}.
 *
 * Uso:
 *   php tools/generate-institutional-specialties.php           # reescribe el bloque
 *   php tools/generate-institutional-specialties.php --check   # exit 1 si está desfasado
 *
 * El bloque vive entre los marcadores `BEGIN:especialidades` y `END:especialidades` de
 * `institucional/index.html`; todo lo que está fuera se respeta tal cual.
 *
 * Con `--check` el script no escribe: compara y falla. Ese es el modo que corre en el
 * hook de pre-commit y en el test de suite, así no se puede publicar el sitio con la
 * lista de especialidades desactualizada.
 */

declare(strict_types=1);

use common\components\Domain\Clinical\Specialty\Domain\Catalog\ClinicalSpecialtyCatalog;

$root = dirname(__DIR__);
require $root . '/web/vendor/autoload.php';
require $root . '/web/vendor/yiisoft/yii2/Yii.php';
Yii::setAlias('@common', $root . '/web/common');

const TARGET_FILE = 'institucional/index.html';
const BEGIN_MARKER = '<!-- BEGIN:especialidades -->';
const END_MARKER = '<!-- END:especialidades -->';

/**
 * Lista en lenguaje natural: «a, b y c».
 *
 * @param list<string> $items
 */
function humanList(array $items): string
{
    if ($items === []) {
        return '';
    }
    if (count($items) === 1) {
        return $items[0];
    }
    $last = array_pop($items);

    return implode(', ', $items) . ' y ' . $last;
}

/**
 * @param list<string> $labels
 */
function renderChips(array $labels, int $depth): string
{
    if ($labels === []) {
        return '';
    }
    $pad = str_repeat('    ', $depth);
    $out = $pad . '<ul class="especialidad-card__fields">' . "\n";
    foreach ($labels as $label) {
        $out .= $pad . '    <li class="especialidad-chip">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</li>' . "\n";
    }
    $out .= $pad . '</ul>' . "\n";

    return $out;
}

/**
 * Chips de una especialidad: los valores que la captura interpreta hoy.
 *
 * @param array{code: string, status: string} $definition
 * @return list<string>
 */
function fieldLabels(array $definition): array
{
    return array_values(ClinicalSpecialtyCatalog::extractedFieldsFor($definition['code']));
}

function renderCard(array $definition, int $depth): string
{
    $pad = str_repeat('    ', $depth);
    $html = $pad . '<article class="especialidad-card">' . "\n";
    $html .= $pad . '    <h3 class="especialidad-card__title">'
        . htmlspecialchars($definition['label'], ENT_QUOTES, 'UTF-8') . '</h3>' . "\n";
    $html .= $pad . '    <p class="especialidad-card__copy">'
        . htmlspecialchars($definition['public_copy'], ENT_QUOTES, 'UTF-8') . '</p>' . "\n";

    $fields = fieldLabels($definition);
    $html .= renderChips($fields, $depth + 1);

    if ($definition['status'] === ClinicalSpecialtyCatalog::STATUS_STANDARD) {
        $standard = array_map(
            static fn (string $label): string => mb_strtolower($label, 'UTF-8'),
            ClinicalSpecialtyCatalog::standardSectionLabels()
        );
        $html .= $pad . '    <p class="especialidad-card__standard">Y el registro clínico completo: '
            . htmlspecialchars(humanList($standard), ENT_QUOTES, 'UTF-8') . '.</p>' . "\n";
    }

    $html .= $pad . '</article>' . "\n";

    return $html;
}

function renderSection(): string
{
    $all = ClinicalSpecialtyCatalog::all();
    $specific = array_filter($all, static fn (array $d): bool => $d['status'] === ClinicalSpecialtyCatalog::STATUS_SPECIFIC);
    $standard = array_filter($all, static fn (array $d): bool => $d['status'] === ClinicalSpecialtyCatalog::STATUS_STANDARD);

    $body = '';
    foreach (array_merge($specific, $standard) as $definition) {
        $body .= renderCard($definition, 3);
    }

    $total = count($specific) + count($standard);

    $html = '<section id="especialidades" class="especialidades">' . "\n";
    $html .= '    <div class="container">' . "\n";
    $html .= '        <div class="section-header reveal">' . "\n";
    $html .= '            <h2 class="section-title">Especialidades clínicas</h2>' . "\n";
    $html .= '            <p class="section-subtitle">Ofrecemos todas las especialidades: es cuestión de configuración, no de desarrollo. '
        . 'Esto es lo que Bioenlace interpreta y registra en ' . $total . ' de ellas, cada una con la interfaz clínica que le corresponde.</p>' . "\n";
    $html .= '        </div>' . "\n";
    $html .= '        <div class="especialidades-grid reveal">' . "\n";
    $html .= $body;
    $html .= '        </div>' . "\n";
    $html .= '        <p class="especialidades-note reveal">¿Tu especialidad no está en la lista? Se configura: el registro clínico, '
        . 'los campos que se interpretan y la interfaz se declaran por especialidad. <a href="#contacto">Hablemos</a>.</p>' . "\n";
    $html .= '    </div>' . "\n";
    $html .= '</section>';

    return $html;
}

$targetPath = $root . '/' . TARGET_FILE;
$source = file_get_contents($targetPath);
if ($source === false) {
    fwrite(STDERR, "No se pudo leer {$targetPath}\n");
    exit(2);
}

$begin = strpos($source, BEGIN_MARKER);
$end = strpos($source, END_MARKER);
if ($begin === false || $end === false || $end < $begin) {
    fwrite(STDERR, 'Faltan los marcadores ' . BEGIN_MARKER . ' / ' . END_MARKER . " en " . TARGET_FILE . "\n");
    exit(2);
}

$head = substr($source, 0, $begin + strlen(BEGIN_MARKER));
$tail = substr($source, $end);
$current = substr($source, $begin + strlen(BEGIN_MARKER), $end - $begin - strlen(BEGIN_MARKER));

$generated = "\n" . renderSection() . "\n    ";

$isCheck = in_array('--check', $argv, true);

if ($isCheck) {
    if ($current !== $generated) {
        fwrite(STDERR, 'La sección de especialidades del sitio institucional está desfasada del catálogo.' . "\n");
        fwrite(STDERR, 'Corré: php tools/generate-institutional-specialties.php' . "\n");
        exit(1);
    }
    echo "Sección de especialidades sincronizada con el catálogo.\n";
    exit(0);
}

$updated = $head . $generated . $tail;
if ($updated === $source) {
    echo "Sin cambios: la sección ya estaba sincronizada.\n";
    exit(0);
}

if (file_put_contents($targetPath, $updated) === false) {
    fwrite(STDERR, "No se pudo escribir {$targetPath}\n");
    exit(2);
}

echo "Sección de especialidades actualizada en " . TARGET_FILE . ".\n";
exit(0);
