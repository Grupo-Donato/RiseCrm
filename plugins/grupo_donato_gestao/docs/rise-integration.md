# Integração com o Rise CRM

> Todos os fatos abaixo foram **verificados diretamente no código-fonte** da
> instalação em `c:\xampp\htdocs\rise` (restrição #12: não inventar convenções).
> Caminhos de arquivo são relativos à raiz do Rise.

## 1. Ambiente

| Item | Valor | Fonte |
|------|-------|-------|
| Versão do Rise | **3.9.6** | `app/Config/Rise.php` → `app_version` |
| Framework | **CodeIgniter 4.6.3** | `system/CodeIgniter.php` → `CI_VERSION` |
| `DBPrefix` | **`rise_`** | `app/Config/Database.php` |
| Coluna de soft-delete | **`deleted`** (`tinyint(1)`, default `0`) | `app/Models/Crud_model.php` |
| Diretório de plugins | `plugins/` (constante `PLUGINPATH` em `app/Config/Events.php`) | verificado |
| Diretório de arquivos | `files/` | verificado |
| Idioma de referência | `portuguese` | usado pelo sistema legado |

## 2. Ciclo de vida de um plugin

Plugins ativos são listados em `app/Config/activated_plugins.json` (array JSON de nomes
de pasta). O carregamento ocorre em `app/Config/Events.php::load_plugin_indexes()`, que
faz `require` do `plugins/<pasta>/index.php` de cada plugin ativo.

### Cabeçalho de metadados (obrigatório no `index.php`)

```php
/*
Plugin Name: Grupo Donato — Gestão
Description: Gestão integrada do complexo esportivo e de eventos.
Version: 0.1.0
Requires at least: 3.9.6
Author: ...
*/
```

`get_plugin_meta_data()` em `app/Helpers/plugin_helper.php` extrai esses campos por regex.

### Hooks de ciclo de vida (`app/Helpers/plugin_helper.php`)

| Função | Hook disparado | Quando |
|--------|----------------|--------|
| `register_installation_hook($plugin, $cb)` | `app_hook_install_plugin_$plugin` | instalação (recebe purchase code) |
| `register_activation_hook($plugin, $cb)` | `app_hook_activate_plugin_$plugin` | ativação |
| `register_deactivation_hook($plugin, $cb)` | `app_hook_deactivate_plugin_$plugin` | desativação |
| `register_uninstallation_hook($plugin, $cb)` | `app_hook_uninstall_plugin_$plugin` | desinstalação |
| `register_update_hook($plugin, $cb)` | `app_hook_update_plugin_$plugin` | atualização |

> **Atenção (lição do sistema legado):** o `$plugin` passado para `register_installation_hook`
> deve **bater com o nome da pasta** ativada em `activated_plugins.json`. O sistema legado
> registra `"plugin_legado"` mas a pasta é `módulo legado (removido)`
> — fonte de bugs de instalação. Nosso plugin usará a pasta **`grupo_donato_gestao`**
> e registrará exatamente esse nome.

`app/Controllers/Rise_plugins.php` gerencia upload do zip, validação (exige `index.php`),
e troca de status (`indexed` → `installed` → `activated`/`deactivated`).

## 3. Sistema de hooks (estilo WordPress)

Implementado em `app/ThirdParty/PHP-Hooks/php-hooks.php` (classe `Hooks`), acessado via
`app_hooks()` (global `$hooks`).

```php
app_hooks()->add_action($tag, $fn, $priority = 10, $accepted_args = 1);
app_hooks()->do_action($tag, $arg = '');
app_hooks()->add_filter($tag, $fn, $priority = 10, $accepted_args = 2);
app_hooks()->apply_filters($tag, $value);
```

### Hooks de UI relevantes para o plugin

| Hook | Tipo | Uso |
|------|------|-----|
| `app_filter_staff_left_menu` | filter | injeta itens no menu lateral do staff |
| `app_filter_client_left_menu` | filter | injeta itens no menu lateral do cliente/portal |
| `app_filter_admin_settings_menu` | filter | adiciona abas em Configurações |
| `app_filter_dashboard_widgets` | filter | adiciona widgets ao dashboard |
| `app_filter_app_csrf_exclude_uris` | filter | URIs isentas de CSRF (formulários públicos) |
| `app_filter_action_links_of_<plugin>` | filter | links de ação no gerenciador de plugins |

### Hooks de dados (CRUD) — disparados pelo `Crud_model`

| Hook | Payload |
|------|---------|
| `app_hook_data_insert` | `["id","table","table_without_prefix","data"]` |
| `app_hook_data_update` | `["id","table","table_without_prefix","data"]` |
| `app_hook_data_delete` | `["id","table","table_without_prefix"]` |
| `app_filter_data_before_insert` | filtro sobre os dados antes do insert |

Helpers de conveniência: `register_data_insert_hook()`, `register_data_update_hook()`,
`register_data_delete_hook()`, `register_before_insert_filter_hook()`.

> Estes hooks são a base do **módulo de Auditoria** do novo plugin: um único listener
> pode capturar mutações financeiras automaticamente (ver `docs/decisions.md`).

### Menu — formato do item

```php
$sidebar_menu["gd_escola"] = [
    "name" => "Escola",                       // ou chave de idioma
    "url" => get_uri("grupo_donato_gestao/escola"),
    "class" => "award",                       // ícone Feather
    "is_custom_menu_item" => true,
    "position" => 12,
    // "submenu" => [...], "is_active_menu" => 1
];
```

## 4. Autenticação, usuário atual e permissões

- `app/Controllers/App_Controller.php` é a base; `app/Controllers/Security_Controller.php`
  impõe login. Controllers do plugin **devem estender `Security_Controller`** (área staff)
  para herdar a checagem de login.
- Usuário logado: `$this->Users_model->login_user_id()` (lê sessão `user_id`);
  `$this->login_user = $this->Users_model->get_access_info($login_user_id)`.
- `$this->login_user->is_admin` (bool), `$this->login_user->user_type` (`staff`/`client`),
  `$this->login_user->permissions` (array desserializado).
- Métodos de guarda: `access_only_admin()`, `access_only_team_members()`,
  `access_only_allowed_members()`.
- Permissões de papel são armazenadas serializadas e checadas por módulo via
  `init_permission_checker($module)` + `get_access_level_for_*`.

> O plugin combinará as **permissões nativas do Rise** (papéis/team members) com um
> **escopo por unidade e por área de negócio próprio** (ver `docs/permissions-plan.md`).
> Diferente do sistema legado, as checagens não ficarão espalhadas no controller: serão
> centralizadas num `AccessService`.

## 5. Banco de dados e o `Crud_model`

`app/Models/Crud_model.php` (estende `CodeIgniter\Model`) é o **padrão obrigatório** para
todos os models do plugin:

```php
class Gd_xxx_model extends Crud_model {
    function __construct() {
        $this->table = 'gd_xxx';            // vira rise_gd_xxx
        parent::__construct($this->table);
    }
}
```

Métodos herdados principais:

| Método | Comportamento |
|--------|---------------|
| `get_one($id)` | 1 registro por id (objeto vazio se não existir) |
| `get_one_where($where)` | 1 registro por critério |
| `get_all_where($where, $limit, $offset, $sort_by, $select)` | lista (filtra `deleted=0`) |
| `ci_save(&$data, $id = 0)` | insert (id=0) ou update; retorna id |
| `delete($id, $undo = false)` | **soft delete** (`deleted=1`) / restaurar |
| `delete_permanently($id)` | hard delete |
| `get_dropdown_list($fields, $key, $where)` | dados para `<select>` |

> **Convenção de schema do plugin:** toda tabela tem `id` PK auto-incremento, `deleted`
> `tinyint(1)` default 0, e (quando fizer sentido) `created_at`/`updated_at`,
> `created_by`/`updated_by`. Money como `decimal(15,2)` (decisão própria — ver
> `docs/decisions.md`; o core do Rise usa `double`).

### Migrations: o Rise **não usa** CI4 Migrations para plugins

Verificado em `plugins/módulo legado (removido)/index.php`: o schema é criado com
**SQL idempotente bruto** dentro do hook de instalação:

```php
if (!$db->tableExists($table_name)) {
    $db->query("CREATE TABLE IF NOT EXISTS `$table_name` ( ... )");
}
// evolução incremental:
$col = $db->query("SHOW COLUMNS FROM `$table_name` LIKE 'nova_coluna'")->getRow();
if (!$col) { $db->query("ALTER TABLE `$table_name` ADD `nova_coluna` ..."); }
```

`register_installation_hook("plugin_legado", "bombeiros_install_or_update")`
é a última linha do `index.php`. **Nosso plugin seguirá o mesmo mecanismo**, porém com
um **instalador modular** (um arquivo de schema por módulo) em vez de uma função gigante
(ver `docs/migration-strategy.md`).

## 6. Rotas

Carregadas via `require __DIR__ . "/Config/Routes.php"` dentro do `index.php`
(guardado por `if (function_exists("service"))`). Padrão:

```php
$routes->group("grupo_donato_gestao",
    ["namespace" => "Grupo_donato_gestao\\Controllers"], function ($routes) {
        $routes->get("escola", "Escola::index");
        $routes->post("escola/turmas/list_data", "Turmas::list_data");
        // ...
});
```

Formulários públicos (sem login) usam um grupo separado e devem ter a URI adicionada em
`app_filter_app_csrf_exclude_uris`.

## 7. AJAX, CSRF, Views e Modais

- CSRF (`app/Config/Security.php`): token `rise_csrf_token`, header `X-CSRF-TOKEN`,
  cookie `rise_csrf_cookie`, método `cookie`. O JS do Rise injeta o token nos POSTs.
- Views: `Template->view($view, $data)` (fragmento) e `Template->render(...)` (página
  completa com layout). Modais carregam por `data-act="ajax-modal"`; ações por
  `data-act="ajax-request"` (helpers `modal_anchor()`, `ajax_anchor()`).
- Listas usam DataTables; models expõem `list_data`/`get_details` para alimentar a tabela.

## 8. Traduções, Uploads, Cron, Logs

- **Traduções:** `app_lang('chave')`. Arquivos em `Language/<idioma>/default_lang.php`
  dentro do plugin (o sistema legado usa `Language/portuguese/default_lang.php`).
- **Uploads:** `app/Helpers/app_files_helper.php` → `upload_file_to_temp()`,
  `move_temp_file()`, `delete_app_file()`; arquivos em `files/`. Hooks
  `app_hook_upload_file_to_temp`, `app_filter_move_temp_file`, `app_hook_delete_app_file`.
- **Cron:** ponto de entrada `app/Controllers/Cron.php` (intervalo mínimo 300s); hook
  `app_hook_after_cron_run` permite ao plugin pendurar jobs (geração de cobranças
  recorrentes, lembretes, fechamento de caixa, expiração de bloqueios).
- **Logs/erros:** `log_message('error'|'notice', ...)` e `Activity_logs_model` para
  trilha de atividade nativa.

## 9. Reutilização do núcleo do Rise

O Rise já modela conceitos próximos. O plugin deve **integrar/seguir** estes padrões
(ver `docs/reuse-vs-rebuild.md` para a decisão final de cada um):

| Conceito Donato | Núcleo Rise candidato | Observação |
|-----------------|-----------------------|------------|
| Pessoas/clientes | `clients` (`type` org/person), `users` (contatos) | base de identidade |
| Catálogo | `items`, `item_categories`, `taxes` | produtos/serviços/uniformes |
| Faturamento | `invoices`, `invoice_items`, `invoice_payments` | suporta pagamento parcial (1 fatura : N pagamentos) |
| PDV/Pedidos | `orders`, `order_items`, `order_status` | base para Bar/PDV |
| Despesas | `expenses`, `expense_categories` | contas a pagar/custos |
| Campos extras | `custom_fields`, `custom_field_values` | extensibilidade |
| Configurações | `settings` (`get_setting`/`save_setting`) | parâmetros e sequências |
| Moeda/datas | helpers `to_currency`, `format_to_date`, `convert_date_*` | UTC no banco, local na exibição |

> **Decisão arquitetural-chave:** o domínio do Donato é mais rico que o núcleo do Rise
> (agenda de quadras com recorrência, créditos de aula, rateio por centro de resultado,
> comandas de bar, caução de eventos). Por isso o plugin terá **tabelas próprias** para
> o domínio, **referenciando** entidades do Rise (ex.: `clients.id`, `items.id`,
> `users.id`) onde houver ganho, sem duplicar identidade. Ver `docs/decisions.md` D-02.
