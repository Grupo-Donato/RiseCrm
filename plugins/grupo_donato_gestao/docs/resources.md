# Recursos físicos (Fase 2B)

`gd_resources` representa **recursos físicos** que poderão ser reservados em fases
futuras (agenda). Nesta fase é só cadastro: **sem disponibilidade, horários,
reservas, conflitos, buffers ou preço embutido**. Preços pertencem ao módulo de
preços ([pricing.md](pricing.md)).

Agenda futura adicionará disponibilidade, bloqueios, reservas, recorrência e
detecção de conflito. Nada disso é inferido de `is_bookable`: a flag apenas
classifica se o recurso poderá participar desse fluxo.

## Campos
`unit_id`, `business_area_id` (opcional), `cost_center_id` (opcional), `code`,
`name`, `resource_type`, `description`, `capacity` (opcional), `is_bookable`,
`is_active`, `sort_order`, `metadata` (JSON), auditoria, `deleted`.

## Tipos (`resource_type`)
`court`, `barbecue_area`, `event_space`, `bar_area`, `locker_room`, `parking`,
`equipment`, `room`, `other`. Persistidos em `VARCHAR`, validados em PHP.

## Regras (`ResourceService`)
- `code` e `name` obrigatórios; `code` único por unidade entre não excluídos
  (índice normalizado `active_code_key`).
- `business_area_id` **opcional** (decisão arquitetural); quando informada deve
  ser ativa e pertencer à unidade (ou ser global).
- `cost_center_id` opcional; quando informado deve pertencer à unidade e ser
  compatível com a área selecionada.
- `capacity` opcional e **não negativa**.
- `is_bookable` informa se o recurso poderá ser reservado no futuro; não cria
  agenda nem disponibilidade.
- `metadata` validada como JSON antes de persistir (TEXT/MEDIUMTEXT).
- Não exclui logicamente com **preço específico ativo** vinculado.

## Seeds reais Q2–Q6 e churrasqueiras

`CatalogSeeder` cadastra, de forma **idempotente**, as quadras reais de
infraestrutura na unidade padrão:

| Código | Nome | Tipo | Ativo | Reservável |
|---|---|---|---|---|
| Q2 | Quadra Q2 | court | sim | sim |
| Q3 | Quadra Q3 | court | sim | sim |
| Q4 | Quadra Q4 | court | sim | sim |
| Q5 | Quadra Q5 | court | sim | sim |
| Q6 | Quadra Q6 | court | sim | sim |

As churrasqueiras são mantidas de forma idempotente como `CH1` a `CH4` e `CH5-SL`,
com nomes `Churrasqueira 1` a `Churrasqueira 4` e `Churrasqueira 5 / Salão`, tipo
`barbecue_area`, ativas e reserváveis. A antiga `CH6` é retirada por exclusão lógica
para preservar reservas e histórico. Se um recurso com o mesmo código ou nome já
existir na unidade padrão, ele é reaproveitado e classificado para o módulo em vez
de ser duplicado.

Sem preço, sem capacidade inventada, sem dimensão inventada, sem descrição
comercial e sem área/centro automáticos. Salão, bar, estacionamento e vestiários
continuam sem seed comercial específico.

## Interface
Lista (server-side) com colunas Código, Nome, Tipo, Área, Centro, Capacidade,
Reservável, Status, Atualização e ações. Detalhe exibe dados gerais, área,
centro, metadata formatada e os produtos com preço específico para o recurso.
Disponibilidade e calendário foram adicionados na Fase 3A em tabelas próprias: regras semanais, exceções e bloqueios. O detalhe do recurso oferece acesso a essas telas, mas nenhum horário é armazenado em `gd_resources`.

Valor, custo, quantidade disponível, saldo de estoque, tarifa, horário e
ocupação não pertencem a `gd_resources`.

O recurso continua sendo apenas o objeto físico. `is_active=0`, `is_bookable=0`, soft delete ou ausência de regra aplicável tornam a consulta indisponível. A agenda-base não cria ocupação/reserva; isso permanece para a Fase 3B.
