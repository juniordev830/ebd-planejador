<?php
declare(strict_types=1);

namespace Ebd;

require_once __DIR__ . '/Planner.php';

/**
 * Aggregate a validated series of lessons without loading external materials.
 * This class only summarizes teacher-supplied data.
 */
final class SeriesInsights
{
    /** @param list<array<string,mixed>> $lessons */
    public static function summarize(array $lessons): array
    {
        $series = Planner::series($lessons);
        $byType = array_fill_keys(Planner::TYPES, 0);
        $uniqueMaterials = [];
        $uniqueReferences = [];
        $planned = 0;
        $free = 0;
        $missingApplication = [];
        $missingConclusion = [];
        $warningsByLesson = [];
        $minutesByWeek = [];
        $datedLessons = [];

        foreach ($series['aulas'] as $index => $plan) {
            $number = $index + 1;
            $summary = $plan['resumo'];
            $date = $plan['aula']['data'];
            if ($date !== null) {
                $calendarDay = new \DateTimeImmutable($date);
                $weekKey = $calendarDay->format('o') . '-W' . $calendarDay->format('W');
                $minutesByWeek[$weekKey] = ($minutesByWeek[$weekKey] ?? 0) + $plan['aula']['duracao'];
                $datedLessons[] = ['aula' => $number, 'data' => $date];
            }
            $planned += $summary['minutos_planejados'];
            $free += $summary['minutos_livres'];
            foreach ($summary['minutos_por_tipo'] as $type => $minutes) {
                $byType[$type] += $minutes;
            }

            foreach ($summary['materiais'] as $material) {
                $key = function_exists('mb_strtolower')
                    ? mb_strtolower($material, 'UTF-8') : strtolower($material);
                $uniqueMaterials[$key] ??= $material;
            }
            foreach ($summary['referencias'] as $reference) {
                $key = function_exists('mb_strtolower')
                    ? mb_strtolower($reference, 'UTF-8') : strtolower($reference);
                $uniqueReferences[$key] ??= $reference;
            }

            if (!array_key_exists('aplicacao', $summary['minutos_por_tipo'])) {
                $missingApplication[] = $number;
            }
            if (!array_key_exists('conclusao', $summary['minutos_por_tipo'])) {
                $missingConclusion[] = $number;
            }
            if ($plan['avisos'] !== []) {
                $warningsByLesson[] = [
                    'aula' => $number,
                    'titulo' => $plan['aula']['titulo'],
                    'avisos' => $plan['avisos'],
                ];
            }
        }

        // Sort by calendar date without changing the original lesson sequence.
        usort($datedLessons, static fn (array $a, array $b): int =>
            strcmp($a['data'], $b['data']));
        ksort($minutesByWeek);
        $intervals = [];
        $longGaps = [];
        for ($i = 1, $total = count($datedLessons); $i < $total; $i++) {
            $previous = $datedLessons[$i - 1];
            $current = $datedLessons[$i];
            $days = (new \DateTimeImmutable($previous['data']))
                ->diff(new \DateTimeImmutable($current['data']))->days;
            $gap = [
                'aula_anterior' => $previous['aula'],
                'aula_seguinte' => $current['aula'],
                'dias' => $days,
            ];
            $intervals[] = $gap;
            if ($days > 14) {
                $longGaps[] = $gap;
            }
        }

        return [
            'minutos_por_semana' => $minutesByWeek,
            'intervalos_entre_aulas' => $intervals,
            'pausas_acima_14_dias' => $longGaps,
            'quantidade_aulas' => $series['resumo']['quantidade'],
            'minutos_totais' => $series['resumo']['minutos_totais'],
            'minutos_planejados' => $planned,
            'minutos_livres' => $free,
            'minutos_por_tipo' => array_filter($byType, static fn (int $n): bool => $n > 0),
            'materiais_unicos' => array_values($uniqueMaterials),
            'referencias_unicas' => array_values($uniqueReferences),
            'aulas_sem_aplicacao' => $missingApplication,
            'aulas_sem_conclusao' => $missingConclusion,
            'avisos_por_aula' => $warningsByLesson,
        ];
    }
}
