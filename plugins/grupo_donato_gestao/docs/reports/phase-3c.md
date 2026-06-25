# Relatório — Fase 3C

## Estado

- Status: concluída com restrições ambientais não bloqueadoras.
- Versão: 0.7.0.
- Schema/marker: 033.
- Tabelas: 33.
- Self-test: 385 PASS / 0 FAIL.

## Entrega

Operação comercial de locação de quadras sobre os módulos existentes (contas/pessoas, catálogo/preços, recursos, reservas únicas, séries, calendário, disponibilidade):

- Cadastro comercial (`gd_court_rentals`) com número por sequência (`LOC-AAAA-NNNNNN`), tipo (avulso/mensalista), ciclo, estados, vigência, dia preferencial de vencimento e valores em `DECIMAL(15,2)`.
- Locação avulsa integrada a uma reserva e mensalista integrado a uma série, criados em transação única com vínculo e snapshot; vínculo a reserva/série existente quando válido.
- Resolução de preço como **sugestão** (reutiliza `PricingService`), valor negociado e **snapshot imutável** da negociação; desconto com motivo obrigatório; reprecificação explícita e auditada que não altera snapshots históricos.
- Estados e transições com `lock_version`; suspensão/encerramento pausam a série e aplicam política explícita (manter/cancelar/pausar) às ocorrências futuras, auditada.
- Histórico append-only (`gd_court_rental_events`); vínculos (`gd_court_rental_schedule_links`) com invariante de exclusividade protegida por unique (colunas-guarda).
- Lista de locações e lista específica de mensalistas; detalhe comercial; permissões, auditoria, testes e documentação.

Não foram criados: título a receber, cobrança, pagamento, baixa, recibo, caixa, conciliação, inadimplência, multa, juros, crédito, caução, nota fiscal, contrato jurídico, assinatura, integração bancária, importação, estoque ou PDV. O dia de vencimento é apenas condição comercial.

## Homologação

- `verify-fast`: PASS (212 arquivos lint; versão 0.7.0/033/033; rotas + CSRF; 682 chaves gd_* únicas).
- `verify-full`: PASS (~24s).
- Self-test: 385 PASS / 0 FAIL (57 asserções novas da Fase 3C).
- Concorrência de locação: duas ativações simultâneas, duas locações na mesma série, dois overrides concorrentes, `lock_version` desatualizado e criação integrada concorrente no mesmo recurso → 1 efetiva + 1 conflito em cada cenário; nenhuma dupla ocupação, nenhum vínculo comercial duplicado, nenhum overwrite silencioso.
- Regressões: sequência (100/100), temporal (1/1), booking (1/1), série (1 efetiva/1 idempotente/0 duplicidades): PASS.
- Install/idempotência e uninstall 33/33: PASS.
- sistema legado, core Rise e migrations 001–029: preservados byte a byte.
- `CHECK TABLE` 33/33 e logs novos relevantes: PASS.

## Restrições

- Smoke autenticado em navegador não automatizado nesta fase (a UI usa os padrões appTable/appForm/select2 já homologados).
- Os assistentes recebem IDs de produto/tabela de preço como entrada direta; uma busca rica de catálogo pode ser adicionada quando o financeiro exigir.
- Falha DDL induzida em clone isolado ainda não executada; a raiz não possui Git e usa manifests/hashes.
- Importação do controle de mensalistas permanece fora de escopo (Fase 13).

## Próxima fase

Não iniciada. Especificar a próxima fase (Escola ou Financeiro núcleo) antes de qualquer mudança; financeiro (contas a receber, cobrança, pagamento) permanece fora do escopo entregue.
