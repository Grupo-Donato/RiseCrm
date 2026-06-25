# Fase 2B — Implementação (Catálogo, produtos, serviços, preços e recursos)

**Status:** CONCLUÍDA COM RESTRIÇÕES (não bloqueadoras).
**Plugin:** **0.3.0** (bump realizado somente após a homologação final).
**Schema:** 012 → **018** (marker 018).
**Ambiente homologado:** Rise 3.9.6 / CodeIgniter 4.6.3 / PHP 8.2.12 / MariaDB
10.4.32 / banco `rise_crm` / DBPrefix `rise_` / InnoDB / `utf8`/`utf8_general_ci`
/ UTC.

## Estado inicial
Versão 0.2.0, schema 001–012, marker 012, **114 PASS / 0 FAIL**, uninstall
preservando 12 tabelas, sistema legado inventariado (41 arquivos). Tudo confirmado antes
de qualquer alteração.

## O que foi implementado

### Versões de schema (013–018, imutáveis e idempotentes)
| Versão | Tabela lógica | Tabela física | Finalidade |
|---|---|---|---|
| 013 | `gd_product_categories` | `rise_gd_product_categories` | Categorias do catálogo (hierárquicas). |
| 014 | `gd_resources` | `rise_gd_resources` | Recursos físicos (quadras, espaços, equipamentos). |
| 015 | `gd_products` | `rise_gd_products` | Produtos e serviços comercializáveis. |
| 016 | `gd_product_variants` | `rise_gd_product_variants` | Variações de produto. |
| 017 | `gd_price_lists` | `rise_gd_price_lists` | Tabelas/contextos de preço. |
| 018 | `gd_prices` | `rise_gd_prices` | Valores por tabela/produto/variação/recurso. |

Convenções herdadas: `unit_id NOT NULL`, soft delete `deleted`, auditoria
`created_at/by`+`updated_at/by`, dinheiro `DECIMAL(15,2)`, quantidade
`DECIMAL(15,3)`, status em `VARCHAR(30)` validado por PHP, JSON em `MEDIUMTEXT`.

**Unicidade normalizada** via colunas geradas `PERSISTENT` (valor só quando
`deleted=0`, e para padrões quando `status='active' AND is_default=1`) com
`UNIQUE` sobre elas — aproveitando que o MariaDB permite múltiplos `NULL`:
`active_code_key` (categorias/recursos/produtos/listas), `active_code_key` por
produto (variações), `default_variant_key`, `default_list_key`,
`active_scope_key` (preços).

### Models (`Models/`)
`Gd_product_categories_model`, `Gd_resources_model`, `Gd_products_model`,
`Gd_product_variants_model`, `Gd_price_lists_model`, `Gd_prices_model` — todos
estendem `Gd_Model` (soft delete, escopo de unidade, paginação server-side,
whitelist de ordenação, filtro de detalhe por ID e joins defensivos por unidade
e soft delete). Variações e listas têm
`mark_as_default()` transacional (`GET_LOCK`). Preços expõem `overlapping()` e
`resolution_candidates()`.

### Services (`Services/`)
`ProductCategoryService`, `ResourceService`, `ProductService`,
`ProductVariantService`, `PriceListService`, `PricingService` e
`CatalogDuplicateDetectionService`. Base comum `CatalogDataService` (estende
`CustomerDataService`) com `assert_area/assert_cost_center/assert_category`. Toda
regra de negócio fica nos services; unidade resolvida no backend; auditoria
centralizada; `DataNormalizationService::json()`, `::decimal()` e
`::decimalCompare()` para validar e comparar JSON/dinheiro sem float.

