<?php
declare(strict_types=1);
function plan(array $blocks,int $available): array {
    if($available<=0 || $available>480) throw new InvalidArgumentException('Duração deve estar entre 1 e 480 minutos');
    if(!array_is_list($blocks)) throw new InvalidArgumentException('A aula deve ser uma lista de blocos');
    $elapsed=0; $rows=[];
    foreach($blocks as $block) {
        if(!is_array($block) || !is_string($block['titulo']??null) || trim($block['titulo'])==='' || !is_int($block['minutos']??null) || $block['minutos']<=0 || $block['minutos']>480 || (array_key_exists('referencia', $block) && !is_string($block['referencia']))) throw new InvalidArgumentException('Bloco inválido');
        if($elapsed+$block['minutos']>$available) throw new InvalidArgumentException('Os blocos ultrapassam o tempo disponível');
        $rows[]=['inicio'=>$elapsed,'fim'=>$elapsed+$block['minutos'],'titulo'=>$block['titulo'],'referencia'=>$block['referencia']??''];
        $elapsed+=$block['minutos'];
    }
    return ['blocos'=>$rows,'minutos_livres'=>$available-$elapsed];
}
try {
    if(($argv[1]??'')==='--self-test') {
        $b=[['titulo'=>'Abertura','minutos'=>10],['titulo'=>'Estudo','minutos'=>20]]; $p=plan($b,40);
        if($p['minutos_livres']!==10 || $p['blocos'][1]['inicio']!==10) throw new RuntimeException('Teste falhou');
        try { plan($b,29); throw new RuntimeException('Falha'); } catch(InvalidArgumentException $e) {}
        $invalidCases = [
            ['bloco' => ['titulo'=>'Abertura','minutos'=>10]],
            [['titulo'=>'Estudo','minutos'=>20,'referencia'=>['Fp 3:1']]],
            [['titulo'=>'Estudo','minutos'=>true]],
        ];
        foreach ($invalidCases as $case) {
            try { plan($case,40); throw new RuntimeException('Entrada inválida aceita'); }
            catch(InvalidArgumentException $e) {}
        }
        echo "5 cenários OK\n"; exit(0);
    }
    if(count($argv)!==3 || !ctype_digit($argv[1])) throw new InvalidArgumentException('Uso: php main.php MINUTOS aula.json');
    $text=file_get_contents($argv[2]); if($text===false) throw new RuntimeException('Falha de leitura');
    $blocks=json_decode($text,true,128,JSON_THROW_ON_ERROR); if(!is_array($blocks)) throw new InvalidArgumentException('Esperada lista de blocos');
    echo json_encode(plan($blocks,(int)$argv[1]),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
} catch(Throwable $e) { fwrite(STDERR,"Erro: {$e->getMessage()}\n"); exit(2); }
