# Financeiro básico

## Contas a receber

Cobranças podem ser manuais, de matrícula ou de locação. Possuem número, cliente, referência, vencimento, área, centro, valor original, pago, saldo e estado. O valor original não é reprecificado depois da geração.

O assistente mensal carrega matrículas ativas e mensalistas, resolve preço cadastral quando disponível e permite informar valor manual. A confirmação gera somente itens ainda inexistentes.

Locação avulsa possui ação explícita no detalhe; nenhuma cobrança histórica é criada automaticamente.

## Pagamentos

Um pagamento pode ser alocado a uma ou várias cobranças; uma cobrança pode receber vários pagamentos. A soma das alocações deve coincidir com o pagamento e não pode superar saldos. O comprovante é uma visualização simples, sem valor fiscal.

O estorno não exclui registros: marca pagamento/alocações como estornados, recalcula cobranças e cria saída inversa no caixa.

## Despesas e caixa

Despesa paga exige conta financeira e método, e cria saída. Pagamentos confirmados criam entradas. O livro-caixa é append-only e calcula saldo acumulado pelo período/conta.

Não existe abertura ou fechamento formal de turno.

## Segurança e limites

Permissões: `gd_finance_view`, `gd_receivables_manage`, `gd_payments_manage`, `gd_expenses_manage`, `gd_cash_view`. Todas as operações respeitam unidade, CSRF, IDOR e auditoria.

Não há boleto, gateway, integração Pix, conciliação, fiscal, juros automáticos, DRE ou importação.
