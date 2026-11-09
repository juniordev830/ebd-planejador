<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SeriesInsights.php';

use Ebd\SeriesInsights;

function assertThat(bool $ok, string $description): void
{
    if (!$ok) {
        throw new RuntimeException("Falha: " . $description);
    }
}

$lessons = [
    [
        'titulo' => 'Lição 1',
        'duracao' => 50,
        'data' => '2026-11-08',
        'blocos' => [
            ['titulo' => 'Abertura', 'tipo' => 'introducao', 'minutos' => 10, 'materiais' => ['Bíblia']],
            ['titulo' => 'Estudo', 'tipo' => 'estudo', 'minutos' => 20,
             'referencia' => 'Fp 4:4', 'materiais' => ['Quadro', 'Bíblia']],
            ['titulo' => 'Aplicação', 'tipo' => 'aplicacao', 'minutos' => 10],
            ['titulo' => 'Conclusão', 'tipo' => 'conclusao', 'minutos' => 10],
        ],
    ],
    [
        'titulo' => 'Lição 2',
        'duracao' => 45,
        'data' => '2026-11-15',
        'blocos' => [
            ['titulo' => 'Estudo', 'tipo' => 'estudo', 'minutos' => 25,
             'referencia' => 'Fp 4:4', 'materiais' => ['Bíblia', 'Projetor']],
            ['titulo' => 'Pausa', 'tipo' => 'intervalo', 'minutos' => 5],
        ],
    ],
];
$report = SeriesInsights::summarize($lessons);
assertThat($report['quantidade_aulas'] === 2, 'contagem de aulas');
assertThat($report['minutos_por_semana'] === ['2026-W45' => 50, '2026-W46' => 45],
    'carga semanal distribuída pelo calendário ISO');
assertThat($report['intervalos_entre_aulas'] === [
    ['aula_anterior' => 1, 'aula_seguinte' => 2, 'dias' => 7]
], 'intervalo semanal regular');
assertThat($report['pausas_acima_14_dias'] === [], 'intervalo de sete dias não é pausa longa');
assertThat($report['minutos_totais'] === 95, 'minutos totais');
assertThat($report['minutos_planejados'] === 80, 'minutos planejados');
assertThat($report['minutos_livres'] === 15, 'minutos livres');
assertThat($report['minutos_por_tipo']['estudo'] === 45, 'soma de estudo');
assertThat($report['minutos_por_tipo']['intervalo'] === 5, 'soma de intervalos');
assertThat($report['materiais_unicos'] === ['Bíblia', 'Quadro', 'Projetor'],
    'materiais únicos e preservação de ordem');
assertThat($report['referencias_unicas'] === ['Fp 4:4'], 'referência deduplicada');
assertThat($report['aulas_sem_aplicacao'] === [2], 'aulas sem aplicação');
assertThat($report['aulas_sem_conclusao'] === [2], 'aulas sem conclusão');
assertThat(count($report['avisos_por_aula']) === 1 &&
    $report['avisos_por_aula'][0]['aula'] === 2, 'avisos por aula');

$reversed = SeriesInsights::summarize(array_reverse($lessons));
assertThat($reversed['intervalos_entre_aulas'] === [
    ['aula_anterior' => 2, 'aula_seguinte' => 1, 'dias' => 7]
], 'datas ordenadas sem alterar referências originais');

$farLesson = $lessons[0];
$farLesson['data'] = '2026-12-06';
$longSeries = SeriesInsights::summarize([$lessons[0], $lessons[1], $farLesson]);
assertThat($longSeries['pausas_acima_14_dias'] === [
    ['aula_anterior' => 2, 'aula_seguinte' => 3, 'dias' => 21]
], 'pausa superior a 14 dias destacada');
$undated = $lessons[0];
unset($undated['data']);
$reportWithoutDate = SeriesInsights::summarize([$undated]);
assertThat($reportWithoutDate['minutos_por_semana'] === [] &&
    $reportWithoutDate['intervalos_entre_aulas'] === [],
    'aulas sem data não criam intervalos fictícios');

try {
    SeriesInsights::summarize([$lessons[0], $lessons[0]]);
    throw new RuntimeException('Séries com datas duplicadas devem falhar.');
} catch (InvalidArgumentException $expected) {
    assertThat(str_contains($expected->getMessage(), 'repetida'), 'validação de datas');
}

$temp = tempnam(sys_get_temp_dir(), 'ebd-series-');
if ($temp === false) {
    throw new RuntimeException('Não foi possível criar arquivo de testes');
}
try {
    file_put_contents($temp, json_encode($lessons, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    $command = [
        PHP_BINARY, dirname(__DIR__) . '/planner.php', $temp, '--series', '--insights',
    ];
    $proc = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    assertThat(is_resource($proc), 'iniciar CLI');
    $json = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($proc);
    assertThat($exit === 0, 'CLI: ' . $error);
    $decoded = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
    assertThat($decoded['minutos_livres'] === 15, 'relatório JSON via CLI');

    $invalid = proc_open(
        [PHP_BINARY, dirname(__DIR__) . '/planner.php', $temp, '--insights'],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes
    );
    assertThat(is_resource($invalid), 'iniciar CLI inválida');
    fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    assertThat(proc_close($invalid) === 2 && str_contains($error, '--series'),
        'opção insights sem series precisa falhar');
} finally {
    unlink($temp);
}
echo "SeriesInsights: calendário semanal e lacunas verificados.\n";
