<?php
declare(strict_types=1);

namespace Ebd;

/**
 * Pure domain service: validates lesson data, builds a timetable and renders
 * safe, deterministic exports. No database, network access or side effects.
 */
final class Planner
{
    public const TYPES = [
        'introducao', 'estudo', 'dinamica', 'aplicacao', 'conclusao', 'intervalo',
    ];

    private const MAX_BLOCKS = 80;
    private const MAX_DURATION = 480;

    /** @param array<string,mixed> $lesson */
    public static function build(array $lesson): array
    {
        $title = self::text($lesson['titulo'] ?? null, 'titulo', 120);
        $duration = self::integer($lesson['duracao'] ?? null, 'duracao', 1, self::MAX_DURATION);
        $start = self::clock($lesson['inicio'] ?? '09:00');
        $startMinutes = self::clockMinutes($start);
        if ($startMinutes + $duration > 1440) {
            throw new \InvalidArgumentException('A aula não pode ultrapassar a meia-noite');
        }

        $blocks = $lesson['blocos'] ?? null;
        if (!is_array($blocks) || !array_is_list($blocks) || count($blocks) < 1 || count($blocks) > self::MAX_BLOCKS) {
            throw new \InvalidArgumentException('blocos deve ser uma lista com 1 a 80 itens');
        }

        $date = self::optionalDate($lesson['data'] ?? null);
        $group = self::optionalText($lesson['turma'] ?? null, 'turma', 80);
        $goal = self::optionalText($lesson['objetivo'] ?? null, 'objetivo', 500);
        $elapsed = 0;
        $schedule = [];
        $materials = [];
        $types = array_fill_keys(self::TYPES, 0);
        $references = [];
        $warnings = [];

        foreach ($blocks as $index => $block) {
            if (!is_array($block) || array_is_list($block)) {
                throw new \InvalidArgumentException("blocos[$index] deve ser um objeto");
            }
            $name = self::text($block['titulo'] ?? null, "blocos[$index].titulo", 120);
            $minutes = self::integer($block['minutos'] ?? null, "blocos[$index].minutos", 1, self::MAX_DURATION);
            $type = $block['tipo'] ?? 'estudo';
            if (!is_string($type) || !in_array($type, self::TYPES, true)) {
                throw new \InvalidArgumentException("blocos[$index].tipo inválido");
            }
            $reference = self::optionalText($block['referencia'] ?? null, "blocos[$index].referencia", 160);
            $description = self::optionalText($block['descricao'] ?? null, "blocos[$index].descricao", 1000);
            $items = self::materials($block['materiais'] ?? [], $index);
            $next = $elapsed + $minutes;
            if ($next > $duration) {
                throw new \InvalidArgumentException("blocos[$index] ultrapassa a duração da aula");
            }

            foreach ($items as $item) {
                $materials[self::key($item)] ??= $item;
            }
            if ($reference !== '') {
                $references[self::key($reference)] ??= $reference;
            }
            $types[$type] += $minutes;
            $schedule[] = [
                'ordem' => $index + 1,
                'titulo' => $name,
                'tipo' => $type,
                'minutos' => $minutes,
                'inicio' => $elapsed,
                'fim' => $next,
                'horario_inicio' => self::minutesClock($startMinutes + $elapsed),
                'horario_fim' => self::minutesClock($startMinutes + $next),
                'referencia' => $reference,
                'descricao' => $description,
                'materiais' => $items,
            ];
            $elapsed = $next;
        }

        if ($types['aplicacao'] === 0) {
            $warnings[] = 'Considere reservar um momento de aplicação prática.';
        }
        if ($types['conclusao'] === 0) {
            $warnings[] = 'Considere incluir uma conclusão.';
        }
        if ($duration - $elapsed > 0) {
            $warnings[] = 'Há tempo disponível não distribuído entre os blocos.';
        }

        return [
            'aula' => [
                'titulo' => $title,
                'duracao' => $duration,
                'inicio' => $start,
                'fim' => self::minutesClock($startMinutes + $duration),
                'data' => $date,
                'turma' => $group,
                'objetivo' => $goal,
            ],
            'blocos' => $schedule,
            'resumo' => [
                'blocos' => count($schedule),
                'minutos_planejados' => $elapsed,
                'minutos_livres' => $duration - $elapsed,
                'minutos_por_tipo' => array_filter($types, static fn (int $value): bool => $value > 0),
                'materiais' => array_values($materials),
                'referencias' => array_values($references),
            ],
            'avisos' => $warnings,
        ];
    }

