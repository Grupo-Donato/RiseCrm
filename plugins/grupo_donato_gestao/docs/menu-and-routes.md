# Menus, rotas e controllers

## Menu real até a Fase 3B2

`app_filter_staff_left_menu` adiciona “Grupo Donato” somente quando há permissão. O submenu direto contém Visão geral, Contas de clientes, Pessoas, Produtos e serviços, Recursos, Tabelas de preço e/ou Configurações. A navegação interna oferece Geral, Unidades, Áreas de negócio, Centros de resultado e Auditoria conforme as chaves do papel. Categorias ficam dentro de Produtos; preços e resolver ficam dentro de Tabelas de preço.

O menu inclui Agenda, Reservas e Séries de reservas conforme permissões. Não existem itens de escola, financeiro, bar/PDV, estoque, eventos ou campeonatos.

## Rotas reais

Prefixo `/grupo_donato`, namespace `grupo_donato_gestao\Controllers`, filtro `csrf`.

| Método | Rota | Controller |
|---|---|---|
| GET | `/`, `/dashboard` | `Dashboard::index` |
| GET | `/settings`, `/settings/general` | `Settings` |
| POST | `/settings/general/save` | `Settings::save_general` |
| GET | `/settings/units` | `Units::index` |
| POST | `/settings/units/{list_data,modal_form,save,delete}` | `Units` |
| GET | `/settings/business-areas` | `Business_areas::index` |
| POST | `/settings/business-areas/{list_data,modal_form,save,delete}` | `Business_areas` |
| GET | `/settings/cost-centers` | `Cost_centers::index` |
| POST | `/settings/cost-centers/{list_data,modal_form,save,delete}` | `Cost_centers` |
| GET | `/audit` | `Audit::index` |
| POST | `/audit/{list_data,view}` | `Audit` |
| GET | `/customers`, `/customers/view/{id}` | `Customer_accounts` |
| POST | `/customers/{list_data,modal_form,save,delete,duplicates}` | `Customer_accounts` |
| GET | `/people`, `/people/view/{id}` | `People` |
| POST | `/people/{list_data,modal_form,save,delete,duplicates}` | `People` |
| POST | `/account-people/{list_data,modal_form,save,delete}` | `Account_people` |
| POST | `/contacts/{list_data,modal_form,save,delete}` | `Contact_methods` |
| POST | `/addresses/{list_data,modal_form,save,delete}` | `Addresses` |
| GET | `/catalog/products`, `/catalog/products/view/{id}` | `Products` |
| POST | `/catalog/products/{list_data,modal_form,save,delete}` | `Products` |
| GET | `/catalog/categories` | `Product_categories` |
| POST | `/catalog/categories/{list_data,modal_form,save,delete}` | `Product_categories` |
| POST | `/catalog/variants/{list_data,modal_form,save,delete}` | `Product_variants` |
| GET | `/resources`, `/resources/view/{id}` | `Resources` |
| POST | `/resources/{list_data,modal_form,save,delete}` | `Resources` |
| GET | `/pricing/lists`, `/pricing/lists/view/{id}` | `Price_lists` |
| POST | `/pricing/lists/{list_data,modal_form,save,delete}` | `Price_lists` |
| GET | `/pricing/resolver` | `Prices::resolver` |
| POST | `/pricing/prices/{list_data,modal_form,save,delete,variants}` | `Prices` |
| POST | `/pricing/resolve` | `Prices::resolve` (somente leitura) |

Não há rota genérica de gravação, rota pública, endpoint de auditoria mutável ou endpoint de fase futura. Todo o grupo usa filtro CSRF; controllers repetem leitura/gestão conforme [permissions-plan.md](permissions-plan.md), e IDs são revalidados na unidade ativa.

## Views e DataTables

Contas, pessoas e auditoria usam paginação server-side; consultas aplicam limite e whitelist de ordenação. Seções filhas usam paginação limitada e ordem fixa. Modais usam `form_open`/`appForm`, POST, CSRF e resposta `{success,message,...}`. Saída originada no banco é escapada antes de compor HTML.

## Agenda — Fase 3A

O submenu `Agenda` aparece com `gd_calendar_view` e aponta para `GET grupo_donato/calendar`. O feed usa `GET grupo_donato/calendar/events`. Páginas por recurso usam `GET resources/availability/{id}`, `exceptions/{id}` e `blocks/{id}`. Listas/modais/saves/deletes são POST; todas as escritas usam CSRF e permissões de gestão específicas. As listas temporais são server-side, escopadas e ordenadas por whitelist.

## Reservas — Fase 3B1

O submenu Reservas exige `gd_bookings_view`. GETs: lista, modal e detalhe. POSTs: list-data, save, delete, check-availability, seletores de cliente/contato e transições confirm/start/complete/cancel/no-show. Não existe endpoint de expiração nem rota pública.

## Séries — Fase 3B2

O submenu exige `gd_booking_series_view`. GETs: lista, detalhe e modal. POSTs: list-data, modal de ocorrência, preview, save, generate, pause, resume, complete, cancel, alterações por escopo e cancelamentos. Todas as escritas usam CSRF e autorização backend; não existe rota pública ou financeira.
