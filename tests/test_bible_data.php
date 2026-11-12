<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Planner.php';
require_once __DIR__ . '/../src/BibleData.php';

use Ebd\BibleData;
use Ebd\Planner;

function bibleSame(mixed $expected, mixed $actual, string $label): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($label . ' esperado=' . var_export($expected, true) . ' obtido=' . var_export($actual, true));
    }
}
function bibleRejects(callable $action, string $fragment): void
{
    try {
        $action();
    } catch (InvalidArgumentException | RuntimeException $error) {
        if (!str_contains($error->getMessage(), $fragment)) {
            throw new RuntimeException('Erro inesperado: ' . $error->getMessage());
        }
        return;
    }
    throw new RuntimeException('Uma entrada inválida foi aceita: ' . $fragment);
}
function bibleRunCli(array $args): array
{
    $command = array_merge([PHP_BINARY, __DIR__ . '/../planner.php'], $args);
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Falha ao iniciar a CLI PHP');
    }
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), $out, $err];
}
function bibleRemoveDirectory(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $entry = $path . '/' . $item;
        if (is_dir($entry) && !is_link($entry)) {
            bibleRemoveDirectory($entry);
        } else {
            unlink($entry);
        }
    }
    rmdir($path);
}

$root = sys_get_temp_dir() . '/ebd-bible-tests-' . bin2hex(random_bytes(6));
mkdir($root . '/bolls/TEST/50', 0700, true);
mkdir($root . '/bolls/TEST/43', 0700, true);
mkdir($root . '/bolls/TEST/18', 0700, true);
file_put_contents($root . '/bolls/TEST/50/4.json', json_encode([
    ['verse' => 4, 'text' => 'Texto sintético da aula de teste.'],
    ['verse' => 5, 'text' => 'Outro versículo de teste.'],
    ['verse' => 6, 'text' => '<script>alert("x")</script>'],
], JSON_THROW_ON_ERROR));
file_put_contents($root . '/bolls/TEST/43/3.json', json_encode([
    ['verse' => 16, 'text' => 'Exemplo autoral do teste.'],
], JSON_THROW_ON_ERROR));
file_put_contents($root . '/bolls/TEST/18/1.json', json_encode([
    ['verse' => 1, 'text' => 'Exemplo de livro com acento.'],
], JSON_THROW_ON_ERROR));

