# Perguntas pendentes de validação com o cliente

Nenhuma das 35 perguntas bloqueia as Fases 1, 2A ou 2B. Não foram inventadas respostas comerciais. “Decisão provisória” indica apenas o padrão técnico seguro até a fase afetada. A lista inicial de papéis foi implementada conforme o escopo da Fase 2A, e a Fase 2B entregou estrutura configurável sem preencher respostas comerciais.

## Validações comerciais abertas após a Fase 2B

| Tema | Estado | Decisão técnica segura já aplicada |
|---|---|---|
| Preço das quadras, fim de semana e horário de pico | `CLIENT_VALIDATION` | nenhum preço seedado; vigência/tabelas/escopo por recurso suportados |
| Capacidade e dimensões de Q2–Q6 | `CLIENT_VALIDATION` | campos permanecem nulos; não inventar valores |
| Área/centro das quadras e área de eventos | `CLIENT_VALIDATION` | área/centro opcionais; Q2–Q6 sem associação automática |
| Categorias e produtos/serviços reais | `CLIENT_VALIDATION` | catálogo vazio; nenhum produto comercial seedado |
| Tamanhos/atributos de uniformes | `CLIENT_VALIDATION` | variações e attributes JSON validados, sem tamanhos seedados |
| Tabelas promocionais e regras de prioridade | `CLIENT_VALIDATION` | apenas `DEFAULT` vazia; sem promoção automática |
| Recursos de salão/bar/churrasqueira/estacionamento/vestiário | `CLIENT_VALIDATION` | tipos existem, cadastros não foram inventados |
| Produtos, ficha técnica e estoque do bar | `BLOCKING_FUTURE_PHASE` | catálogo pode classificar produtos; estoque/bar não iniciados |

Decisões técnicas respondidas: área é opcional em produto e recurso; Q2–Q6
permanecem sem área; metadata/attributes usam JSON textual validado. Essas
decisões não respondem preços, capacidade, dimensões ou operação do cliente.

