<?php
declare(strict_types=1);

namespace Ebd;

/**
 * Optional, read-only adapter for a local checkout of adc-de-fuc-ebd.
 *
 * Expected layout: <data-root>/bolls/<translation>/<book-number>/<chapter>.json.
 * No remote calls, embedded Bible text, downloads, or changes to the source data.
 */
final class BibleData
{
    private const BOOKS = [
        'genesis', 'exodo', 'levitico', 'numeros', 'deuteronomio', 'josue',
        'juizes', 'rute', '1samuel', '2samuel', '1reis', '2reis',
        '1cronicas', '2cronicas', 'esdras', 'neemias', 'ester', 'joantigo',
        'salmos', 'proverbios', 'eclesiastes', 'cantares', 'isaias', 'jeremias',
        'lamentacoes', 'ezequiel', 'daniel', 'oseias', 'joel', 'amos',
        'obadias', 'jonas', 'miqueias', 'naum', 'habacuque', 'sofonias',
        'ageu', 'zacarias', 'malaquias', 'mateus', 'marcos', 'lucas',
        'joao', 'atos', 'romanos', '1corintios', '2corintios', 'galatas',
        'efesios', 'filipenses', 'colossenses', '1tessalonicenses',
        '2tessalonicenses', '1timoteo', '2timoteo', 'tito', 'filemom',
        'hebreus', 'tiago', '1pedro', '2pedro', '1joao', '2joao',
        '3joao', 'judas', 'apocalipse',
    ];

    private const ALIASES = [
        'gn' => 1, 'gen' => 1, 'ex' => 2, 'lv' => 3, 'nm' => 4,
        'dt' => 5, 'js' => 6, 'jz' => 7, 'rt' => 8,
        '1sm' => 9, '2sm' => 10, '1rs' => 11, '2rs' => 12,
        '1cr' => 13, '2cr' => 14, 'ed' => 15, 'ne' => 16,
        'et' => 17, 'job' => 18, 'sl' => 19, 'pv' => 20,
        'ec' => 21, 'ct' => 22, 'is' => 23, 'jr' => 24,
        'lm' => 25, 'ez' => 26, 'dn' => 27, 'os' => 28,
        'jl' => 29, 'am' => 30, 'ob' => 31, 'jn' => 32,
        'mq' => 33, 'na' => 34, 'hc' => 35, 'sf' => 36,
        'ag' => 37, 'zc' => 38, 'ml' => 39, 'mt' => 40,
        'mc' => 41, 'lc' => 42, 'jo' => 43, 'at' => 44,
        'rm' => 45, '1co' => 46, '2co' => 47, 'gl' => 48,
        'ef' => 49, 'fp' => 50, 'cl' => 51, '1ts' => 52,
        '2ts' => 53, '1tm' => 54, '2tm' => 55, 'tt' => 56,
        'fm' => 57, 'hb' => 58, 'tg' => 59, '1pe' => 60,
        '2pe' => 61, '1jo' => 62, '2jo' => 63, '3jo' => 64,
        'jd' => 65, 'ap' => 66,
    ];

