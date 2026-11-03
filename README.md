# EBD Planejador

Planejamento de aulas, referências, dinâmicas e materiais de estudo.

## Implementado na primeira versão

**PHP — núcleo executável de domínio.** Monta uma sequência de blocos da aula, calcula início e fim de cada etapa e rejeita um roteiro maior que o tempo disponível. Valida que os blocos formam uma lista e que `titulo` e `referencia` são textos, evitando estruturas inesperadas no resultado.

## Como executar

Instale PHP 8.3 CLI. Não há dependências Composer. Os comandos abaixo partem da raiz do repositório.

```sh
php main.php 90 examples/aula.json
```

O arquivo da aula deve conter uma lista JSON. Cada bloco exige `titulo` (texto não vazio) e `minutos` (inteiro positivo); `referencia` é opcional, mas precisa ser texto quando informada.

## Testes

```sh
php main.php --self-test
```

Os testes cobrem invariantes e entradas inválidas. A preparação local não dispõe deste runtime; o workflow em `.github/workflows/ci.yml` executa a validação no GitHub Actions. O resultado de cada execução está na aba Actions, associado ao commit.

## Organização e limites

Código e exemplos: `main.php e examples/aula.json`. Os dados de exemplo são fictícios. As funções de domínio são separadas da leitura de argumentos no mesmo arquivo para manter a primeira versão pequena e revisável.

Planejamento de tempo e referências informadas pelo professor; não contém material licenciado da CPAD. Editor, exportação de apostila, Laravel, Blade e MariaDB ainda estão planejados.

A proposta completa de evolução está em `ARQUITETURA.md`. As tecnologias propostas nessa arquitetura não devem ser interpretadas como dependências já implementadas.

## Próximas entregas

- [ ] Planejar sequência da aula — ampliar o núcleo atual até o fluxo completo.
- [ ] Organizar referências e dinâmicas — ampliar o núcleo atual até o fluxo completo.
- [ ] Exportar material autoral para os alunos — ampliar o núcleo atual até o fluxo completo.

Licença: ainda não definida.
