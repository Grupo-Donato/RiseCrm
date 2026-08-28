# Handoff atual

## Estado final

- Última entrega: **TASK-GD-BBQ-001** — réplica isolada do módulo de Locações para Churrasqueiras 1–6.
- Entrega anterior: **TASK-GD-LOC-001** (preços livres, cancelamento/liberação de mensalistas,
  sinal e saldo de avulsas, histórico financeiro e lista operacional).
- Versão: **0.9.8**.
- Schema alvo: **053**; V053 adiciona 4 tabelas comerciais próprias para churrasqueiras, preservando as tabelas de quadras.
- O self-test completo anterior não foi reexecutado neste pacote fora de uma instalação Rise; o QA desta entrega inclui lint integral e checagem estática de rotas/arquivos.

## sistema legado embutido (override dos guardrails #2/#3, autorizado)

- Todo o sistema legado importado para `Operacional/` sob `grupo_donato_gestao\Operacional` (namespace reescrito;
  URL `grupo_donato/operacional/...` preservada). Wire em `index.php` (require `Operacional/bootstrap.php`);
  `gd_install()` cria as 9 tabelas `grupo_donato_*`. Verificar com `php Tests/cli.php operacional-check`.
- sistema legado original intacto (só leitura). Recursos com libs externas (Dompdf/Mpdf/IARA) dependem das libs.

## Arquivos principais (protótipo)

- `index.php` — `gd_left_menu()` reduzido a 9 itens; importação e telas avançadas fora do menu.
- `Controllers/Dashboard.php` + `Views/dashboard/index.php` — KPIs reais + atalhos.
- `Views/components/` — `tabs_nav.php`, `empty_state.php`, `finance_nav.php`, `cash_nav.php`.
- Telas com abas/botões/links: `school_students/*`, `school_classes/*` (+ controller),
  `school_attendance/index.php`, `calendar/index.php` (+ controller), `court_rentals/monthly.php`
  (+ controller), `finance/*`, `settings/general.php` (+ controller).
- `Language/portuguese/default_lang.php` — novas chaves `gd_*` (menu/KPIs/atalhos/abas).
- `Services/CourtRentalLifecycleService.php` — pausa reversível e cancelamento definitivo com liberação futura/auditoria.
- `Services/FinanceService.php` + `Database/Schema/Versions/V051_add_payment_type.php` — sinal, saldo e histórico sem segundo financeiro.
- `Services/CourtRentalService.php` + `Database/Schema/Versions/V052_add_court_rental_extra_time.php` — permanência adicional sem nova reserva, recebível sincronizado e entrada na baixa.
- `Tests/cli.php` — `import_selftest` desligado (comentado) por estar fora do escopo.

## Testes

- Lint dos arquivos alterados, migration/idempotência, rotas+CSRF e smoke HTTP local: PASS.
- Self-test atual: 489 PASS / 4 FAIL; nenhuma das falhas restantes pertence aos cenários novos de locações.

## Pendência (não-plugin) — bloqueia 1.0.0

- `verify-full` acusa `app/Config/Logger.php` alterado hoje 09:58 (threshold de log, fora deste
  trabalho). Core **não** editado (guardrail #1). Reverter ao baseline (hash `4f45…e1e`) e
  re-rodar `verify-full` → 100% verde → bump 1.0.0.

## Reparo de ambiente já feito

- Banco corrompido pelo rebuild de hoje: `gd_settings` e `gd_business_areas` recriadas via
  TRUNCATE (sem dados de domínio) + instalador idempotente; AUTO_INCREMENT de todas as `gd_*`
  reassentado para `MAX(id)+1`. `CHECK TABLE` 49/49 OK; foundation reseedado.

## Próxima ação

1. Corrigir as quatro falhas antigas do harness e repetir o `verify-full` autenticado, se necessário.
2. Importação permanece **não continuada**; só retomar se formalmente definido.