    /** Build a course overview, keeping each lesson's validation independent. */
    public static function series(array $lessons): array
    {
        if (!array_is_list($lessons) || count($lessons) < 1 || count($lessons) > 52) {
            throw new \InvalidArgumentException('A série precisa conter de 1 a 52 aulas');
        }
        $result = [];
        $dates = [];
        $total = 0;
        foreach ($lessons as $index => $lesson) {
            if (!is_array($lesson)) {
                throw new \InvalidArgumentException("Aula $index inválida");
            }
            $planned = self::build($lesson);
            $date = $planned['aula']['data'];
            if ($date !== null) {
                if (isset($dates[$date])) {
                    throw new \InvalidArgumentException("Data repetida na série: $date");
                }
                $dates[$date] = true;
            }
            $total += $planned['aula']['duracao'];
            $result[] = $planned;
        }
        return [
            'aulas' => $result,
            'resumo' => [
                'quantidade' => count($result),
                'minutos_totais' => $total,
                'datas' => array_keys($dates),
            ],
        ];
    }

    /** A teacher-facing lesson plan with a timetable and checklist. */
    public static function markdown(array $plan): string
    {
        self::assertPlan($plan);
        $aula = $plan['aula'];
        $lines = [
            '# ' . self::markdownText($aula['titulo']),
            '',
            '**Turma:** ' . self::markdownText($aula['turma'] ?: 'Não informada'),
            '**Data:** ' . ($aula['data'] ?? 'Não informada'),
            '**Horário:** ' . $aula['inicio'] . '–' . $aula['fim'],
            '**Objetivo:** ' . self::markdownText($aula['objetivo'] ?: 'Não informado'),
            '',
            '## Cronograma',
            '',
            '| Horário | Etapa | Tipo | Minutos | Referência |',
            '| --- | --- | --- | ---: | --- |',
        ];
        foreach ($plan['blocos'] as $block) {
            $lines[] = '| ' . $block['horario_inicio'] . '–' . $block['horario_fim']
                . ' | ' . self::markdownText($block['titulo'])
                . ' | ' . $block['tipo']
                . ' | ' . $block['minutos']
                . ' | ' . self::markdownText($block['referencia']) . ' |';
        }
        $lines[] = '';
        $lines[] = '## Orientações por etapa';
        foreach ($plan['blocos'] as $block) {
            $lines[] = '';
            $lines[] = '### ' . $block['ordem'] . '. ' . self::markdownText($block['titulo']);
            $lines[] = self::markdownText($block['descricao'] ?: 'Sem observações adicionais.');
        }
        $lines[] = '';
        $lines[] = '## Materiais';
        foreach ($plan['resumo']['materiais'] as $item) {
            $lines[] = '- ' . self::markdownText($item);
        }
        if (!$plan['resumo']['materiais']) {
            $lines[] = 'Nenhum material informado.';
        }
        $lines[] = '';
        $lines[] = '## Alertas de planejamento';
        foreach ($plan['avisos'] as $warning) {
            $lines[] = '- ' . self::markdownText($warning);
        }
        if (!$plan['avisos']) {
            $lines[] = 'Nenhum alerta.';
        }
        return implode("\n", $lines) . "\n";
    }

    /** Student handout excludes teacher descriptions and preparation notes. */
    public static function studentHandout(array $plan): string
    {
        self::assertPlan($plan);
        $aula = $plan['aula'];
        $lines = [
            '# ' . self::markdownText($aula['titulo']),
            '',
            '**Turma:** ' . self::markdownText($aula['turma'] ?: 'Não informada'),
            '**Data:** ' . ($aula['data'] ?? 'Não informada'),
            '**Objetivo:** ' . self::markdownText($aula['objetivo'] ?: 'Não informado'),
            '',
            '## Roteiro da aula',
        ];
        foreach ($plan['blocos'] as $block) {
            if ($block['tipo'] === 'intervalo') {
                continue;
            }
            $lines[] = '- **' . self::markdownText($block['titulo']) . '**'
                . ($block['referencia'] !== '' ? ' — ' . self::markdownText($block['referencia']) : '');
        }
        $lines[] = '';
        $lines[] = '## Referências';
        foreach ($plan['resumo']['referencias'] as $reference) {
            $lines[] = '- ' . self::markdownText($reference);
        }
        if (!$plan['resumo']['referencias']) {
            $lines[] = 'Referências não informadas.';
        }
        return implode("\n", $lines) . "\n";
    }

