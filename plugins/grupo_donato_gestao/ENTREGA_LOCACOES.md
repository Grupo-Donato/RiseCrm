# Entrega — locações de quadras simplificadas

**Plugin:** Grupo Donato Gestão  
**Versão:** 0.9.6  
**Schema:** 049, sem migration nova

## Regra comercial aplicada

O formulário de locação foi ajustado para a operação real do Grupo Donato:

- Avulso de 1h30: R$ 380,00.
- Avulso de 2h: R$ 460,00.
- Mensalista de 1h30: R$ 900,00 por mês.
- Mensalista de 2h: R$ 1.050,00 por mês.
- Quadra + churrasqueira: valor especial informado no cadastro.

Os quatro preços regulares ficam centralizados em `Config/Constants.php` e são reaplicados no servidor no momento da gravação. A interface não depende do valor enviado pelo navegador.

## Formulário único

As rotas antigas de avulso e mensalista continuam válidas, mas agora abrem o mesmo formulário com a modalidade correspondente pré-selecionada.

O novo fluxo contém:

1. Tipo de locação.
2. Cliente, contato e telefone.
3. Data, horário e duração.
4. Seleção única da quadra.
5. Dia de vencimento para mensalista.
6. Valor especial somente para quadra + churrasqueira.
7. Resumo e verificação de disponibilidade.

O título, horário final, recorrência semanal, valor e metadados comerciais são gerados automaticamente.

## Correção 0.9.5

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
- Nenhuma regra comercial, rota, tabela ou migration foi alterada.

## Compatibilidade

- Mantidas as tabelas e migrations existentes.
- Mantidas as rotas antigas `save-single` e `save-monthly`.
- Criado o endpoint consolidado `court-rentals/save-rental`.
- Agenda, alunos, turmas, presença e financeiro não foram remodelados.
- CSS novo permanece escopado em `.gd-rentals-shell` e utiliza os componentes do Rise CRM.

## Validação executada

- Lint PHP: 335 arquivos aprovados.
- JavaScript do novo modal: sintaxe validada com Node.js.
- Catálogo de idioma: 1.089 chaves `gd_*`, sem duplicidades ou referências literais ausentes.
- Versão do metadata e `Constants::PLUGIN_VERSION`: 0.9.6.
- Schema preservado: 049.
- `Tests/verify-fast.sh`: aprovado com consulta ao banco desativada; o lint do script foi complementado por verificação direta dos 335 arquivos.

O `verify-full` continua dependente da instalação real do Rise CRM, banco configurado e sessão autenticada.
