<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Planner.php';

use Ebd\Planner;

function lesson(array $changes = []): array
{
    return array_replace([
        'titulo' => 'Alegria em Filipenses',
        'turma' => 'Jovens',
        'data' => '2026-11-08',
        'inicio' => '09:30',
        'duracao' => 90,
        'objetivo' => 'Compreender a alegria cristã.',
        'blocos' => [
            [
                'titulo' => 'Abertura',
                'tipo' => 'introducao',
                'minutos' => 10,
                'referencia' => 'Fp 4:4',
                'materiais' => ['Quadro', 'Bíblia'],
            ],
            [
                'titulo' => 'Estudo bíblico',
                'tipo' => 'estudo',
                'minutos' => 60,
                'referencia' => 'Fp 3:7-9',
                'descricao' => 'Notas reservadas ao professor',
                'materiais' => ['Bíblia', 'Projetor'],
            ],
            [
                'titulo' => 'Aplicação',
                'tipo' => 'aplicacao',
                'minutos' => 15,
            ],
            [
                'titulo' => 'Conclusão',
                'tipo' => 'conclusao',
                'minutos' => 5,
            ],
        ],
    ], $changes);
}

function same(mixed $expected, mixed $actual, string $label = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            "$label esperado=" . var_export($expected, true) . ' obtido=' . var_export($actual, true)
        );
    }
}

function truth(bool $value, string $label): void
{
    if (!$value) {
        throw new RuntimeException($label);
    }
}

function rejects(callable $callback, string $part = ''): void
{
    try {
        $callback();
    } catch (InvalidArgumentException $error) {
        if ($part !== '' && !str_contains($error->getMessage(), $part)) {
            throw new RuntimeException('Mensagem inesperada: ' . $error->getMessage());
        }
        return;
    }
    throw new RuntimeException('Esperada InvalidArgumentException');
}

