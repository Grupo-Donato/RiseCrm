# Auditoria e redesenho do front de locações de quadras

**Plugin:** `grupo_donato_gestao`  
**Versão desta entrega:** `0.9.2`  
**Schema preservado:** `049`  
**Escopo:** somente a experiência de uso de locações de quadras. Alunos, turmas, presença, mensalidades escolares, pagamentos, caixa e demais áreas foram preservados.

## 1. Conclusão executiva

O módulo não estava “sem backend”. A base já possuía uma arquitetura razoavelmente madura, com quatro conceitos diferentes:

1. **Agenda:** representação visual dos horários.
2. **Reserva (`booking`):** uma ocupação concreta da quadra em uma data e horário.
3. **Série recorrente (`booking_series`):** regra que gera várias reservas futuras.
4. **Locação comercial (`court_rental`):** acordo com cliente, valor, vencimento, vigência e vínculo com uma reserva ou série.

O principal problema era de **arquitetura de informação do front**. Esses conceitos técnicos estavam apresentados como telas irmãs no menu, sem explicar suas diferenças. Para o usuário da recepção, “Reservas”, “Séries recorrentes”, “Locações avulsas” e “Mensalistas” pareciam variações da mesma função.

A solução adotada não elimina as entidades técnicas e não duplica regras de negócio. Ela organiza o módulo em três pontos de trabalho claros:

- **Agenda:** para enxergar e operar os horários.
- **Reservas:** uma área única, com abas para locações comerciais, ocupações e recorrências.
- **Mensalistas:** para contratos de horário fixo e sua situação financeira.

Financeiro e Cobranças continuam acessíveis no mesmo grupo de menu, mas sem recriar módulos que já existem.

## 2. O que existia no plugin

### 2.1 Estrutura técnica encontrada

O plugin já continha:

- Reservas únicas com bloqueio de concorrência e `lock_version`.
- Recursos múltiplos e buffers antes/depois.
- Verificação de disponibilidade e conflito.
- Tipos de ocupação: locação de cliente, escola, personal, evento, interno e outros.
- Ciclo operacional de reserva: hold, pendente, confirmada, em andamento, concluída, cancelada, expirada e no-show.
- Séries diárias, semanais e mensais.
- Séries abertas, até uma data ou por quantidade.
- Política de conflito por rejeição ou salto de ocorrências conflitantes.
- Alteração de uma ocorrência, desta e futuras ou de toda a série.
- Locação comercial avulsa e recorrente.
- Vínculo entre locação e reserva/série.
- Valor de tabela, valor negociado, desconto, vencimento e vigência.
- Status comercial: rascunho, ativo, suspenso, cancelado, concluído e arquivado.
- Histórico append-only de eventos.
- Geração de contas a receber para avulsas e geração mensal para mensalistas.
- Pagamentos, alocações, estornos, caixa e contas a receber.
- Escopo por unidade, permissões e CSRF no grupo de rotas.

Portanto, reconstruir o banco e a lógica do zero seria desperdício. O trabalho correto era tornar essa estrutura compreensível e operacional no front.

### 2.2 Duplicações percebidas

As duplicações eram principalmente de apresentação:

| Tela anterior | Conteúdo | Problema |
|---|---|---|
| Reservas | Lista de ocupações concretas | Parecia outra lista de locações. |
| Séries recorrentes | Lista de regras recorrentes | Conceito técnico exposto como módulo principal. |
| Locações avulsas | Lista de acordos comerciais | Nome não correspondia à lista, que também incluía recorrentes. |
| Mensalistas | Lista recorrente específica | Necessária, mas desconectada das demais. |

Comparando o código original, as telas de listagem repetiam a mesma estrutura de card, título, filtros, DataTable e ações. A similaridade textual foi de aproximadamente:

- `booking_series/index.php` × `court_rentals/index.php`: **62,7%**.
- `bookings/index.php` × `booking_series/index.php`: **49,2%**.
- `single_modal.php` × `monthly_modal.php`: **71,3%**.

Isso não significa que as entidades devam ser fundidas no banco. Significa que o usuário não precisa navegar entre várias páginas quase iguais para consultar a mesma operação.

## 3. Comparação com o modelo funcional identificado na pesquisa

Os padrões úteis encontrados em sistemas de gestão de quadras foram reduzidos ao necessário para o Grupo Donato.

