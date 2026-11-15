# Critérios de qualidade do planejamento EBD

## Objetivo

Manter o planejador reprodutível, acessível e independente de conteúdo editorial licenciado.

## Cenários de regressão

| Entrada | Resultado esperado |
| --- | --- |
| Duração zero ou acima de 480 minutos | Rejeição com erro claro |
| Soma dos blocos maior que a aula | Rejeição sem saída parcial |
| Data inexistente | Rejeição |
| Horário que ultrapassa meia-noite | Rejeição |
| Série com datas duplicadas | Rejeição |
| Material com tipo incorreto | Rejeição |
| Título com caracteres de controle | Rejeição |
| Texto iniciado por =, +, - ou @ exportado para CSV | Neutralização de fórmula |
| Saída student | Omitir notas internas de preparação |
| Série de até 52 aulas | Consolidação conforme contrato documentado |

## Revisão pedagógica e direitos autorais

- Tratar alertas como auxílio ao professor, não como julgamento automático de conteúdo bíblico.
- Manter exemplos autorais e referências bíblicas; não reproduzir revistas ou comentários protegidos sem licença.
- Conferir manualmente contexto, interpretação e aplicação antes da aula.
- Não incluir dados pessoais de alunos nos exemplos versionados.

## Comandos de verificação

```bash
php -l main.php
php -l planner.php
php -l src/Planner.php
php -l tests/test_planner.php
php main.php --self-test
php tests/test_planner.php
```

Os comandos acima devem ser executados em ambiente com PHP compatível. A inclusão deste documento não comprova execução bem-sucedida.
