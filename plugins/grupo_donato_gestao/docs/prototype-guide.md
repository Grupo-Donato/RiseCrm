# Guia de demonstração — Grupo Donato (≤ 15 minutos)

Este guia conduz uma demonstração completa do protótipo ao cliente usando apenas a
interface. Tudo é acessível pelo submenu **Grupo Donato** (9 itens) e por botões/abas
dentro das telas. Não é preciso usar URLs técnicas.

> Pré-requisito: estar logado no Rise como usuário **staff/admin** com as permissões do
> Grupo Donato. A unidade ativa padrão é **Unidade Principal**.

## Menu do plugin (9 telas)

1. **Visão geral** — dashboard operacional + atalhos.
2. **Clientes e alunos** — abas Alunos / Famílias e clientes / Pessoas.
3. **Turmas e personal** — filtro Todas / Turmas em grupo / Personal.
4. **Presença** — chamada por turma e data.
5. **Agenda e reservas** — calendário + botões de reserva/recorrência.
6. **Mensalistas de quadra** — locações recorrentes + situação financeira.
7. **Financeiro** — abas Resumo / Contas a receber / Pagamentos / Gerar cobranças.
8. **Caixa e despesas** — abas Movimentações de caixa / Despesas.
9. **Configurações** — hub de cadastros de apoio + informações do sistema.

---

## Roteiro de 15 minutos

### 0. Visão geral (1 min)
Abra **Visão geral**. Mostre os KPIs reais (alunos ativos, turmas, aulas e reservas de
hoje, mensalistas, a receber, vencido, recebido no mês, saldo do mês) e a faixa de
**Atalhos**. Explique que tudo parte daqui.

### 1. Fluxo Escola (4 min)
1. **Atalho “Novo aluno”** (ou Clientes e alunos → aba Alunos → Adicionar).
   - Crie o aluno informando uma **nova família** e um **responsável** + contato.
2. Em **Turmas e personal** (filtro *Turmas em grupo*), abra/!crie uma turma e use
   **Matricular** para inscrever o aluno.
3. Em **Presença**: selecione a turma e a data, **Carregar alunos**,
   use **Marcar todos presentes**, depois **Salvar**.
4. Em **Financeiro → Gerar cobranças**: escolha o mês, **Pré-visualizar** e **Confirmar**
   (gera a mensalidade da matrícula).
5. Em **Financeiro → Contas a receber**: localize a cobrança e **Registrar pagamento**.

### 2. Fluxo Personal (3 min)
1. **Novo aluno** (ou reutilize um existente).
2. **Turmas e personal** (filtro *Personal*): crie/abra uma turma personal, vincule o
   instrutor, o recurso e os dias/horário; **Matricular** o aluno.
3. **Agenda e reservas**: mostre a turma no calendário (links “Ver reservas/Ver séries”).
4. **Presença** para a turma personal.
5. **Gerar cobranças** + **Registrar pagamento** como no fluxo Escola.

### 3. Fluxo Mensalista de quadra (4 min)
1. **Atalho “Novo mensalista”** (ou Mensalistas de quadra → Novo mensalista).
   - Cliente, contato, quadra, dia, horário, valor e dia de vencimento.
2. Gere a **série** de reservas (recorrência) ao salvar a locação recorrente.
3. Em **Mensalistas de quadra**, mostre as colunas (Cliente, Contato, Quadra, Dia,
   Horário, Valor, Vencimento, Status, Próxima reserva, **Situação financeira**).
4. Na linha, use **Gerar cobrança** (mensal) e **Registrar pagamento**.
   Mostre que a **Situação financeira** muda para *Em dia*.
5. Demonstre **Suspender / Retomar** direto na lista.

### 4. Fluxo Locação avulsa (2 min)
1. Em **Mensalistas de quadra**, botão **Locações avulsas** (lista geral).
2. Crie uma locação **avulsa** (single) para um cliente, reservando a quadra.
3. Abra a locação e use **Gerar cobrança** (avulsa) → **Registrar pagamento**.

### 5. Fechamento (1 min)
- **Caixa e despesas**: aba *Movimentações de caixa* mostra entradas dos pagamentos;
  aba *Despesas* permite lançar uma despesa.
- **Configurações**: mostre o hub (Unidades, Áreas, Centros, Produtos, Recursos, Tabelas
  de preço, Auditoria, Permissões do Rise) e a seção **Informações do sistema**.

---

## Dicas de navegação
- Toda tela financeira tem a **barra de abas** no topo — não dependa do menu lateral.
- O detalhe do aluno tem atalhos para família, turma/matrícula, presença e financeiro.
- O detalhe da turma conecta alunos, instrutor, recurso, horários, agenda e presença.
- Cadastros avançados (produtos, recursos, tabelas de preço, séries, contas a receber)
  continuam existindo e são alcançados por botões/abas/links internos.

## Fora do protótipo (não demonstrar)
Importação de planilhas, bar/estoque, eventos/campeonatos, portal público, WhatsApp,
gateway/boleto/Pix integrado. O módulo de importação existe no banco mas está **oculto**
e **não** foi continuado neste protótipo.