| Necessidade prática | Estado anterior | Decisão desta entrega |
|---|---|---|
| Ver ocupação por quadra e horário | Existia, mas com excesso de “camadas” técnicas | Agenda simplificada, com filtros por quadra, status e conteúdo. |
| Criar avulsa rapidamente | Existia em modal denso | Formulário reorganizado por cliente, horário, valor e quadra. |
| Criar horário fixo | Existia em modal muito técnico | Fluxo semanal simplificado, com dias, horário, início, quadra, valor e vencimento. |
| Separar uso interno de locação paga | Existia no tipo de reserva | Ações internas ficam na aba de ocupações/recorrências, não misturadas ao cadastro comercial principal. |
| Consultar contrato do mensalista | Existia | Tela mantida e refinada. |
| Saber se está em dia | Existia por consulta financeira | Situação financeira apresentada como badge na lista de mensalistas. |
| Evitar duplicação de agenda | Existia no backend | Verificação de disponibilidade destacada no formulário. |
| Trabalhar em celular/tablet | Parcial | Filtros fluidos, modais reorganizados e agenda em lista no celular. |
| Manter identidade do Rise | Parcial | Uso prioritário de cards, botões, forms, Select2, DataTables, badges e navegação do Rise. |
| Fila de espera, app público, campeonatos, rateio | Não existia | Fora do escopo. Não são necessários para a primeira operação funcional. |

## 4. Arquitetura final do menu

### Locações

1. **Agenda**
2. **Reservas**
3. **Mensalistas**
4. **Financeiro**
5. **Cobranças** — somente quando o plugin de cobrança está disponível e o usuário possui permissão.

Foram removidos do menu lateral os itens separados “Séries recorrentes” e “Locações avulsas”. As rotas continuam funcionando e redirecionam para a aba correspondente da área unificada.

## 5. Tela “Agenda”

### Objetivo

Mostrar onde há ocupação, bloqueio ou fechamento, sem transformar a tela em um painel técnico de disponibilidade.

### Alterações

- Cabeçalho com ações de nova avulsa, novo mensalista e acesso às reservas.
- Filtro responsivo por quadra.
- Filtro por status da reserva.
- Filtro por tipo de conteúdo:
  - reservas e usos;
  - bloqueios;
  - fechamentos;
  - aberturas excepcionais;
  - disponibilidade padrão.
- Reserva, bloqueio e fechamento habilitados por padrão.
- Grade de 30 minutos.
- Semana como visualização padrão no desktop.
- Lista semanal como visualização padrão no celular.
- Clique em reserva abre o detalhe operacional existente.
- Legenda textual, sem depender apenas de cores.
- Altura adaptada ao viewport.
- Filtros e toolbar reorganizados em telas pequenas.

### Decisão de escopo

A Agenda permanece separada de Reservas porque sua função é visual e temporal. Unificá-la em uma tabela eliminaria a principal ferramenta operacional da recepção.

## 6. Tela “Reservas e locações”

A antiga fragmentação foi transformada em uma única área com três abas.

### Aba 1 — Locações comerciais

Representa o contrato comercial:

- número da locação;
- título;
- cliente;
- tipo avulsa/recorrente;
- quadras;
- vigência;
- valor;
- status;
- atualização;
- ações.

### Aba 2 — Ocupações da agenda

Representa cada horário concreto, inclusive escola, personal, evento e uso interno:

- número da reserva;
- título;
- tipo;
- cliente;
- recursos;
- início e fim;
- status;
- hold;
- atualização;
- ações.

### Aba 3 — Recorrências

Representa as regras que geram horários futuros. É uma visão operacional avançada, não um módulo comercial separado.

### Compatibilidade

As tabelas continuam usando os endpoints existentes de reservas, séries e locações. Não houve fusão de tabelas nem alteração de regras de negócio.

## 7. Formulário de locação avulsa

O formulário anterior era uma sequência compacta de linhas e campos técnicos. O novo layout foi dividido em seções:

1. **Cliente**
   - cliente obrigatório;
   - contato opcional;
   - título da locação.
2. **Horário**
   - início e fim locais;
   - indicação do fuso da unidade.
3. **Condições comerciais**
   - valor negociado;
   - valor de tabela;
   - observações.
4. **Quadras**
   - cards selecionáveis;
   - buffers aparecem apenas quando a quadra é selecionada.
5. **Opções avançadas**
   - desconto;
   - vigência;
   - produto e tabela de preço por ID, preservados temporariamente;
   - ativação imediata;
   - justificativa.

Há validação visual para impedir envio sem quadra e botão explícito de verificação de disponibilidade.

## 8. Formulário de mensalista

O Grupo Donato trabalha principalmente com horários semanais fixos. Por isso o formulário principal foi reduzido ao caso real:

- cliente;
- contato;
- título;
- valor mensal;
- dia de vencimento;
- observações;
- dias da semana;
- hora inicial e final;
- data de início;
- término aberto ou até data;
- quadras;
- prévia de ocorrências.

Frequências diária/mensal, intervalos especiais, limite por quantidade, conflito, status padrão, desconto, tabela de preço e demais opções continuam disponíveis no backend ou na área avançada quando aplicáveis, sem poluir o fluxo comum.

## 9. Tela “Mensalistas”

A tela foi mantida porque possui finalidade própria: administrar contratos fixos.

### Colunas finais

1. Cliente e contato.
2. Quadra.
3. Dia e horário.
4. Valor contratado.
5. Dia de vencimento.
6. Status do contrato.
7. Próxima ocorrência.
8. Situação financeira.
9. Ações.

