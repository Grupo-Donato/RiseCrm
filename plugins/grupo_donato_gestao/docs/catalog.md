# Catálogo — produtos, categorias e variações (Fase 2B)

O catálogo central define **o que pode ser comercializado**. Nesta fase ele
**não** registra venda, cobrança, estoque, reserva, contrato ou consumo — apenas
o cadastro. Tudo é estritamente escopado por unidade (`unit_id` resolvido no
backend; nunca confiado ao navegador).

## Produto × Variação × Recurso

| Conceito | Tabela | Significa |
|---|---|---|
| **Produto** | `gd_products` | Algo que poderá ser vendido/cobrado (serviço, item físico, locação, taxa). |
| **Variação** | `gd_product_variants` | Uma variante de um produto (tamanho, sabor, volume). Existe nesta fase; **quantidade disponível é estoque (fase futura)**. |
| **Recurso** | `gd_resources` | Algo físico que poderá ser ocupado/reservado (quadra, salão). Ver [resources.md](resources.md). |

Um **recurso não é um produto**. Exemplo: o recurso `Quadra Q2` é distinto do
produto `Locação de quadra por hora`. Um preço pode, opcionalmente, ser
específico para um recurso (ver [pricing.md](pricing.md)).

## Categorias — `gd_product_categories`

Organizam produtos e serviços em hierarquia. Regras aplicadas no
`ProductCategoryService`:

- `code` e `name` obrigatórios; `code` único entre registros **não excluídos** da
  unidade (índice único normalizado `active_code_key`).
- `parent_id` deve pertencer à mesma unidade e não estar excluído.
- **Sem autorreferência** e **sem ciclos** (a cadeia de ancestrais é percorrida
  antes de salvar).
- Não permite exclusão lógica com **subcategoria ativa** ou **produto não
  arquivado** vinculado.
- Mudança de pai, mudança de status e exclusão são auditadas.

Categorias são administradas dentro de **Produtos e serviços** (botão “Categorias”).

## Produtos — `gd_products`

Campos principais: `code`, `name`, `description`, `product_type`, `billing_mode`,
`unit_of_measure`, flags (`track_stock`, `allows_variants`, `allows_discount`,
`requires_resource`), `status`, `category_id`, `business_area_id`,
`default_cost_center_id`, `rise_item_id`, `metadata`.

### Tipos (`product_type`)
`service`, `physical`, `rental`, `fee`, `other` na interface.
`credit` e `discount` ficam **reservados** (rejeitados no backend nesta fase para
não deixar comportamento ambíguo).

### Modos de cobrança (`billing_mode`)
`one_time`, `recurring`, `per_use`, `per_hour`, `per_day`, `per_person`,
`per_event`. **É apenas classificação** — não gera cobrança.

### Unidades de medida (`unit_of_measure`)
`unit`, `hour`, `day`, `month`, `session`, `person`, `event`, `package`, `other`.
`package` aqui é unidade de medida e **não** implementa pacote comercial.

### Status (`status`)
`draft`, `active`, `inactive`, `archived`. Produto em `draft` é administrável mas
não deve aparecer em seletores comerciais futuros.

### Regras (`ProductService`)
- `code` único ativo por unidade; `name` obrigatório.
- Enums validados; `product_type` restrito aos selecionáveis.
- Categoria/área/centro opcionais; quando informados devem pertencer à unidade e
  o centro deve ser compatível com a área.
- **Normalização de flags:** apenas produto `physical` pode `track_stock`
  (forçado a 0 nos demais).
- Desabilitar `allows_variants` é bloqueado se houver variação ativa.
- `rise_item_id` opcional, validado contra `items` do Rise (nunca cria item).
- `metadata` validada como JSON (TEXT/MEDIUMTEXT). Sem campos fiscais, custo
  médio, saldo ou quantidade disponível.
- Não exclui logicamente com **variação não arquivada** ou **preço ativo**.

`rise_item_id` é somente um vínculo opcional: o ID precisa existir em `items`,
mas o plugin não cria, atualiza nem exclui o item do Rise.

## Variações — `gd_product_variants`

Campos: `product_id`, `code`, `name`, `barcode`, `attributes` (JSON), `is_default`,
`sort_order`, `status` (`active`/`inactive`/`archived`).

### Regras (`ProductVariantService`)
- Produto deve pertencer à unidade, permitir variações e não estar arquivado.
- `code` único por produto entre não excluídos.
- `attributes` JSON válido quando preenchido.
- **Apenas uma variação padrão ativa por produto** — garantido por:
  - índice único normalizado `default_variant_key` (no banco);
  - troca transacional `mark_as_default()` com `GET_LOCK` (no serviço).
- Não exclui logicamente com **preço ativo**.

`barcode` **não** tem unique global rígido (decisão documentada): o mesmo código
de barras pode reaparecer entre produtos/variações sem bloqueio.

## Detecção assistiva de duplicidade

`CatalogDuplicateDetectionService` gera **alertas** (não mescla automaticamente):
- Categoria: mesmo código (bloqueado) / mesmo nome sob o mesmo pai (alerta).
- Produto: mesmo código (bloqueado) / mesmo nome+tipo (alerta) / mesmo
  `rise_item_id` (alerta).
- Variação: mesmo código no produto (bloqueado) / mesmos atributos normalizados
  (alerta).

Duplicidade exata de código é **bloqueada**. Alertas não exatos permitem override
confirmado, que é **auditado**.

## Limites da fase
Sem estoque/saldo, sem venda/PDV, sem cobrança, sem matrícula. A quantidade
disponível das variações e o custo médio pertencem à fase de estoque.

Todos os cadastros usam soft delete. IDs de unidade, autoria e estado de
exclusão não são aceitos do request; dependências ativas bloqueiam a exclusão
quando necessário. Os exemplos deste documento são conceituais, não seeds de
produtos reais.
