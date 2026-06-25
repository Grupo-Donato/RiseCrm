# Permissões

## Modelo implementado nas Fases 1, 2A e 2B

A autorização usa autenticação/staff do `Security_Controller`, chaves planas persistidas em `roles.permissions` e checagem central no `AccessService`. Admin tem tudo. Uma chave `*_manage` implica a respectiva `*_view`, tanto ao salvar o papel quanto ao avaliar acesso.

| Chave | Efeito |
|---|---|
| `gd_dashboard_view` | dashboard técnico |
| `gd_settings_view` / `gd_settings_manage` | visualizar/alterar configurações gerais |
| `gd_units_view` / `gd_units_manage` | listar/gerenciar unidades |
| `gd_business_areas_view` / `gd_business_areas_manage` | listar/gerenciar áreas |
| `gd_cost_centers_view` / `gd_cost_centers_manage` | listar/gerenciar centros |
| `gd_audit_view` | listar/ver auditoria |
| `gd_customer_accounts_view` / `gd_customer_accounts_manage` | visualizar/gerenciar contas |
| `gd_people_view` / `gd_people_manage` | visualizar/gerenciar pessoas |
| `gd_customer_relations_manage` | criar, editar e encerrar relações |
| `gd_contacts_manage` | gerenciar contatos das pessoas |
| `gd_addresses_manage` | gerenciar endereços das contas |
| `gd_catalog_view` | visualizar categorias, produtos e variações |
| `gd_products_manage` | gerenciar produtos e variações; implica catálogo |
| `gd_product_categories_manage` | gerenciar categorias; implica catálogo |
| `gd_resources_view` / `gd_resources_manage` | visualizar/gerenciar recursos físicos |
| `gd_price_lists_view` / `gd_price_lists_manage` | visualizar/gerenciar tabelas de preço |
| `gd_prices_manage` | gerenciar preços e usar o resolver; implica leitura de catálogo, recursos e listas |

`PermissionsRegistrar` renderiza checkboxes em `app_hook_role_permissions_extension` e persiste em `app_filter_role_permissions_save_data`. As chaves chegam ao usuário por `$this->login_user->permissions`. Menu, abas, páginas, modais e endpoints de escrita verificam permissão separadamente.

Como relações, contatos e endereços não possuem chave `view` própria, seus `manage`
implicam a leitura mínima dos cadastros pais: relações → contas+pessoas; contatos →
pessoas; endereços → contas. Essa implicação vale no salvamento do papel e no backend.

Na Fase 2B, `products_manage` e `product_categories_manage` implicam
`catalog_view`; `resources_manage` implica `resources_view`;
`price_lists_manage` implica `price_lists_view`; `prices_manage` implica as três
leituras necessárias. O menu usa o mesmo `AccessService` dos controllers, de
modo que implicações diretas e adicionais não divergem. GETs de página/detalhe
exigem leitura; POSTs de lista/modal/save/delete repetem autorização e CSRF. O
resolver é somente leitura e exige acesso às tabelas de preço.

## Unidade

Não existe `gd_usuario_unidade`. A unidade ativa validada no backend é o único escopo permitido; `unit_id` de POST/GET/URL é ignorado. Nesta fase não existe compartilhamento de pessoas nem ACL usuário×unidade adicional.

## Testes homologados

Checkboxes apareceram na tela real de papel e `gd_units_manage` foi persistida junto de `gd_units_view`. Admin, staff autorizado, manage→view e staff sem permissão foram exercitados. A URL direta sem permissão redirecionou para `forbidden`; POSTs repetem a autorização.

## Futuro

Papéis comerciais, permissões de clientes, escola, financeiro, bar, estoque, eventos e ACL usuário×unidade dependem de validação do cliente e de fases futuras. Não foram seedados papéis nem respostas comerciais.

## Fase 3A

| Chave | Efeito |
|---|---|
| `gd_calendar_view` | visualizar calendário, regras, exceções e bloqueios |
| `gd_resource_availability_manage` | gerir regras e exceções; implica calendário e `gd_resources_view` |
| `gd_resource_blocks_manage` | gerir bloqueios; implica calendário e `gd_resources_view` |

Nenhuma permissão temporal implica `gd_resources_manage`. Toda autorização é repetida no backend e limitada à unidade ativa.

## Fase 3B1

| Chave | Efeito |
|---|---|
| `gd_bookings_view` | lista, detalhe e dados identificados da reserva no calendário |
| `gd_bookings_manage` | criar/editar/soft-delete quando permitido; implica leituras de agenda, recursos, contas e pessoas |
| `gd_booking_status_manage` | confirmar, iniciar, concluir, cancelar e no-show; implica bookings view e calendário |

`gd_bookings_manage` não concede gestão de clientes/recursos. `gd_booking_status_manage` também não concede essas gestões. Operador apenas com manage cria hold ou pending; criação confirmed exige status manage.

## Fase 3B2

| Chave | Efeito |
|---|---|
| `gd_booking_series_view` | lista, detalhe, ocorrências, exceções e histórico |
| `gd_booking_series_manage` | criar, preview, gerar e alterar; implica apenas leituras necessárias |
| `gd_booking_series_status_manage` | pausar, retomar, encerrar e cancelar |

As permissões de série implicam leitura de reservas, calendário, recursos, contas e pessoas, mas nunca gestão desses cadastros.
