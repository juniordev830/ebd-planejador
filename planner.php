<?php
declare(strict_types=1);

require_once __DIR__ . '/src/Planner.php';
require_once __DIR__ . '/src/SeriesInsights.php';
require_once __DIR__ . '/src/BibleData.php';

use Ebd\Planner;
use Ebd\SeriesInsights;
use Ebd\BibleData;

/**
 * Usage:
 *   php planner.php lesson.json [--format=json|markdown|student|csv]
 *   php planner.php course.json --series
 *
 * All exports go to stdout. No files are overwritten by the application.
 */
function plannerCli(array $arguments): int
{
    $usage = "Uso: php planner.php arquivo.json [--format=json|markdown|student|csv] [--series] [--insights] [--biblia-dir=CAMINHO] [--traducao=ACF11]\n";
    if (count($arguments) < 2 || in_array($arguments[1], ['--help', '-h'], true)) {
        fwrite(STDERR, $usage);
        return count($arguments) > 1 ? 0 : 2;
    }

    $file = $arguments[1];
    $format = 'json';
    $series = false;
    $insights = false;
    $bibleDirectory = null;
    $translation = 'ACF11';
    $translationProvided = false;
    foreach (array_slice($arguments, 2) as $option) {
        if ($option === '--insights') {
            $insights = true;
        } elseif ($option === '--series') {
            $series = true;
        } elseif (str_starts_with($option, '--biblia-dir=')) {
            $bibleDirectory = substr($option, strlen('--biblia-dir='));
        } elseif (str_starts_with($option, '--traducao=')) {
            $translation = substr($option, strlen('--traducao='));
            $translationProvided = true;
        } elseif (str_starts_with($option, '--format=')) {
            $format = substr($option, 9);
        } else {
            fwrite(STDERR, "Opção desconhecida: $option\n" . $usage);
            return 2;
        }
    }

    if (!in_array($format, ['json', 'markdown', 'student', 'csv'], true)) {
        fwrite(STDERR, "Formato inválido: $format\n");
        return 2;
    }
    if ($insights && !$series) {
        fwrite(STDERR, "A opção --insights exige --series\n");
        return 2;
    }
    if (($bibleDirectory !== null && ($bibleDirectory === '' || $series || $format === 'csv'))
        || ($translationProvided && $bibleDirectory === null)) {
        fwrite(STDERR, "Consulta bíblica exige --biblia-dir e aceita apenas uma aula nos formatos json, markdown ou student\n");
        return 2;
    }
    if ($series && $format !== 'json') {
        fwrite(STDERR, "A opção --series só admite --format=json\n");
        return 2;
    }

    try {
        if (!is_file($file) || !is_readable($file)) {
            throw new RuntimeException('Arquivo não encontrado ou sem permissão de leitura');
        }
        $text = file_get_contents($file);
        if ($text === false) {
            throw new RuntimeException('Falha ao ler o arquivo');
        }
        $input = json_decode($text, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($input)) {
            throw new InvalidArgumentException('O JSON deve conter um objeto ou uma lista');
        }

        $plan = $insights ? SeriesInsights::summarize($input) :
            ($series ? Planner::series($input) : Planner::build($input));
        $passages = null;
        if ($bibleDirectory !== null) {
            $passages = BibleData::forLesson($plan, $bibleDirectory, $translation);
            $plan['textos_biblicos'] = $passages;
        }
        $output = match ($format) {
            'markdown' => Planner::markdown($plan),
            'student' => Planner::studentHandout($plan),
            'csv' => Planner::csv($plan),
            default => json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n",
        };
        if ($passages !== null && ($format === 'markdown' || $format === 'student')) {
            $output .= BibleData::markdown($passages);
        }
        fwrite(STDOUT, $output);
        return 0;
    } catch (JsonException | InvalidArgumentException | RuntimeException $error) {
        fwrite(STDERR, "Erro: " . $error->getMessage() . "\n");
        return 2;
    }
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    exit(plannerCli($argv));
}
