# EBD Planejador: guia técnico

## Escopo real

Ferramenta PHP de organização de aulas com exportações Markdown, CSV e JSON; não interpreta conteúdo bíblico.

## Arquivos relacionados

`main.php`, `planner.php`, `src/Planner.php`, `tests/test_planner.php`, `tests/test_insights.php`.

## Procedimento de validação

`php -l main.php`; `php main.php --self-test`; `php tests/test_planner.php`; `php tests/test_insights.php`.

Se algo não puder ser executado, registrar como pendente, sem afirmar sucesso. Preservar resultados reais de CI vinculados aos commits relevantes.

## Casos para revisão

Aula acima do tempo disponível, etapa sem aplicação, série com datas duplicadas, semana ISO e intervalo entre aulas; verificar escape de conteúdo não confiável nas saídas.

## Próxima evolução

Manter textos bíblicos ou conteúdo de editoras fora dos exemplos, salvo material devidamente licenciado. Ampliar testes de plano semanal antes de integrar calendário.

## Para submeter uma mudança

- [ ] Especificar problema e comportamento esperado.
- [ ] Anexar evidência de teste ou reprodução real.
- [ ] Revisar acessibilidade, privacidade e licenças quando aplicável.
- [ ] Submeter em branch separada e revisar antes de merge.

## Integração bíblica local (adc-de-fuc-ebd)

- Conferir que o `src/BibleData.php` é exclusivamente leitor e não faz chamadas HTTP.
- Executar `php tests/test_bible_data.php` para cobrir traduções, referências abreviadas e acentuadas, CSV incompatível, exportação JSON e Markdown, travessia de diretórios, JSON inválido e versículos duplicados.
- Os testes criam um corpus **sintético** em pasta temporária e não distribuem textos de traduções protegidas.
- Em uso local, instalar uma tradução somente após verificar seus termos com `bash scripts/instalar-base-biblica.sh ACF11`; não rodar esse instalador dentro do CI.
- Executar `php planner.php examples/aula-completa.json --biblia-dir=data/bible-data --traducao=ACF11 --format=markdown` após instalar os arquivos.
- Sem `--biblia-dir`, a CLI anterior deve manter exatamente seu comportamento; séries e CSV não utilizam a base.
- Não versionar `data/bible-data/` nem incorporar documentos sem licença no repositório do planejador.
