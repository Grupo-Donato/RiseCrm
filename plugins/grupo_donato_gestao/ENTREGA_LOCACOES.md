# Entrega — módulo de locações de quadras

**Plugin:** Grupo Donato Gestão  
**Versão:** 0.9.8

**Schema:** 053; o módulo de quadras permanece nas tabelas originais e o schema 053 adiciona o módulo irmão de churrasqueiras

## Regra comercial

Não há preços fixos por duração ou modalidade. O operador informa livremente o
valor do avulso ou do mensalista; o backend normaliza e persiste o valor em
`DECIMAL`, sem `float` e sem presets embutidos.

O módulo de quadras mantém somente as modalidades `single` (avulso) e `recurring`
(mensalista). Churrasqueiras agora existem em um módulo irmão isolado, sem misturar
contratos, agenda comercial ou cobranças das quadras. Estacionamento e bar permanecem fora do escopo.

## Financeiro da locação avulsa

Ao criar um avulso com valor positivo, a operação cria de forma idempotente o
recebível total. O formulário permite informar sinal, forma de pagamento e
conta financeira. O sinal é registrado como pagamento `deposit`; pagamentos
posteriores usam o fluxo financeiro existente, atualizam o saldo e preservam o
histórico de alocações.

O detalhe e a lista de mensalistas exibem semáforo para sem cobrança, em aberto,
somente sinal, parcial, pago e vencido.

## Acréscimo de permanência

No detalhe de uma locação avulsa ou mensalista, o operador pode registrar 30
minutos, 1 hora ou outro tempo adicional, informar o valor e deixar uma
observação. Isso não cria uma nova reserva: na avulsa, o acréscimo fica na
cobrança única; no mensalista, é aplicado às competências em aberto e às
próximas gerações. Ao ser baixado, o valor é lançado como entrada no caixa
junto com o pagamento. Competências já pagas permanecem preservadas.

## Baixa de pagamentos das locações

A tela de pagamentos reúne locações avulsas e mensalistas por competência. Ao
abrir uma competência, as cobranças recorrentes são criadas automaticamente e
de forma idempotente; o cliente já vem vinculado à conta criada na locação.

A baixa usa um formulário próprio, no padrão da GD Academy, com pessoa que
alugou, competência, valor pago, data de pagamento, forma de pagamento e
observação. O backend confere a pessoa e a competência, registra o pagamento
no ledger e altera a cobrança para `paid` quando o saldo chega a zero. Valores
menores que o saldo ficam como pagamento parcial; a conta financeira padrão é
usada automaticamente quando necessário.

## Formulário e ciclo de vida

As rotas antigas de avulso e mensalista continuam válidas e abrem o formulário
unificado com a modalidade correspondente pré-selecionada. O fluxo contém:

1. Tipo de locação.
2. Cliente, contato e telefone.
3. Data, horário e duração.
4. Seleção da quadra e verificação de disponibilidade.
5. Valor editável; dia de vencimento para mensalista.
6. Sinal opcional apenas para avulso.

Na listagem e no detalhe, a locação pode ser editada enquanto ainda não está em
estado terminal. A edição avulsa altera horário, duração, quadra, cliente,
contato, observações e valor; a edição mensalista altera o contrato e as
ocorrências futuras da série. Pagamentos existentes são preservados e uma
redução abaixo do total já pago é recusada.

Suspensão permanece reversível e não cancela a locação. Cancelamento exige
confirmação e motivo, encerra a série, cancela/libera ocorrências futuras,
preserva o histórico passado e registra auditoria com ator, motivo e contagem.

## Compatibilidade e validação

- Quadras sem grade semanal cadastrada agora ficam disponíveis por padrão.
- Bloqueios, exceções de fechamento e conflitos com outras reservas continuam impedindo o agendamento.
- Quando houver uma grade semanal configurada para a quadra, os horários fora dela continuam indisponíveis.
- A opção **Confirmar e ativar ao salvar** agora mantém coerentes o status da locação e o da reserva ou série vinculada.
- A mensagem de erro informa se o motivo é conflito, bloqueio, fechamento, horário fora da grade ou recurso inativo.

## Correção visual 0.9.6

- Restaurada a inicialização Select2 nos filtros da agenda, evitando que os campos apareçam como selects nativos desalinhados.
- O CSS do FullCalendar foi isolado em `#gd-calendar` e adaptado às variáveis do tema institucional do Rise.
- Corrigidos contraste dos cabeçalhos, horários, botões de navegação, setas, legenda e visualizações mês/semana/dia/lista.
- Removido o `overflow: hidden` genérico do corpo do card da agenda, que podia recortar elementos internos.
- Removidos estilos antigos do formulário anterior que já não eram utilizados.
- Mantidas as tabelas e migrations existentes.
- Mantidas as rotas antigas `save-single` e `save-monthly`.
- Criado o endpoint consolidado `court-rentals/save-rental`.
- Agenda, alunos, turmas, presença e financeiro existentes foram reutilizados.
- CSS novo permanece escopado em `.gd-rentals-shell` e utiliza os componentes do Rise CRM.

Validação local: migration instalada no MariaDB do XAMPP, lint dos arquivos
alterados sem erros, `git diff --check` limpo e selftest com 500 PASS. Permanecem
cinco falhas anteriores da suíte, fora deste módulo.
