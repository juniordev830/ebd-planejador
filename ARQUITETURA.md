# Arquitetura — EBD Planejador

## Objetivo

Planejar aulas de Escola Bíblica Dominical com referências, cronograma, materiais, saídas para professor e aluno e integração **opcional** com uma base bíblica offline.

## Estado atual

Aplicação funcional em **PHP 8.3+**, sem Composer, banco de dados ou framework. A proposta antiga de Laravel e MariaDB não faz parte do código atual.

| Camada | Arquivo | Responsabilidade |
| --- | --- | --- |
| Interface legada | `main.php` | Planejamento simples compatível |
| Interface principal | `planner.php` | Importação de aulas em JSON e exportação |
| Domínio | `src/Planner.php` | Validação, cronograma, resumos e relatórios |
| Séries | `src/SeriesInsights.php` | Consolidação e análise de calendário |
| Adaptador externo | `src/BibleData.php` | Leitura de um corpus local sem rede |
| Testes | `tests/` | Casos de domínio, CLI e integração |
| Dados opcionais | `data/bible-data/` | Checkout local da base importada, ignorado pelo Git |

## Integração dos projetos

- **Origem dos dados:** `juniordev830/adc-de-fuc-ebd`, com tradução organizada em `bolls/<traducao>/<livro>/<capitulo>.json`.
- **Aplicação de destino:** `juniordev830/ebd-planejador`, com novo adaptador `BibleData`.
- **Instalação:** script opcional de sparse checkout de uma tradução. O repositório do planejador não inclui os arquivos bíblicos nem modifica o repositório importado.
- **Contrato:** referência `Fp 4:4-6`, diretório local e código da tradução retornam uma lista de versículos. O leitor valida referências e conteúdo de arquivos.
- **Compatibilidade:** sem `--biblia-dir` o planejador mantém a experiência anterior. Com a opção, produz `textos_biblicos` no JSON e uma seção de leituras nos roteiros Markdown e student.
- **Licença:** como o corpus importado não informa licença, os dados não foram copiados para o histórico do planejador.

## Próximos passos possíveis

Separar o parser de referências por idiomas; considerar um índice de livros e disponibilidade de traduções; implementar integração web se houver necessidade real. Qualquer publicação de texto bíblico deve respeitar a licença da edição utilizada.