$lesson = [
    'titulo' => 'Aula de integração',
    'duracao' => 30,
    'blocos' => [
        ['titulo' => 'Leitura', 'tipo' => 'estudo', 'minutos' => 20, 'referencia' => 'Fp 4:4-5'],
        ['titulo' => 'Conclusão', 'tipo' => 'conclusao', 'minutos' => 10],
    ],
];
$lessonFile = $root . '/aula.json';
file_put_contents($lessonFile, json_encode($lesson, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

$tests = [
    'lê os versículos a partir da estrutura Bolls importada' => static function () use ($root): void {
        $result = BibleData::lookup('Fp 4:4-5', $root, 'TEST');
        bibleSame(50, $result['livro_numero'], 'Filipenses');
        bibleSame(4, $result['capitulo'], 'capítulo');
        bibleSame([4, 5], array_column($result['versiculos'], 'numero'), 'versículos');
        bibleSame('Texto sintético da aula de teste.', $result['versiculos'][0]['texto'], 'texto local');
    },
    'entende livro com acento e abreviação de João' => static function () use ($root): void {
        bibleSame(43, BibleData::lookup('João 3:16', $root, 'TEST')['livro_numero'], 'João');
        bibleSame(43, BibleData::lookup('Jo 3:16', $root, 'TEST')['livro_numero'], 'Jo abreviado');
        bibleSame(18, BibleData::lookup('Jó 1:1', $root, 'TEST')['livro_numero'], 'Jó');
    },
    'permite consultar o capítulo e mantém a ordem' => static function () use ($root): void {
        $result = BibleData::lookup('Filipenses 4', $root, 'TEST');
        bibleSame([4, 5, 6], array_column($result['versiculos'], 'numero'), 'capítulo completo');
    },
    'rejeita capítulos inexistentes e intervalos incompletos' => static function () use ($root): void {
        bibleRejects(static fn () => BibleData::lookup('Fp 9:1', $root, 'TEST'), 'Capítulo não disponível');
        bibleRejects(static fn () => BibleData::lookup('Fp 4:5-9', $root, 'TEST'), 'versículos ausentes');
        bibleRejects(static fn () => BibleData::lookup('Fp 4:6-4', $root, 'TEST'), 'Intervalo');
    },
    'rejeita referência inválida e proteção de caminho' => static function () use ($root): void {
        bibleRejects(static fn () => BibleData::lookup('Livro 1:1', $root, 'TEST'), 'Livro bíblico');
        bibleRejects(static fn () => BibleData::lookup('Fp 4:', $root, 'TEST'), 'Use referência');
        bibleRejects(static fn () => BibleData::lookup('Fp 4:4', $root, '../TEST'), 'tradução');
        bibleRejects(static fn () => BibleData::lookup('Fp 4:4', $root . '/nope', 'TEST'), 'Diretório');
    },
    'detecta JSON inválido e versículo duplicado' => static function () use ($root): void {
        file_put_contents($root . '/bolls/TEST/50/1.json', '{bad');
        bibleRejects(static fn () => BibleData::lookup('Fp 1:1', $root, 'TEST'), 'JSON inválido');
        file_put_contents($root . '/bolls/TEST/50/1.json', '[{"verse":1,"text":"a"},{"verse":1,"text":"b"}]');
        bibleRejects(static fn () => BibleData::lookup('Fp 1:1', $root, 'TEST'), 'duplicado');
    },
    'rejeita duplicatas fora da faixa solicitada' => static function () use ($root): void {
        // A seleção de um verso não deve ocultar corrupção no restante do capítulo.
        file_put_contents($root . '/bolls/TEST/50/2.json', json_encode([
            ['verse' => 1, 'text' => 'Versículo único solicitado.'],
            ['verse' => 2, 'text' => 'Duplicata A.'],
            ['verse' => 2, 'text' => 'Duplicata B.'],
        ], JSON_THROW_ON_ERROR));
        bibleRejects(static fn () => BibleData::lookup('Fp 2:1', $root, 'TEST'), 'duplicado');
    },
    'associa referências ao plano validado' => static function () use ($root, $lesson): void {
        $plan = Planner::build($lesson);
        $passages = BibleData::forLesson($plan, $root, 'TEST');
        bibleSame(1, count($passages), 'uma referência única');
        bibleSame('Fp 4:4-5', $passages[0]['referencia'], 'referência da aula');
    },
    'escapa conteúdo da base em material Markdown' => static function () use ($root): void {
        $passages = [BibleData::lookup('Fp 4:6', $root, 'TEST')];
        $markdown = BibleData::markdown($passages);
        if (str_contains($markdown, '<script>') || !str_contains($markdown, '&lt;script&gt;')) {
            throw new RuntimeException('O Markdown deve escapar HTML vindo de dados externos');
        }
    },
    'neutraliza links e formatação Markdown na base externa' => static function () use ($root): void {
        file_put_contents($root . '/bolls/TEST/50/3.json', json_encode([
            ['verse' => 1, 'text' => 'Texto [clique](https://exemplo.invalid) **formatação** e _ênfase_.'],
        ], JSON_THROW_ON_ERROR));
        $markdown = BibleData::markdown([BibleData::lookup('Fp 3:1', $root, 'TEST')]);
        if (str_contains($markdown, '[clique](') || str_contains($markdown, '**formatação**')
            || !str_contains($markdown, '\[clique\]') || !str_contains($markdown, '\*')
            || !str_contains($markdown, '\_ênfase\_')) {
            throw new RuntimeException('Markdown vindo da base externa não foi neutralizado');
        }
    },
    'CLI combina planejamento e consulta no JSON' => static function () use ($root, $lessonFile): void {
        [$code, $out, $err] = bibleRunCli([$lessonFile, '--format=json', '--biblia-dir=' . $root, '--traducao=TEST']);
        bibleSame(0, $code, 'CLI deve concluir: ' . $err);
        $json = json_decode($out, true, 32, JSON_THROW_ON_ERROR);
        bibleSame('Aula de integração', $json['aula']['titulo'], 'aula preservada');
        bibleSame(2, count($json['textos_biblicos'][0]['versiculos']), 'versos enriquecidos');
    },
    'CLI adiciona leituras ao roteiro e ao resumo do aluno' => static function () use ($root, $lessonFile): void {
        foreach (['markdown', 'student'] as $format) {
            [$code, $out, $err] = bibleRunCli([$lessonFile, '--format=' . $format, '--biblia-dir=' . $root, '--traducao=TEST']);
            bibleSame(0, $code, 'CLI ' . $format . ': ' . $err);
            if (!str_contains($out, 'Leituras bíblicas') || !str_contains($out, 'Texto sintético da aula de teste.')) {
                throw new RuntimeException('Leitura não incluída em ' . $format);
            }
        }
    },
    'CLI não permite base bíblica em série ou CSV' => static function () use ($root, $lessonFile): void {
        foreach (['--series', '--format=csv'] as $option) {
            [$code, , $err] = bibleRunCli([$lessonFile, $option, '--biblia-dir=' . $root]);
            bibleSame(2, $code, 'Rejeitar opção incompatível');
            if (!str_contains($err, 'Consulta bíblica')) {
                throw new RuntimeException('Mensagem de incompatibilidade ausente');
            }
        }
    },
];

try {
    $failed = 0;
    foreach ($tests as $name => $test) {
        try {
            $test();
            echo "[OK] $name\n";
        } catch (Throwable $error) {
            $failed++;
            fwrite(STDERR, "[FALHA] $name: " . $error->getMessage() . "\n");
        }
    }
    echo count($tests) - $failed . '/' . count($tests) . " testes de integração passaram\n";
    if ($failed !== 0) {
        exit(1);
    }
} finally {
    bibleRemoveDirectory($root);
}
