# EBD Planejador

Ferramenta local e sem dependências externas para organizar aulas de Escola Bíblica Dominical, distribuir minutos, preparar materiais e exportar roteiros. **Não baixa nem reproduz conteúdo licenciado de revistas.** Os exemplos são autorais.

## Requisitos

PHP 8.3 ou superior. O planejamento funciona sem Composer, banco de dados ou acesso à internet.

## Uso rápido

A interface original continua funcionando, sem quebra de compatibilidade:

```sh
php main.php 90 examples/aula.json
php main.php --self-test
```

A nova interface trabalha com objetos completos de aula:

```sh
php planner.php examples/aula-completa.json
php planner.php examples/aula-completa.json --format=markdown
php planner.php examples/aula-completa.json --format=student
php planner.php examples/aula-completa.json --format=csv
```

Para uma série de aulas, passe um JSON com uma lista de objetos no mesmo formato do exemplo:

```sh
php planner.php examples/serie.json --series
```

A saída é enviada para stdout; para salvar um arquivo, redirecione explicitamente: `php planner.php examples/aula-completa.json --format=markdown > roteiro.md`. O programa não escreve em arquivos por conta própria.

## Modelo de entrada

`titulo` (texto), `duracao` (minutos inteiros, de 1 a 480) e `blocos` (lista não vazia) são obrigatórios. São opcionais: `inicio` (HH:MM, padrão 09:00), `data` (AAAA-MM-DD), `turma` e `objetivo`.

Cada bloco precisa de `titulo` e `minutos` (inteiro positivo). Também aceita `tipo`, `referencia`, `descricao` e `materiais` (lista de textos). Os tipos permitidos são `introducao`, `estudo`, `dinamica`, `aplicacao`, `conclusao` e `intervalo`. O tipo padrão é `estudo`.

A validação rejeita aulas que ultrapassem o horário disponível ou a meia-noite, datas inexistentes, campos de tipos incorretos, textos com caracteres de controle e listas de materiais inválidas. A série recusa datas duplicadas quando informadas. Não há interpretação automática do texto bíblico.

## Saídas

- **JSON:** dados estruturados, horários absolutos e relativos, contagem de minutos por tipo, referências, materiais e alertas de planejamento.
- **Markdown:** roteiro do professor, com tabela de horários, orientações, checklist e alertas.
- **Student:** resumo para alunos com objetivo, etapas e referências; omite as notas internas e os materiais de preparação.
- **CSV:** tabela de horários compatível com planilhas; campos iniciados por caracteres de fórmula são neutralizados.

A função pura `Ebd\Planner::build()` pode ser chamada em outros sistemas PHP sem depender da CLI. `Ebd\Planner::series()` consolida até 52 aulas. Nenhuma exportação executa código recebido nos dados.

## Testes

```sh
php -l main.php
php -l planner.php
php -l src/Planner.php
php -l tests/test_planner.php
php main.php --self-test
php tests/test_planner.php
```

A suíte inclui 26 cenários de domínio, entrada inválida, exportações, segurança de CSV e CLI, além do teste original. O GitHub Actions executa a mesma verificação em pushes e PRs.

## Organização

```text
main.php                 Interface original compatível
planner.php              Nova CLI
src/Planner.php          Serviço de domínio e exportadores
tests/test_planner.php   Testes sem dependências externas
examples/                Entradas fictícias e autorais
.github/workflows/ci.yml Validação automatizada
```

## Limites

O aplicativo organiza informações fornecidas pelo professor; não valida teologicamente as referências, não oferece login, banco de dados, calendário compartilhado ou edição web. Os textos de exemplo não representam material oficial da CPAD. A arquitetura futura está em `ARQUITETURA.md`.
