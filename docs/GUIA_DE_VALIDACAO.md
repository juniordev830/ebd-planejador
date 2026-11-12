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