    /**
     * @return array{referencia:string,traducao:string,livro_numero:int,capitulo:int,versiculos:list<array{numero:int,texto:string}>}
     */
    public static function lookup(string $reference, string $dataDirectory, string $translation = 'ACF11'): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{1,20}$/D', $translation)) {
            throw new \InvalidArgumentException('Código de tradução inválido');
        }
        if (!preg_match('/^(.+?)\s+([1-9][0-9]{0,2})(?::([1-9][0-9]{0,2})(?:\s*[-–]\s*([1-9][0-9]{0,2}))?)?$/uD', trim($reference), $match)) {
            throw new \InvalidArgumentException('Use referência como Fp 4:4-6 ou João 3:16');
        }

        $bookKey = self::bookKey($match[1]);
        $bookIndex = array_search($bookKey, self::BOOKS, true);
        $bookNumber = $bookIndex === false ? (self::ALIASES[$bookKey] ?? null) : $bookIndex + 1;
        if ($bookNumber === null) {
            throw new \InvalidArgumentException('Livro bíblico não reconhecido');
        }

        $chapter = (int) $match[2];
        $first = isset($match[3]) && $match[3] !== '' ? (int) $match[3] : null;
        $last = isset($match[4]) && $match[4] !== '' ? (int) $match[4] : $first;
        if ($first !== null && ($last < $first || $last - $first > 199)) {
            throw new \InvalidArgumentException('Intervalo de versículos inválido ou superior a 200');
        }

        $root = realpath($dataDirectory);
        if ($root === false || !is_dir($root . '/bolls')) {
            throw new \InvalidArgumentException('Diretório da base bíblica não encontrado (esperado: bolls/)');
        }
        $file = realpath($root . '/bolls/' . $translation . '/' . $bookNumber . '/' . $chapter . '.json');
        if ($file === false || !str_starts_with($file, $root . DIRECTORY_SEPARATOR . 'bolls' . DIRECTORY_SEPARATOR) || !is_file($file)) {
            throw new \RuntimeException('Capítulo não disponível nessa tradução ou base local');
        }
        $bytes = filesize($file);
        if ($bytes === false || $bytes < 2 || $bytes > 1048576) {
            throw new \RuntimeException('Arquivo de capítulo vazio ou acima do limite de segurança');
        }
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new \RuntimeException('Não foi possível ler o capítulo');
        }
        try {
            $rows = json_decode($contents, true, 12, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new \RuntimeException('JSON inválido no arquivo da base bíblica', 0, $error);
        }
        if (!is_array($rows) || !array_is_list($rows) || count($rows) > 200) {
            throw new \RuntimeException('Estrutura inválida no capítulo bíblico');
        }
        $verses = [];
        $seenVerseNumbers = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !is_int($row['verse'] ?? null)
                || $row['verse'] < 1 || $row['verse'] > 200
                || !is_string($row['text'] ?? null)
                || strlen($row['text']) > 12000 || !preg_match('//u', $row['text'])) {
                throw new \RuntimeException('Versículo malformado na base bíblica');
            }
            $number = $row['verse'];
            // A integridade do capítulo inteiro importa, mesmo ao consultar um único verso.
            if (isset($seenVerseNumbers[$number])) {
                throw new \RuntimeException('Versículo duplicado na base bíblica');
            }
            $seenVerseNumbers[$number] = true;
            if ($first === null || ($number >= $first && $number <= $last)) {
                $verses[$number] = ['numero' => $number, 'texto' => $row['text']];
            }
        }

        if ($verses === []) {
            throw new \RuntimeException('Não foram encontrados versículos para a referência');
        }
        ksort($verses);
        if ($first !== null) {
            for ($number = $first; $number <= $last; $number++) {
                if (!isset($verses[$number])) {
                    throw new \RuntimeException('A referência contém versículos ausentes na base local');
                }
            }
        }
        return [
            'referencia' => $reference,
            'traducao' => $translation,
            'livro_numero' => $bookNumber,
            'capitulo' => $chapter,
            'versiculos' => array_values($verses),
        ];
    }

    /** @return list<array<string,mixed>> */
    public static function forLesson(array $plan, string $dataDirectory, string $translation = 'ACF11'): array
    {
        if (!isset($plan['resumo']['referencias']) || !is_array($plan['resumo']['referencias'])) {
            throw new \InvalidArgumentException('O plano deve ser gerado por Planner::build()');
        }
        $result = [];
        foreach ($plan['resumo']['referencias'] as $reference) {
            try {
                $result[] = self::lookup($reference, $dataDirectory, $translation);
            } catch (\InvalidArgumentException | \RuntimeException $error) {
                throw new \RuntimeException('Referência ' . $reference . ': ' . $error->getMessage(), 0, $error);
            }
        }
        return $result;
    }

    /** @param list<array<string,mixed>> $passages */
    public static function markdown(array $passages): string
    {
        $lines = ["", "## Leituras bíblicas (base local)", ""];
        foreach ($passages as $passage) {
            $reference = self::escapeMarkdown($passage['referencia']);
            $translation = self::escapeMarkdown($passage['traducao']);
            $lines[] = '### ' . $reference . ' — ' . $translation;
            foreach ($passage['versiculos'] as $verse) {
                $lines[] = '- **' . $verse['numero'] . '** ' . self::escapeMarkdown($verse['texto']);
            }
            $lines[] = '';
        }
        return implode("\n", $lines);
    }

    private static function bookKey(string $name): string
    {
        if (preg_match('/^Jó$/iuD', trim($name))) {
            return 'joantigo';
        }
        $normalized = strtr($name, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e',
            'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ü' => 'u',
            'ç' => 'c', 'Á' => 'a', 'À' => 'a', 'Â' => 'a', 'Ã' => 'a', 'É' => 'e',
            'Ê' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ô' => 'o', 'Õ' => 'o', 'Ú' => 'u',
            'Ç' => 'c',
        ]);
        return strtolower(preg_replace('/[\s._-]+/u', '', $normalized));
    }

    private static function escapeMarkdown(string $value): string
    {
        $safe = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
        return str_replace(["\r", "\n", '|', '<'], [' ', ' ', '\\|', '&lt;'], $safe);
    }
}
