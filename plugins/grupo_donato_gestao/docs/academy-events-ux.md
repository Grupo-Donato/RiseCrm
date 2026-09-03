# GD Academy Eventos — UX e navegação

## Hierarquia

`GD Academy > Eventos > Evento > Categoria > Partida/Avaliação`

`gd_tab=eventos` é a entrada de listagem. O detalhe do evento deixou de ser um workspace monolítico e passou a utilizar páginas profundas com objetivo operacional único.

## URLs

- `grupo_donato/operacional?gd_tab=eventos`: lista e busca de eventos.
- `grupo_donato/operacional/evento/{id}`: resumo do evento.
- `grupo_donato/operacional/evento/{id}/categorias`: categorias.
- `grupo_donato/operacional/evento/{id}/financeiro`: financeiro.
- `grupo_donato/operacional/evento/{id}/checklist`: checklist.
- `grupo_donato/operacional/evento/{id}/configuracoes`: dados administrativos.
- `grupo_donato/operacional/evento/{id}/categoria/{id}`: resumo da categoria.
- `.../convocacao`, `.../partidas`, `.../avaliacoes`, `.../estatisticas`: subtelas da categoria.
- `.../partida/{id}`: resumo da partida; `.../escalacao` e `.../estatisticas` são subtelas.
- `.../avaliacao/{participant_id}`: ficha individual do atleta.

As abas são links reais. Cada endpoint monta somente o read model da seção solicitada; o contrato de escrita continua nos endpoints existentes do `AcademyEventService`.

## Regras de interface

- Breadcrumb contextual em todas as subtelas.
- Links de abertura em nova aba usam escolha explícita do usuário e `rel="noopener"`.
- Estados vazios explicam o próximo passo.
- Listas de convocação, escalação e checklist viram cards no mobile.
- Financeiro, avaliação, checklist e configurações não são renderizados na mesma página do resumo.
