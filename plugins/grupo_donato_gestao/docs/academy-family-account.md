# GD Academy — conta familiar

A conta familiar moderna é apenas uma ponte financeira para o responsável legado. O vínculo é persistido em `gd_customer_accounts.legacy_responsible_id`; a origem cadastral continua sendo `grupo_donato_responsaveis`.

Ao abrir uma conta pelo workspace, o serviço reutiliza o vínculo existente ou localiza a conta familiar pelo nome normalizado. Não é criada uma nova ficha de responsável. Os lançamentos de evento são recebíveis individuais e podem ter pagamento parcial, total ou nenhum pagamento.

A consulta combina:

- recebíveis modernos do evento, com criança, categoria, evento, vencimento, saldo e status;
- cobranças mensais legadas, exibidas como histórico compatível.

Essa combinação é somente de leitura para as cobranças antigas. A criação de novos eventos nunca altera a mensalidade legada e a baixa moderna usa o ledger financeiro existente.
