# Validação do disparador — 2026-09-08

Foi usado um banco MySQL descartável (`campaign_test`) e transportes simulados. Nenhuma
mensagem real foi enviada.

- `php Tests/run_campaign_schedule.php`: 14 verificações passaram.
- `node Tests/campaign_form_test.js`: 8 verificações passaram.
- `php Tests/run_campaign_integration.php`: 27 verificações passaram.
- `php Tests/run_unit.php`: 17 passaram.
- `php Tests/run_product_static.php`: 18 passaram.
- `php Tests/run_inbox3_handoff.php`: 84 passaram.
- `php Tests/run_migration_smoke.php`: passou, schema V015.
- `php Tests/run_refinement_integration.php`: 18 passaram.
- `php -l` em todos os arquivos PHP, `node --check` nos JavaScript alterados e `git diff --check`: passaram.

Node foi executado pelo binário instalado no container N8N. O teste novo cobre datas,
fuso, dias permitidos, fim do período, janelas perdidas, idempotência de cadastro,
agendamento e envio, variáveis, telefones duplicados, intervalo, limite por minuto,
retry, opt-out tardio, encerramento, mídia reutilizável, isolamento de canal e template
oficial.

`php Tests/run_service_integration.php` continua falhando neste runtime com `preg_match_all():
Compilation failed: unknown property name after \\P or \\p at offset 25`, fora do caminho do
disparador. Essa limitação fica registrada; testes com provedores reais e revisão visual no
navegador continuam pendentes.
