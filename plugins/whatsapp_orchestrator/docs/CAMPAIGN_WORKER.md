# Campanhas e worker

## Tipos

- **Oficial**: Meta Cloud API, template aprovado e parâmetros estruturados.
- **Não oficial**: Evolution, texto/mídia conforme capacidades do canal.

## Fluxo

1. A campanha é validada e salva.
2. A agenda cria uma ocorrência em `chat_campaign_runs`.
3. O público é congelado em `chat_campaign_run_recipients`.
4. O worker seleciona registros disponíveis sob lock.
5. Antes de cada envio, confere opt-out, estado do canal, horário e limites.
6. O provedor envia e devolve ID externo.
7. Webhooks atualizam enviado, entregue, lido, respondido ou falha.
8. Falhas transitórias recebem retry com atraso; falhas definitivas encerram o destinatário.

## Idempotência

A chave inclui campanha, ocorrência e destinatário. Recorrências não reutilizam a mesma chave. Recibos tardios continuam associados à ocorrência correta.

## Agendamento

Execute por minuto:

```bash
php plugins/Chatwoot_plugin/cron.php 50
```

O limite pode variar de 1 a 200. Use um usuário de sistema com acesso somente ao diretório necessário. Não exponha `cron.php` via HTTP; o arquivo encerra fora de CLI.

## Operação

O histórico da campanha mostra ocorrências e destinatários, com tentativas, status, último evento e erro. Ao atualizar uma instalação antiga, campanhas antes controladas por n8n são pausadas e devem ser revisadas antes da reativação.

## Disparador automático no Grupo Donato

Na aba **Campanhas → Nova campanha**, selecione o canal Evolution e informe o público,
texto (ou legenda com anexo), início, horário, dias da semana, fim opcional e intervalo
mínimo entre mensagens (padrão: 15 segundos). O anexo usa o serviço de mídia existente e
permanece disponível para as próximas ocorrências. O `account`/`inbox` do fluxo antigo é
substituído pelo canal nativo `instance_id`. As campanhas existentes no N8N não são
importadas nem alteradas.

A lista manual aceita um telefone por linha ou a lista JSON do fluxo:

```json
[{"numero":"5511999999999","variaveis":{"nome":"Ana","pedido":"123"}}]
```

A mensagem `Olá {nome}, seu pedido é {pedido}.` é personalizada por destinatário.
Telefones duplicados são consolidados e o opt-out é conferido novamente antes do envio.

Os campos adicionais da API de campanhas são `ends_at`, `interval_seconds`, `numbers`,
`media_id` e `idempotency_key`. `ends_at` usa o fuso da campanha quando não contém offset;
`interval_seconds` aceita 0–3600; `media_id` precisa pertencer ao mesmo canal e não pode
estar vinculado a uma conversa. Repetir o mesmo `idempotency_key` retorna o cadastro
original; reutilizá-lo com conteúdo diferente responde 409.

`GET .../campaigns/ID` retorna a lista manual e as variáveis para edição. `POST
.../campaigns/ID/stop` encerra de forma idempotente e bloqueia os próximos envios. O menu
**Encerrar disparos** usa essa ação; para reiniciar uma campanha encerrada, duplique-a.

Recorrências usam uma janela inicial de 120 segundos no fuso informado. Janelas perdidas
são puladas; uma ocorrência já iniciada pode continuar até terminar ou atingir `ends_at`.
O worker verifica a fila a cada 15 segundos. O intervalo configurado e o limite por
minuto são mínimos/verificados antes do envio e podem aumentar com a ocupação da fila.
Não há migração de schema: os novos campos ficam em `schedule_json`.

Validação automatizada e limitações: [CAMPAIGN_DISPATCH_VALIDATION.md](CAMPAIGN_DISPATCH_VALIDATION.md).