A tabela anterior tinha informações espalhadas em mais colunas. Cliente/contato e dia/horário foram agrupados para melhorar leitura e responsividade.

### Ações existentes preservadas

- abrir detalhe;
- suspender;
- retomar;
- acessar geração financeira;
- registrar pagamento.

## 10. Detalhe da locação

A página passou a apresentar primeiro o que a recepção precisa:

- cliente e contato;
- quadras;
- data/dia e horário;
- valor contratado;
- vencimento;
- vigência;
- observações;
- resumo financeiro;
- vínculos com agenda;
- condições comerciais;
- ações de ciclo de vida;
- histórico;
- dados técnicos em área secundária.

Para locação avulsa, a data e o horário são convertidos para o fuso da unidade antes da exibição. Para recorrente, são exibidos dias da semana e horário local.

## 11. Responsividade e CSS

### Princípios adotados

- O Rise continua sendo a fonte visual principal.
- Não foi criado tema próprio.
- Não foram sobrescritos estilos globais.
- O CSS adicional está escopado em `.gd-rentals-shell`.
- Select2 ocupa 100% da coluna disponível.
- Filtros `w200`, `w180` e `w120` deixam de forçar largura inadequada dentro do módulo.
- Toolbars quebram linha.
- Botões ocupam largura útil no celular.
- Modais reorganizam colunas verticalmente.
- Tabelas preservam `table-responsive` e DataTables do Rise.
- A agenda troca para lista no celular em vez de comprimir uma semana inteira.

## 12. Arquivos alterados

- `index.php`
- `Config/Constants.php`
- `Controllers/Calendar.php`
- `Controllers/Bookings.php`
- `Controllers/Booking_series.php`
- `Controllers/Court_rentals.php`
- `Language/portuguese/default_lang.php`
- `Views/calendar/index.php`
- `Views/bookings/index.php`
- `Views/booking_series/index.php`
- `Views/court_rentals/index.php`
- `Views/court_rentals/monthly.php`
- `Views/court_rentals/single_modal.php`
- `Views/court_rentals/monthly_modal.php`
- `Views/court_rentals/view.php`
- `Views/components/rentals_nav.php` — novo
- `Views/components/rentals_styles.php` — novo

Nenhuma migration foi criada e nenhuma tabela foi removida ou alterada.

## 13. Pontos que ainda dependem do backend

O front usa o backend existente, mas a revisão encontrou pontos que o Codex deve validar ou ajustar:

1. O filtro por quadra da lista comercial considera atualmente recursos de séries; deve também considerar reservas avulsas.
2. O resumo de horário avulso dentro de `CourtRentalService` usa substring UTC; a apresentação foi corrigida no controller, mas o service deve retornar horário local canônico.
3. Produto e tabela de preço ainda são informados por ID na área avançada; devem receber endpoints de busca e seleção amigável.
4. Ação financeira da lista de mensalistas deve abrir a cobrança correta ou a geração já filtrada para o contrato.
5. Respostas JSON devem possuir `Content-Type: application/json` e formato consistente.
6. Conflitos de concorrência devem retornar mensagem funcional e dados suficientes para atualizar a tela.
7. O estado financeiro deve ser calculado em lote para evitar uma consulta por mensalista em páginas grandes.
8. O calendário deve, quando possível, abrir diretamente a locação comercial vinculada, além da reserva operacional.
9. É necessário executar smoke test autenticado dentro de uma instalação real do Rise.
10. Testes de instalação, banco e concorrência dependem do ambiente completo e não podem ser validados apenas no ZIP isolado.

## 14. Fora do escopo deliberadamente

Não foram criados:

- reserva pública;
- aplicativo;
- fila de espera;
- partidas abertas;
- campeonatos;
- controle de acesso;
- rateio entre jogadores;
- comanda/bar dentro de locações;
- política automática de chuva;
- cupom e preço dinâmico;
- dashboard novo de locações;
- novas tabelas;
- integração bancária.

Esses recursos podem ser úteis no futuro, mas não são necessários para tornar a operação atual funcional.

## 15. Validação executada

- PHP lint em **334 arquivos**: aprovado.
- Versão do metadata e `Constants::PLUGIN_VERSION`: `0.9.2` em ambos.
- Schema target e marker de teste: `049`.
- Rotas obrigatórias e grupo CSRF: aprovados pelo `verify-fast.sh`.
- Catálogo de idioma: **1042 chaves `gd_*` únicas**, sem duplicatas.
- Todas as referências literais `app_lang("gd_...")`: resolvidas.
- Referências de URI das views: compatíveis com as rotas do plugin.
- `verify-fast.sh`: aprovado em ambiente estático com consulta ao banco desabilitada.

Não foi executado `verify-full.sh`, pois ele exige uma instalação completa do Rise, banco configurado, tabelas reais, CLI do projeto e testes de concorrência.
