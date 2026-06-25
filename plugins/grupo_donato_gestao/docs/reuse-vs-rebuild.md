# Reaproveitar vs. Refazer

## 1. Do núcleo do Rise — REAPROVEITAR (integrar)

| Recurso Rise | Uso no plugin |
|--------------|---------------|
| `Crud_model` + soft delete `deleted` | base de todos os models do plugin |
| Hooks `app_hook_data_*` | alimentar Auditoria automaticamente |
| `app_filter_staff_left_menu` / `admin_settings_menu` / `dashboard_widgets` | menus e widgets |
| Autenticação `Security_Controller` / `login_user` | login + admin shortcut |
| `app_files_helper` (`files/`) | uploads/anexos |
| Helpers de moeda/data (`to_currency`, `format_to_*`, `convert_date_*`) | formatação |
| `settings` (`get_setting`/`save_setting`) | parâmetros globais |
| `custom_fields`/`custom_field_values` | campos extras por entidade |
| Cron (`app_hook_after_cron_run`) | jobs de recorrência/lembrete/fechamento |
| `clients`/`users` | identidade de cliente/contato (referência) |
| `items`/`item_categories`/`taxes` | espelho de catálogo quando útil p/ faturamento |
| `invoices`/`invoice_payments` | faturamento nativo opcional (1 fatura : N pagamentos) |
| `orders`/`order_items` | base conceitual do PDV/Bar |
| `expenses`/`expense_categories` | espelho de despesas quando útil |
| DataTables, jQuery, Feather, calendário (vendor já presente) | UI sem duplicar libs |

## 2. Do sistema legado — REAPROVEITAR (apenas conceitos, reescrevendo)

| Conceito sistema legado | Como reescrever |
|------------------|-----------------|
| Multiunidade com papéis + permissões por unidade | `gd_usuario_unidade` + `AccessService` (sem 12 bits fixos) |
| Instalação idempotente via SQL no hook | manter mecanismo, modularizar (`Database/Schema/*`) |
| Snapshot no comprovante (imutabilidade) | `gd_recibos.snapshot JSON` padronizado |
| Preview-antes-de-confirmar na importação | módulo Importações com lotes/linhas/erros |
| Dedup de contato por telefone | normalização no Service (não em SQL) |
| Integração externa com degradação graciosa (IARA) | módulo Integrações isolado, contrato explícito |

## 3. Do sistema legado — REFAZER (obrigatório, NÃO copiar)

| Item sistema legado | Por quê | Substituição |
|--------------|---------|--------------|
| Controller monolítico `Bombeiros.php` (5k linhas) | impossível manter/testar | 1 controller/agregado + Services |
| Métodos de 100–222 linhas | regra de negócio no HTTP | Services + DTOs + transações |
| `turma`/`horario`/`pelotao` texto no aluno | sem estrutura/relatório | `programas`/`turmas`/`horarios`/`matriculas` |
| Pagamento dentro de `grupo_donato_cobrancas` | sem auditoria, sem N:N | `cobrancas` + `pagamentos` + `pagamento_alocacoes` |
| Presença `UNIQUE(aluno_id,data_aula)` | uma aula/dia, sem turma | presença por `aula_id`+`matricula_id` |
| Responsável 1:1 (`responsavel_id` no aluno) | sem múltiplos responsáveis | `conta_pessoa` N:N com papéis |
| Materiais em ~6 colunas do aluno | não escala | entregas referenciando catálogo |
| Comprovante com snapshot coluna a coluna | frágil | recibo imutável + JSON |
| SQL embutido/strings mágicas nos models | risco/manutenção | query builder + Enums + Repositories |
| Valores/textos hard-coded (237.00, curso, slug) | específicos do cliente sistema legado | `gd_config` + seeds neutros |
| Sem auditoria das mutações | sem rastreabilidade | módulo Auditoria + eventos |
| Matrícula pública sem verificação | abuso/fraude | honeypot + rate-limit + OTP |
| Unidade ativa só em sessão | acesso indevido | revalidar escopo por request |
| Sem paginação server-side | não escala | DataTables server-side |
| Respostas JSON inconsistentes | manutenção/erros | formato único `{success,message,data}` |
| Nomes/prefixos internos da marca anterior | restrição #2 | prefixo `gd_` |

## 4. Nada copiado literalmente

Nenhuma linha de código, nome de tabela, nome de classe ou regra de negócio específica do
sistema legado é reaproveitada no novo plugin. O sistema legado é referência de **como o Rise funciona**
e de **o que evitar**. Ver [legacy-audit.md](legacy-audit.md) e [risks.md](risks.md).