function runCli(array $args): array
{
    $command = array_merge([PHP_BINARY, __DIR__ . '/../planner.php'], $args);
    $process = proc_open(
        array_map('strval', $command),
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Não foi possível iniciar a CLI');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), $stdout, $stderr];
}

$tests = [
    'build returns ordered times and available minutes' => function (): void {
        $plan = Planner::build(lesson());
        same('09:30', $plan['blocos'][0]['horario_inicio']);
        same('09:40', $plan['blocos'][1]['horario_inicio']);
        same('11:00', $plan['blocos'][3]['horario_fim']);
        same(0, $plan['resumo']['minutos_livres']);
        same(90, $plan['resumo']['minutos_planejados']);
        same(4, $plan['resumo']['blocos']);
        same([], $plan['avisos']);
    },
    'preserves ordered unique materials and references' => function (): void {
        $plan = Planner::build(lesson());
        same(['Quadro', 'Bíblia', 'Projetor'], $plan['resumo']['materiais']);
        same(['Fp 4:4', 'Fp 3:7-9'], $plan['resumo']['referencias']);
        same(['Quadro', 'Bíblia'], $plan['blocos'][0]['materiais']);
    },
    'warns when application conclusion and free time are missing' => function (): void {
        $plan = Planner::build(lesson([
            'blocos' => [['titulo' => 'Exposição', 'minutos' => 10]],
        ]));
        same(80, $plan['resumo']['minutos_livres']);
        same(3, count($plan['avisos']));
        same(10, $plan['resumo']['minutos_por_tipo']['estudo']);
    },
    'defaults start and optional fields' => function (): void {
        $plan = Planner::build([
            'titulo' => 'Lição', 'duracao' => 30,
            'blocos' => [['titulo' => 'Estudo', 'minutos' => 30]],
        ]);
        same('09:00', $plan['aula']['inicio']);
        same('09:30', $plan['aula']['fim']);
        same(null, $plan['aula']['data']);
        same('', $plan['aula']['turma']);
    },
    'rejects missing and blank title' => function (): void {
        rejects(fn () => Planner::build(lesson(['titulo' => '  '])), 'titulo');
        rejects(fn () => Planner::build(lesson(['titulo' => null])), 'titulo');
    },
    'rejects noninteger and excessive durations' => function (): void {
        foreach ([0, 481, '90', 90.5, true] as $duration) {
            rejects(fn () => Planner::build(lesson(['duracao' => $duration])), 'duracao');
        }
    },
    'rejects malformed or empty blocks' => function (): void {
        foreach ([[], 'x', [2 => ['titulo' => 'A', 'minutos' => 10]]] as $blocks) {
            rejects(fn () => Planner::build(lesson(['blocos' => $blocks])), 'blocos');
        }
    },
    'rejects malformed block types' => function (): void {
        rejects(fn () => Planner::build(lesson([
            'blocos' => [['titulo' => 'A', 'minutos' => 10, 'tipo' => 'hack']],
        ])), 'tipo');
    },
    'rejects invalid block duration' => function (): void {
        rejects(fn () => Planner::build(lesson([
            'blocos' => [['titulo' => 'A', 'minutos' => 0]],
        ])), 'minutos');
    },
    'rejects a schedule that exceeds the lesson duration' => function (): void {
        rejects(fn () => Planner::build(lesson(['duracao' => 80])), 'ultrapassa');
    },
    'rejects crossing midnight' => function (): void {
        rejects(fn () => Planner::build(lesson(['inicio' => '23:30'])), 'meia-noite');
    },
    'rejects invalid clock formats' => function (): void {
        foreach (['9:00', '24:00', '12:60', '00:00:00', 900] as $start) {
            rejects(fn () => Planner::build(lesson(['inicio' => $start])), 'inicio');
        }
    },
    'rejects nonexistent dates and accepts leap day' => function (): void {
        foreach (['2026-02-29', '2026-13-01', '2026-2-1'] as $date) {
            rejects(fn () => Planner::build(lesson(['data' => $date])), 'data');
        }
        same('2028-02-29', Planner::build(lesson(['data' => '2028-02-29']))['aula']['data']);
    },
    'rejects invalid material shapes' => function (): void {
        foreach (['Quadro', [''], [5]] as $materials) {
            rejects(fn () => Planner::build(lesson([
                'blocos' => [['titulo' => 'A', 'minutos' => 10, 'materiais' => $materials]],
            ])), 'materiais');
        }
    },
    'deduplicates materials within each block' => function (): void {
        $plan = Planner::build(lesson([
            'blocos' => [[
                'titulo' => 'A', 'minutos' => 10,
                'materiais' => ['Bíblia', 'Bíblia', 'Quadro'],
            ]],
        ]));
        same(['Bíblia', 'Quadro'], $plan['blocos'][0]['materiais']);
    },
    'rejects invalid utf8 and control characters' => function (): void {
        rejects(fn () => Planner::build(lesson(['titulo' => "\xFF"])), 'UTF-8');
        rejects(fn () => Planner::build(lesson(['titulo' => "A\x00B"])), 'inválido');
    },
    'series summarizes multiple lessons' => function (): void {
        $course = Planner::series([
            lesson(),
            lesson(['data' => '2026-11-15', 'titulo' => 'Aula 2']),
        ]);
        same(2, $course['resumo']['quantidade']);
        same(180, $course['resumo']['minutos_totais']);
        same(['2026-11-08', '2026-11-15'], $course['resumo']['datas']);
    },
    'series rejects duplicate dates and invalid shape' => function (): void {
        rejects(fn () => Planner::series([lesson(), lesson()]), 'Data repetida');
        rejects(fn () => Planner::series([]), 'série');
        rejects(fn () => Planner::series([null]), 'Aula');
    },
    'markdown contains useful planning sections' => function (): void {
        $text = Planner::markdown(Planner::build(lesson()));
        truth(str_contains($text, '## Cronograma'), 'Tabela ausente');
        truth(str_contains($text, '## Materiais'), 'Checklist ausente');
        truth(str_contains($text, 'Notas reservadas'), 'Orientações ausentes');
        truth(str_contains($text, '09:30–09:40'), 'Horário ausente');
    },
    'markdown escapes raw html and table separators' => function (): void {
        $plan = Planner::build(lesson(['titulo' => '<script>|a']));
        $text = Planner::markdown($plan);
        truth(str_contains($text, '&lt;script&gt;\\|a'), 'Escape ausente');
        truth(!str_contains($text, '<script>'), 'HTML cru vazou');
    },
    'student handout excludes private teacher notes' => function (): void {
        $text = Planner::studentHandout(Planner::build(lesson()));
        truth(str_contains($text, 'Fp 3:7-9'), 'Referência ausente');
        truth(!str_contains($text, 'Notas reservadas'), 'Notas privadas vazaram');
        truth(!str_contains($text, 'Projetor'), 'Material do professor vazou');
    },
    'csv quotes commas and blocks formula injection' => function (): void {
        $plan = Planner::build(lesson([
            'blocos' => [[
                'titulo' => '=HYPERLINK("bad")',
                'referencia' => '+1,2',
                'minutos' => 10,
            ]],
        ]));
        $csv = Planner::csv($plan);
        truth(str_contains($csv, "'=HYPERLINK"), 'Fórmula no título não neutralizada');
        truth(str_contains($csv, "'+1,2"), 'Fórmula na referência não neutralizada');
        $rows = array_map('str_getcsv', array_filter(explode("\n", trim($csv))));
        same(2, count($rows));
        same(7, count($rows[1]));
    },
    'exports reject a non-plan argument' => function (): void {
        rejects(fn () => Planner::markdown([]), 'planejamento');
        rejects(fn () => Planner::studentHandout([]), 'planejamento');
        rejects(fn () => Planner::csv([]), 'planejamento');
    },
    'cli emits valid json and markdown' => function (): void {
        $file = tempnam(sys_get_temp_dir(), 'ebd');
        try {
            file_put_contents($file, json_encode(lesson(), JSON_THROW_ON_ERROR));
            [$code, $out] = runCli([$file]);
            same(0, $code);
            same(4, json_decode($out, true, 32, JSON_THROW_ON_ERROR)['resumo']['blocos']);
            [$code, $out] = runCli([$file, '--format=markdown']);
            same(0, $code);
            truth(str_contains($out, '## Cronograma'), 'Markdown ausente');
            [$code, $out] = runCli([$file, '--format=student']);
            same(0, $code);
            truth(!str_contains($out, 'Notas reservadas'), 'Vazamento na CLI');
            [$code, $out] = runCli([$file, '--format=csv']);
            same(0, $code);
            truth(str_contains($out, 'Ordem,Início'), 'Cabeçalho CSV ausente');
        } finally {
            unlink($file);
        }
    },
    'cli validates malformed input and options' => function (): void {
        $file = tempnam(sys_get_temp_dir(), 'ebd');
        try {
            file_put_contents($file, '{');
            [$code, , $error] = runCli([$file]);
            same(2, $code);
            truth(str_contains($error, 'Erro:'), 'Erro amigável ausente');
            [$code] = runCli([$file, '--format=invalid']);
            same(2, $code);
            [$code] = runCli([$file, '--series', '--format=csv']);
            same(2, $code);
        } finally {
            unlink($file);
        }
    },
    'cli supports series with independent lessons' => function (): void {
        $file = tempnam(sys_get_temp_dir(), 'ebd');
        try {
            file_put_contents($file, json_encode([
                lesson(), lesson(['data' => '2026-11-15']),
            ], JSON_THROW_ON_ERROR));
            [$code, $out] = runCli([$file, '--series']);
            same(0, $code);
            same(2, json_decode($out, true, 32, JSON_THROW_ON_ERROR)['resumo']['quantidade']);
        } finally {
            unlink($file);
        }
    },
];

$failed = 0;
foreach ($tests as $name => $test) {
    try {
        $test();
        echo "[OK] $name\n";
    } catch (Throwable $error) {
        ++$failed;
        fwrite(STDERR, "[FAIL] $name: " . $error->getMessage() . "\n");
    }
}
echo sprintf("%d testes; %d falhas\n", count($tests), $failed);
exit($failed === 0 ? 0 : 1);
