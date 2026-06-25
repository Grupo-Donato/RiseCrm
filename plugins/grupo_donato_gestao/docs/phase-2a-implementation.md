# Fase 2A — implementação e homologação

## Status

**CONCLUÍDA COM RESTRIÇÕES**, em 18/06/2026.

Foram entregues o cadastro central de contas, pessoas, relações N:N, métodos de contato, endereços, pesquisa server-side, duplicidade assistiva, permissões, auditoria, telas e testes. Agenda, escola, quadras, contratos e financeiro não foram iniciados.

As restrições herdadas permanecem: não houve instalação limpa nem falha DDL induzida em banco isolado; não há automação de navegador para console JavaScript; a raiz não possui Git. O bloqueio HTTP de papel sem permissão foi homologado na Fase 1 e coberto novamente no backend nesta fase, mas não foi repetido com uma segunda sessão staff. Durante a primeira regressão, antes da correção do harness, o teste herdado ainda fez cleanup físico de registros técnicos temporários da Fase 1; nenhum registro de domínio foi apagado fisicamente e o harness agora usa rollback/soft delete.

## Estado inicial

- Schema 001–007 concluído, sete tabelas `rise_gd_*` e marker 007.
- Self-test da Fase 1: 46 PASS / 0 FAIL; concorrência: 100/100.
- Plugin com 74 arquivos e 323.848 bytes.
- sistema legado inventariado com 41 arquivos; hashes de referência registrados na Fase 1.
- Core sem patch funcional do plugin; apenas estado operacional de plugins já existente.

## Entrega

- Versions 008–012 e marker 012.
- Models separados para `customer_accounts`, `people`, `account_people`, `contact_methods` e `addresses`.
- Services de conta, pessoa, relação, contato, endereço, normalização, privacidade e duplicidade.
- Controllers finos e rotas GET de página/detalhe e POST com CSRF para listagem, modal e mutação.
- Sete permissões novas integradas aos papéis nativos do Rise.
- Menu direto para Contas de clientes e Pessoas.
- DataTables server-side com limites e ordenação por whitelist.
- Cinco modais e detalhes de conta/pessoa com relações, contatos, endereços, duplicidades e auditoria resumida.
- País padrão opcional configurável; nenhum país comercial foi seedado.

## Homologação

- Lint: 86 arquivos PHP, 0 erro.
- Self-test: 114 PASS / 0 FAIL, com dados de domínio em transação revertida.
- Concorrência: 100 números, 100 distintos.
- Uninstall: `before=12 after=12 preserved=yes`.
- HTTP real: páginas/listas 200; criação e edição de conta/pessoa, relação, contato e dois endereços 200; detalhe 200; duplicidade alertada e override auditado; troca de principal confirmada no banco; CSRF sem token 303; todos os registros HTTP técnicos terminaram soft-deleted e nenhum ficou ativo.
- Apache/Rise: nenhum erro novo relacionado ao plugin no smoke.

## Limites

Não existe compartilhamento de identidade entre unidades, merge automático, integração obrigatória com clientes Rise, importação, portal, mensageria ou operação comercial/financeira. A Fase 2B não foi iniciada.
