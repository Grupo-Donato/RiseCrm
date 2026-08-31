# Grupo Donato — Gestão

Plugin do Rise CRM para a gestão integrada do Grupo Donato.

**Estado:** Fases 1 a 5 + módulos de locações de quadras e churrasqueiras (versão 0.9.8, schema 001–066). O plugin entrega cadastro central, catálogo, agenda, locações, escola/personal e financeiro básico. As modalidades avulso e mensalista aceitam valor livremente editável; ambas integram recebível, sinal opcional, saldo, acréscimo de permanência e histórico ao financeiro existente. O módulo Churrasqueiras replica o fluxo comercial de Locações para os recursos CH1–CH4 e CH5-SL, com agenda, avulsos, mensalistas e pagamentos próprios. Estacionamento e bar permanecem fora deste módulo.

> **Importação (Cenário 2):** as tabelas `gd_import_*` (046–049) existem mas o módulo está **oculto** e **não foi continuado** no protótipo. `import_selftest` está desligado da suíte.
>
> **Homologação:** nesta entrega, `verify-fast` foi executado com sucesso com checagem de banco desabilitada, além de lint em todos os arquivos PHP. O `verify-full` deve ser repetido no ambiente completo do Rise, com banco, plugins e usuário autenticado, antes da publicação em produção.

## Requisitos

- Rise CRM 3.9.6 ou superior compatível.
- PHP 8.1 ou superior; homologado em 8.2.12.
- MySQL/MariaDB com InnoDB e `GET_LOCK`; homologado em MariaDB 10.4.32.
- Pasta do plugin exatamente `plugins/grupo_donato_gestao`.

## Instalação e atualização

Use **Configurações → Plugins** no Rise para instalar, ativar, desativar e atualizar. Não edite `app/Config/activated_plugins.json` manualmente: esse arquivo é estado operacional gerenciado pelo Rise.

O hook de instalação/ativação/atualização aplica as versões 001–049 e seeds idempotentes, incluindo Q2–Q6, tabela de preço padrão e `Caixa Principal`. O uninstall não remove tabelas nem dados.

Detalhes: [docs/installation.md](docs/installation.md).

## Verificação

Este pacote do Rise não contém `spark`. Use o harness próprio:

```powershell
php plugins/grupo_donato_gestao/Tests/cli.php install
php plugins/grupo_donato_gestao/Tests/cli.php demo
php plugins/grupo_donato_gestao/Tests/cli.php selftest
php plugins/grupo_donato_gestao/Tests/cli.php uninstallcheck
powershell -ExecutionPolicy Bypass -File plugins/grupo_donato_gestao/Tests/concurrency.ps1
powershell -ExecutionPolicy Bypass -File plugins/grupo_donato_gestao/Tests/booking_concurrency.ps1
powershell -ExecutionPolicy Bypass -File plugins/grupo_donato_gestao/Tests/series_concurrency.ps1
php plugins/grupo_donato_gestao/Tests/cli.php expire-holds
```

Para validar a tela integrada com dados comerciais de teste, execute `demo` depois
do `install`. A carga é idempotente, usa códigos `DEMO-*` e cria produtos,
variações, preços, uma turma/aluno com presença, uma cobrança parcial, um
pagamento e uma despesa paga. Ela não é executada automaticamente na instalação.

O script Bash equivalente é `Tests/concurrency.sh`. Consulte [docs/testing.md](docs/testing.md).

## Estrutura

- `Config/`: constantes, permissões e rotas.
- `Controllers/`, `Models/`, `Services/`, `Views/`: fundação, cadastro central e catálogo.
- `Database/Schema/Versions/`: versões 001–049 (046–049 = importação, sem uso no protótipo).
- `Database/Seeds/`: unidade, áreas, permissões, catálogo e conta financeira padrão.
- `Language/`: português e fallback inglês seguro.
- `Tests/`: instalação, self-test e concorrência.
- `docs/`: arquitetura, segurança, instalação, schema e homologação.

## Pacote operacional para agentes

O módulo central de Custos está documentado em [docs/costs.md](docs/costs.md), com relatório em [docs/reports/costs-implementation.md](docs/reports/costs-implementation.md). Para validar somente esse domínio, execute `php plugins/grupo_donato_gestao/Tests/cli.php costs-selftest`.

O ponto de entrada permanente para novas tarefas está em [`docs/agent/`](docs/agent/): contexto técnico, guardrails, estado atual, roadmap, critérios de aceite e handoff. Registre novas especificações em [`docs/tasks/`](docs/tasks/) e resultados em [`docs/reports/`](docs/reports/).

Verificação rápida e completa:

```powershell
powershell -ExecutionPolicy Bypass -File plugins/grupo_donato_gestao/Tests/verify-fast.ps1
powershell -ExecutionPolicy Bypass -File plugins/grupo_donato_gestao/Tests/verify-full.ps1
```

Equivalentes Bash: `Tests/verify-fast.sh` e `Tests/verify-full.sh`.

## Ordem de leitura

1. [docs/phase-5-implementation.md](docs/phase-5-implementation.md) · [docs/finance.md](docs/finance.md) · [docs/reports/phase-5.md](docs/reports/phase-5.md)
2. [docs/series-occurrences.md](docs/series-occurrences.md) · [docs/bookings.md](docs/bookings.md) · [docs/booking-lifecycle.md](docs/booking-lifecycle.md)
3. [docs/resource-availability.md](docs/resource-availability.md) · [docs/calendar.md](docs/calendar.md) · [docs/time-and-timezone.md](docs/time-and-timezone.md)
4. [docs/installation.md](docs/installation.md) · [docs/testing.md](docs/testing.md) · [docs/architecture.md](docs/architecture.md)

## Limites e segurança

Não existem boleto, gateway, integração Pix, conciliação bancária, nota fiscal, juros/multa automáticos, DRE, fechamento formal de caixa, estoque, bar ou integrações externas.

Segredos são recusados pelo `SettingsService`; não há criptografia caseira. Rotas POST do plugin usam o filtro CSRF, toda autorização é repetida no backend e `unit_id` é validado. Não há purge nem fluxo público para editar/excluir auditoria.

As restrições de homologação são: não foi executada falha induzida de DDL em banco isolado e não houve automação de console JavaScript em navegador; os fluxos HTTP, banco, permissões e serviços foram verificados no ambiente real.

Próxima etapa: executar o prompt de backend em [docs/agent/CODEX_LOCACOES_BACKEND_PROMPT.md](docs/agent/CODEX_LOCACOES_BACKEND_PROMPT.md), validar o contrato em [docs/agent/LOCACOES_BACKEND_CONTRACT.md](docs/agent/LOCACOES_BACKEND_CONTRACT.md) e rodar `verify-full` no ambiente completo. Importação permanece **não continuada** e oculta no protótipo.