| # | Pergunta original | Classificação | Fase afetada | Decisão provisória | Cliente? |
|---|---|---|---|---|---|
| 1 | As quadras Q2–Q6 e o bar/eventos pertencem a **uma única unidade física** ou há mais de uma filial? (Define se multiunidade é central ou simplificável.) | BLOCKING_FUTURE_PHASE | 2–3 | Fundação suporta várias unidades; ACL por unidade adiada. | Sim |
| 2 | Quais **centros de resultado** o cliente quer apurar (por quadra? por área? por produto)? Existe DRE atual a espelhar? | BLOCKING_FUTURE_PHASE | 6/11/14 | CRUD vazio; não seedar centros comerciais. | Sim |
| 3 | Há **CNPJs distintos** por área (escola × bar × eventos) com necessidade fiscal separada? | BLOCKING_FUTURE_PHASE | 2/6/15 | Unidade possui documento opcional; fiscal não iniciado. | Sim |
| 4 | Um adulto pode ser **participante e pagante de si mesmo** (sem responsável)? (Modelo já suporta, mas confirma regras de UI.) | CLIENT_VALIDATION | 2B | A Fase 2A permite conta individual e responsabilidade financeira; regra comercial ainda precisa confirmação. | Sim |
| 5 | Quais **papéis** de pessoa importam (pai/mãe/responsável legal/financeiro/contato de emergência)? | ANSWERED_BY_IMPLEMENTATION | 2A | Lista inicial fixa em PHP entregue; expansão futura requer nova decisão. | Sim |
| 6 | Coleta-se **dado médico** (atestado, restrição)? Há exigência legal de guarda/retenção? | BLOCKING_FUTURE_PHASE | 2/4 | Não coletar até política LGPD aprovada. | Sim |
| 7 | Modalidades/programas e faixas etárias atuais (lista real)? | CLIENT_VALIDATION | 4 | Não seedar catálogo comercial. | Sim |
| 8 | Vínculo **turma ↔ quadra** é fixo? A turma deve **bloquear** a quadra na agenda automaticamente? | BLOCKING_FUTURE_PHASE | 3–4 | Agenda é fonte de ocupação; regra depende do cliente. | Sim |
| 9 | Existem **aulas avulsas/pacotes de crédito** (ex.: “pacote de 8”)? Regras de validade? | BLOCKING_FUTURE_PHASE | 2/4 | Não implementar pacote até resposta. | Sim |
| 10 | Política de **reposição/falta justificada** afeta cobrança? | CONFIGURABLE | 4/6 | Política futura configurável, sem default financeiro. | Sim |
| 11 | Treino **noturno** e **grupos de pais** têm preço/regra diferentes? | CLIENT_VALIDATION | 2/4 | Modelar como programa/preço configurável. | Sim |
| 12 | Diferença operacional entre **locação avulsa** e **mensal**: preço por hora? pacote mensal fixo? horários fixos recorrentes? | BLOCKING_FUTURE_PHASE | 3/5/6 | Separar avulsa de contrato/série recorrente. | Sim |
| 13 | Permite **overbooking** ou lista de espera? Política de cancelamento/no-show? | CONFIGURABLE | 3/5 | Default seguro: sem overbooking. | Sim |
| 14 | Reserva **online pública** é desejada agora? Exige pagamento antecipado? | BLOCKING_FUTURE_PHASE | 3/6 | Nenhum endpoint público até decisão e threat model. | Sim |
| 15 | Personal é **profissional interno** (na folha) ou **autônomo** que aluga a quadra? Como entra a receita (split com a casa)? | BLOCKING_FUTURE_PHASE | 5/6 | Não assumir vínculo nem split. | Sim |
| 16 | Formas de pagamento aceitas (dinheiro, PIX, débito, crédito, boleto, “crédito da casa”)? Há **gateway** a integrar? | BLOCKING_FUTURE_PHASE | 6/15 | Catálogo/gateway configurável; nada seedado. | Sim |
| 17 | Mensalidades: dia de vencimento fixo por aluno? Geração **automática** mensal? Política de **juros/multa/desconto** por antecipação? | CONFIGURABLE | 6/12 | Regras versionadas/configuráveis, sem default monetário. | Sim |
| 18 | **Inadimplência:** após quantos dias marca inadimplente? Bloqueia acesso/treino? | CONFIGURABLE | 6/12 | Não bloquear automaticamente sem regra aprovada. | Sim |
| 19 | Emite **recibo/comprovante** padrão? Precisa de **nota fiscal** (serviço/produto)? | BLOCKING_FUTURE_PHASE | 6/15 | Recibo e fiscal separados; fiscal não presumido. | Sim |
| 20 | Há **rateio** real de receita/custo entre centros, ou é só relatório? | BLOCKING_FUTURE_PHASE | 11/14 | Não criar lançamentos de rateio até resposta. | Sim |
| 21 | Quantos **caixas/PDVs** operam? Abertura/fechamento por turno com conferência? | CLIENT_VALIDATION | 7 | Modelo futuro suporta múltiplos caixas/sessões. | Sim |
| 22 | Bar trabalha com **comanda** vinculada à quadra/evento, ou venda direta no balcão? | BLOCKING_FUTURE_PHASE | 7 | Permitir origem opcional; fluxo depende do cliente. | Sim |
| 23 | O bar **controla estoque** item a item? Há **fichas técnicas** (insumo→produto)? | BLOCKING_FUTURE_PHASE | 7–8 | Não baixar estoque sem definição. | Sim |
| 24 | **Locais de estoque** reais (bar, almoxarifado, loja de uniformes)? | CLIENT_VALIDATION | 8 | Não seedar locais fictícios. | Sim |
| 25 | Uniformes/produtos: controla **variação** (tamanho)? Faz **inventário** periódico? | CLIENT_VALIDATION | 2/8 | Modelo futuro deve suportar variação opcional. | Sim |
| 26 | Tipos de evento e o que compõe o pacote (espaço, tempo de montagem/limpeza, adicionais)? | BLOCKING_FUTURE_PHASE | 3/9 | Recursos e buffers configuráveis. | Sim |
| 27 | Trabalha com **caução**? Valor/regra de retenção/devolução? | BLOCKING_FUTURE_PHASE | 9 | Não assumir valor ou regra. | Sim |
| 28 | Faz **vistoria** de entrada/saída do espaço? | CLIENT_VALIDATION | 9 | Checklist opcional e versionado. | Sim |
| 29 | Inscrição é por **equipe** ou **atleta**? Há taxa? Premiação/custos a controlar? | BLOCKING_FUTURE_PHASE | 10 | Modelo só após definição. | Sim |
| 30 | Existe base atual (sistema legado, planilhas, outro sistema) a **migrar**? Em que formato? Volume aproximado de pessoas/cobranças/pagamentos? | BLOCKING_FUTURE_PHASE | 13 | Nenhuma importação automática; preservar origem. | Sim |
| 31 | Há histórico financeiro que precisa ser **preservado** (saldos, inadimplência)? | BLOCKING_FUTURE_PHASE | 6/13 | Não migrar/sintetizar saldo sem reconciliação. | Sim |
| 32 | Perfis de usuário reais e o que cada um pode fazer (validar matriz de [permissions-plan.md](permissions-plan.md))? | CLIENT_VALIDATION | 2+ | Mecanismo de permissões está pronto; papéis não são seedados. | Sim |
| 33 | Professores registram **a própria chamada**? Por app/tablet na quadra? | BLOCKING_FUTURE_PHASE | 4 | Endpoint/UI móvel não iniciados. | Sim |
| 34 | WhatsApp/mensageria para lembretes/cobrança — qual provedor? | BLOCKING_FUTURE_PHASE | 15 | Sem integração/segredo nesta fase. | Sim |
| 35 | Calendário externo (Google) para agenda? Emissor fiscal/PDF específico? | BLOCKING_FUTURE_PHASE | 15 | Adaptadores somente após escolha do provedor. | Sim |

Classificação sem ocorrência: `BLOCKING_PHASE_1`. `ANSWERED_BY_IMPLEMENTATION`
aplica-se somente a decisões técnicas explicitamente marcadas; nenhuma pergunta
de preço, capacidade, agenda, estoque ou operação comercial foi encerrada pelo código.

## Após a Fase 3A

Não há pergunta bloqueadora para disponibilidade. Horários reais de Q2–Q6 continuam deliberadamente sem resposta e sem seed. Para a Fase 3B ainda precisam ser definidos ciclo/status de reserva, política de hold/expiração, recorrência, overbooking, vínculo de clientes e regras comerciais; nenhuma dessas decisões foi inferida nesta fase.

## Após a Fase 3B1

Ciclo técnico, hold e default sem overbooking foram definidos pelo escopo: hold padrão de 30 minutos configurável, buffers por recurso e cliente obrigatório apenas nos tipos comerciais. Permanecem abertas para 3B2/3C as regras de recorrência, mensalistas, preço, sinal, cancelamento comercial, crédito, cobrança, check-in/out e integrações.

## Após a Fase 3B2

Recorrência simples, mês inexistente, conflitos e escopos de alteração/cancelamento foram definidos. Permanecem abertas para 3C as regras comerciais: mensalistas, contrato, preço, sinal, cancelamento comercial, crédito, cobrança e check-in/out. RRULE arbitrária continua fora do escopo.