    /** RFC 4180-compatible CSV with spreadsheet formula injection prevention. */
    public static function csv(array $plan): string
    {
        self::assertPlan($plan);
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new \RuntimeException('Não foi possível criar o CSV');
        }
        try {
            fputcsv($stream, ['Ordem', 'Início', 'Fim', 'Título', 'Tipo', 'Minutos', 'Referência'], ',', '"', '');
            foreach ($plan['blocos'] as $block) {
                fputcsv($stream, [
                    $block['ordem'],
                    $block['horario_inicio'],
                    $block['horario_fim'],
                    self::safeCell($block['titulo']),
                    $block['tipo'],
                    $block['minutos'],
                    self::safeCell($block['referencia']),
                ], ',', '"', '');
            }
            rewind($stream);
            $output = stream_get_contents($stream);
            if ($output === false) {
                throw new \RuntimeException('Não foi possível ler o CSV');
            }
            return $output;
        } finally {
            fclose($stream);
        }
    }

    private static function safeCell(string $value): string
    {
        return preg_match('/^[\s\x00-\x1F]*[=+@\-]/u', $value) === 1 ? "'" . $value : $value;
    }

    private static function assertPlan(array $plan): void
    {
        if (!isset($plan['aula'], $plan['blocos'], $plan['resumo'], $plan['avisos'])) {
            throw new \InvalidArgumentException('O argumento deve ser um planejamento gerado por build()');
        }
    }

    private static function text(mixed $value, string $field, int $max): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException("$field deve ser texto não vazio");
        }
        $value = trim($value);
        if (strlen($value) > $max * 4 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) {
            throw new \InvalidArgumentException("$field contém texto inválido ou muito longo");
        }
        if (!preg_match('//u', $value)) {
            throw new \InvalidArgumentException("$field deve ser UTF-8");
        }
        if (function_exists('mb_strlen') && mb_strlen($value, 'UTF-8') > $max) {
            throw new \InvalidArgumentException("$field excede $max caracteres");
        }
        if (!function_exists('mb_strlen') && strlen($value) > $max * 4) {
            throw new \InvalidArgumentException("$field excede o limite");
        }
        return $value;
    }

    private static function optionalText(mixed $value, string $field, int $max): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        return self::text($value, $field, $max);
    }

    private static function integer(mixed $value, string $field, int $min, int $max): int
    {
        if (!is_int($value) || $value < $min || $value > $max) {
            throw new \InvalidArgumentException("$field deve ser inteiro entre $min e $max");
        }
        return $value;
    }

    private static function clock(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $value)) {
            throw new \InvalidArgumentException('inicio deve usar HH:MM (24 horas)');
        }
        return $value;
    }

    private static function clockMinutes(string $value): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $value));
        return $hours * 60 + $minutes;
    }

    private static function minutesClock(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    private static function optionalDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $match)
            || !checkdate((int) $match[2], (int) $match[3], (int) $match[1])) {
            throw new \InvalidArgumentException('data deve ser uma data real em AAAA-MM-DD');
        }
        return $value;
    }

    private static function materials(mixed $items, int $index): array
    {
        if (!is_array($items) || !array_is_list($items) || count($items) > 30) {
            throw new \InvalidArgumentException("blocos[$index].materiais deve ser lista de até 30 itens");
        }
        $unique = [];
        foreach ($items as $position => $item) {
            $name = self::text($item, "blocos[$index].materiais[$position]", 100);
            $unique[self::key($name)] ??= $name;
        }
        return array_values($unique);
    }

    private static function key(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private static function markdownText(string $value): string
    {
        $value = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
        return str_replace(['|', "\r", "\n"], ['\\|', ' ', ' '], $value);
    }
}