### Controllers (`Controllers/`) e rotas
`Product_categories`, `Products`, `Product_variants`, `Resources`, `Price_lists`,
`Prices` — finos, no padrão da Fase 2A (CSRF pelo grupo, `access->require`,
`{success,message,data}`). GET para páginas/detalhe; POST para
`list_data`/`modal_form`/`save`/`delete`. Endpoints sob `catalog/*`,
`resources/*`, `pricing/*`, incluindo `pricing/resolve` (leitura) e
`pricing/resolver` (página da ferramenta). Ver [menu-and-routes.md](menu-and-routes.md).

### Permissões e menu
Novas chaves nativas: `gd_catalog_view`, `gd_products_manage`,
`gd_product_categories_manage`, `gd_resources_view`, `gd_resources_manage`,
`gd_price_lists_view`, `gd_price_lists_manage`, `gd_prices_manage` (com
implicações manage→view e leituras adicionais para preços). Menu “Grupo Donato”
ganha **Produtos e serviços**, **Recursos** e **Tabelas de preço**, filtrados por
permissão, sem alterar o core.

### Views
Listas (DataTables server-side/client-side) + modais (`appForm`) + detalhes para
produtos (com variações e preços vigentes), recursos, tabelas de preço (com a
grade de preços) e a ferramenta de resolução. `attributes`/`metadata` editados
como textarea JSON validado.

### Seeds (`CatalogSeeder`)
Q2–Q6 (quadras reais) e uma tabela padrão `DEFAULT` por unidade. Idempotentes,
sem dados comerciais fictícios. Chamado no `gd_install` e no `cli.php install`.

## Testes
Self-test ampliado para **207 PASS / 0 FAIL** (a linha de base desta continuação
era 193/0; a Fase 2A havia encerrado em 114/0). Novos grupos: schema
013–018, seeds Q2–Q6/tabela padrão (idempotentes), categorias, recursos,
produtos, variações, tabelas de preço, preços, **resolução por precedência**,
segurança/escopo, detalhe por ID, referências inativas sem fallback, precisão
decimal, vigência, invariantes de banco sob concorrência, permissões e rotas.
Regressão: Fases 1 e 2A verdes, sequências 100/100 distintas, uninstall
preservando **18** tabelas, sistema legado inalterado, lint 127 arquivos / 0 erros.

## Limites desta fase (fora de escopo)
Sem estoque/saldo, compras, fornecedores, pedidos, venda/PDV, comandas/bar,
matrículas/turmas/presença, contratos/cobranças/pagamentos/caixa, reservas/agenda/
disponibilidade, eventos, campeonatos, pacotes de crédito, fiscal/impostos,
importação de planilhas e integrações externas. O que permanece para essas fases:
quantidade disponível (estoque), ocupação de recursos (agenda) e geração de
valores a receber (financeiro).

## Restrições não bloqueadoras (herdadas/novas)
- Sem automação de console JS no smoke web (verificação por HTTP).
- Sem ACL por usuário×unidade (mantido da Fase 1/2A).
- Tipos de produto `credit`/`discount` reservados (rejeitados no backend até
  haver uso operacional seguro).
- Concorrência de troca de padrão/preço validada por invariantes de banco
  (índices únicos + `GET_LOCK`) em processo; harness paralelo dedicado fica como
  melhoria futura não bloqueadora.
- O log de startup do MariaDB contém alertas InnoDB de LSN herdados do ambiente.
  Não houve erro novo durante a homologação e `CHECK TABLE` foi OK em todo
  `rise_crm`/plugin/stats, mas backup e saneamento do host são recomendados antes
  de produção ou novo restart.

## Confirmações explícitas
Nenhum arquivo do sistema legado foi alterado. Nenhum arquivo-fonte do core do Rise foi
alterado. Não foram implementados estoque, venda/PDV, agenda/reserva,
contrato/cobrança/pagamento. Nenhum preço comercial fictício foi seedado. Q2–Q6
foram cadastradas sem inventar capacidade ou valor. Nenhuma exclusão física nos
fluxos de domínio. O uninstall continua preservando dados (agora 18 tabelas). A
próxima fase não foi iniciada.
