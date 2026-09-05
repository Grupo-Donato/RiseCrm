# GD Academy — eventos esportivos

O módulo de eventos vive dentro da tela operacional oficial da Academy. A entrada é `grupo_donato/operacional?gd_tab=eventos`; não há uma segunda tela de alunos, responsáveis ou unidade.

## Arquitetura

- Alunos e responsáveis internos continuam sendo lidos de `grupo_donato_alunos` e `grupo_donato_responsaveis`.
- A unidade legada é mapeada para uma unidade ativa de `gd_units`. O mapeamento é feito por id, nome ou unidade padrão, nesta ordem.
- Categorias, partidas, convocação, confirmações, atletas externos, checklist, avaliações e estatísticas são armazenados nas tabelas `gd_academy_*` criadas pela V067.
- Atleta externo é um registro contextual do evento. Ele não cria um segundo aluno nem um segundo responsável.

## Fluxo

1. Criar o evento com período, local, organizador e valor padrão.
2. Criar categorias com faixa etária, gênero, equipe técnica, limite e valor.
3. Registrar partidas e atualizar o placar após o jogo.
4. Pesquisar alunos da operação e adicionar convidados externos com dados de origem.
5. Registrar confirmação e escalação. A mudança da confirmação também grava a linha de confirmação/auditoria.
6. Gerar o recebível individual, baixar pagamentos parciais ou totais e consultar a conta familiar.
7. Registrar avaliação de 1 a 5, pontos fortes, desenvolvimento, recomendação e nota interna.
8. Registrar estatísticas por partida e concluir o checklist.
9. Finalizar somente sem pendências; quando houver pendência, o backend exige justificativa explícita. Cancelamento tenta cancelar recebíveis abertos e preserva aviso para recebíveis já pagos.

## Financeiro

O recebível usa `source_type=academy_event_participation` e `source_id=participant_id`. A chave `(unit_id, source_type, source_id, reference_month, deleted)` mantém a geração idempotente. O `reference_month` do evento fica vazio de propósito: cada participação é uma cobrança única, não uma mensalidade.

Para aluno interno, o responsável legado é ligado a uma única conta familiar moderna por `gd_customer_accounts.legacy_responsible_id`. A tela de conta familiar mostra os recebíveis modernos do evento e também as cobranças mensais legadas, sem migrá-las ou duplicá-las.

Recebíveis pagos não são apagados nem cancelados; o cancelamento do evento retorna o aviso correspondente. Pagamentos usam `FinanceService`, contas financeiras existentes, alocação parcial e trilha de auditoria.

## Permissões

O acesso ao menu **GD Academy** é a autorização do módulo. Quem pode visualizar
esse menu pode operar todos os recursos da Academy, inclusive criar/editar
eventos, categorias e partidas, convocar atletas, registrar avaliações,
movimentar o financeiro do evento e finalizar ou cancelar eventos.

As chaves `gd_academy_events_*` e `gd_academy_evaluations_*` continuam sendo
aceitas pelo backend para compatibilidade com as rotas existentes, mas não
restringem usuários que já têm acesso ao menu GD Academy.

Os endpoints continuam sob o grupo CSRF de `grupo_donato/operacional` e todas as consultas de domínio são limitadas por `unit_id`.

## Endpoints principais

`eventos_list_data`, `evento_modal_form`, `save_event`, `save_event_category`, `save_event_match`, `save_event_match_score`, `save_event_staff`, `academy_student_search`, `add_event_participant`, `update_event_participant`, `save_event_confirmation`, `event_charge`, `event_payment`, `save_event_evaluation`, `save_event_stat`, `save_event_checklist`, `toggle_event_checklist`, `event_family_account`, `student_sport_history`, `finalize_event` e `cancel_event`.

## Verificação

```text
C:\xampp\php\php.exe plugins/grupo_donato_gestao/Tests/cli.php academy-events-selftest
```

The student search ranks athletes inside the category age range first but does not block authorized exceptions. External athletes keep birth date, responsible, phone and origin club in their contextual record. The event workspace includes an auditable history, and the existing student profile exposes a printable three-month development report with criterion averages; internal notes remain private.

O self-test cria um evento identificável, executa o fluxo interno completo e remove somente os ids criados pelo teste.
