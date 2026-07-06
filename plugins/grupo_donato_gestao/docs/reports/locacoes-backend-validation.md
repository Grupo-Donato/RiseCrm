# Validação — backend das locações (0.9.3)

Ambiente: Windows 11 + XAMPP, banco real `rise_crm` (prefixo `rise_`), PHP CLI `C:\xampp\php\php.exe`.
Data: 2026-07-06. Schema mantido em **049** (nenhuma migration nova).

## 1. `php -l` (lint)
`verify-fast.sh` → **PASS 334/334** arquivos PHP sem erro de sintaxe.

## 2. `verify-fast.sh` (estático)
```
[FAST] PHP lint            PASS 334/334
[FAST] Version/schema/marker  PASS version=0.9.3 schema=049 database=049|049
[FAST] Routes              PASS required routes and CSRF group
[FAST] Language catalog    PASS 1044 unique gd_* keys
VERIFY-FAST: PASS
```
- `PLUGIN_VERSION` = header `index.php` = **0.9.3**.
- Catálogo: 1044 chaves `gd_*` únicas (antes 1042; +`gd_finance_partial`, +`gd_finance_receivable_exists`), sem duplicatas.
- Rotas novas `court-rentals/product-options` e `court-rentals/price-list-options` presentes no grupo CSRF.

## 3. Self-test (`php Tests/cli.php selftest`)
**461 PASS / 1 FAIL.** O único FAIL é **pré-existente e ambiental**, fora do escopo de locações (ver §6).

Novos testes adicionados e aprovados:

| # | Teste | Resultado |
|---|---|---|
| 2.1 | filtro por quadra encontra avulsa (gd_booking_resources) | PASS |
| 2.1 | filtro por quadra encontra recorrente (gd_booking_series_resources) | PASS |
| 2.1 | filtro por outra quadra exclui locações não vinculadas | PASS |
| 2.2 | resumo de avulsa canônico no fuso local (sem substring UTC) | PASS |
| 2.2 | resumo de recorrente traz dias e horário local | PASS |
| 2.2 | horário que cruza meia-noite preserva data/hora local (virada de dia) | PASS |
| 2.4 | calendário inclui court_rental_id, booking_type e resource_ids | PASS |
| 2.4 | calendário sem tipos solicitados não devolve disponibilidade padrão | PASS |
| 2.5/2.6 | options de produto só traz tipos compatíveis; exclui físico | PASS |
| 2.6 | options de produto respeita a unidade (não vaza de outra) | PASS |
| 2.5 | options de tabela de preço traz a lista ativa | PASS |
| 2.7 | saldo agregado por locação (aberto/vencido/parcial) em lote | PASS |
| 2.7 | saldo agregado lista cobranças abertas por locação | PASS |
| 2.8 | geração de cobrança avulsa é idempotente (não duplica) | PASS |
| 2.8 | idempotência mantém uma única cobrança avulsa por locação | PASS |

Regressão: os testes de calendário pré-existentes (privacidade/PII, "somente Ocupado" sem permissão) continuam PASS — o `court_rental_id` só é anexado quando o usuário já vê detalhes de booking (`can_view_bookings`).

## 4. Concorrência (executados diretamente — PASS)
```
court_rental_concurrency.sh  PASS (linked 1/1, activations 1/1, overrides 1/1, create 1/1)
concurrency.sh               PASS (sem duplicata; bloqueio temporal serializado)
booking_concurrency.sh       PASS (reservas simples e multi-recurso serializadas)
series_concurrency.sh        PASS (geração de série sem duplicidades)
```
Cada cenário confirma **um vencedor + um conflito**, sem gravação parcial, com locks liberados. O mapeamento de 409 (ver §5) é aplicado nas ações de ciclo de vida consumidas por `$.ajax`.

## 5. JSON / status HTTP
- `Gd_Controller::json_success/json_error` agora definem `Content-Type: application/json; charset=UTF-8` (global, seguro).
- `gd_fail` inclui `error_code` (chave `gd_...`) e — quando `emit_http_status` está ligado — status HTTP: 422 validação/regra, 409 conflito de agenda/versão/lock, 404 inexistente/fora da unidade, 500 não tratado.
- `emit_http_status` é ligado **apenas** em `Court_rentals::writeStatus` (ativar/suspender/retomar/cancelar/concluir), pois esses endpoints são consumidos por `$.ajax` cujos `.fail` foram endurecidos (`view.php`, `monthly.php`) para ler `xhr.responseJSON.message` e recarregar em 409. Os fluxos `save-single`/`save-monthly` (Rise `appForm`, sem handler de erro no core) permanecem em HTTP 200 com `{success:false,message,error_code}` — preservando a mensagem funcional sem quebrar o modal. `AccessService::require` mantém 403 e agora envia Content-Type + `error_code=gd_access_denied`.

## 6. Falha pré-existente (fora do escopo)
`[FAIL] 7 áreas de negócio — 6 áreas` (`cli.php` install, foundation).

Causa raiz (verificada em `rise_gd_business_areas`): existem **7 linhas corrompidas** (ids 1–7) de execuções antigas deste banco de desenvolvimento — `code` ilegível, `status='?%??'`, `deleted=-91`. Como a colação do MySQL é case-insensitive, o `code` corrompido "Personal" (id 7) colide com o `code` "personal" que o `FoundationSeeder` tenta inserir, e o índice único `uniq_scope_code` impede a inserção → `count_active()` retorna 6 em vez de 7.

- **Não relacionado a locações nem a esta entrega.** Os arquivos de seed/área de negócio (`FoundationSeeder.php`, `V003_*`, `Business_areas.php`, `cli.php` install) não foram alterados por este trabalho.
- **Bloqueia apenas o verdito "verde" do `verify-full`**, que usa `set -euo pipefail` e aborta no passo `self-test` porque `cli.php selftest` sai com código 1 quando há qualquer FAIL. Os passos seguintes (concorrência, uninstallcheck) foram, então, executados diretamente e passam (§4).
- Correção segura possível (requer aprovação — o guardrail proíbe limpeza de dados sem autorização): remover/normalizar as 7 linhas corrompidas (`deleted=-91`) para o seeder reinserir "personal". É reversível e não toca dados legítimos.

## 7. `verify-full.sh`
Aborta no passo `self-test` por causa do FAIL pré-existente de §6 (`set -euo pipefail`). `verify-fast`, self-test (exceto o item de §6) e as quatro suítes de concorrência foram validados individualmente e passam. Após a limpeza das linhas corrompidas de §6, o `verify-full` deve concluir verde de ponta a ponta.

## 8. Smoke autenticado (manual)
Checklist entregue em [locacoes-smoke-checklist.md](locacoes-smoke-checklist.md) — requer sessão logada no Rise e navegador (desktop/tablet/celular).
