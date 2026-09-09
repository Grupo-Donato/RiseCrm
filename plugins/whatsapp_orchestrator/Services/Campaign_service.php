<?php

declare(strict_types=1);

namespace Chatwoot_plugin\Services;

use Chatwoot_plugin\Models\Chat_campaign_templates_model;
use Chatwoot_plugin\Models\Chat_campaigns_model;
use Chatwoot_plugin\Models\Chat_instances_model;
use Chatwoot_plugin\Models\Chat_settings_model;
use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use RuntimeException;

class Campaign_service
{
    private BaseConnection $db;

    public function __construct(
        private ?Chat_campaigns_model $campaigns = null,
        private ?Chat_campaign_templates_model $templates = null,
        private ?Chat_instances_model $instances = null,
        private ?Chat_settings_model $settings = null,
        private ?Contact_service $contacts = null,
        private ?Audit_service $audit = null,
        ?BaseConnection $db = null
    ) {
        $this->campaigns ??= new Chat_campaigns_model();
        $this->templates ??= new Chat_campaign_templates_model();
        $this->instances ??= new Chat_instances_model();
        $this->settings ??= new Chat_settings_model();
        $this->contacts ??= new Contact_service();
        $this->audit ??= new Audit_service();
        $this->db = $db ?? db_connect('default');
    }

    public function list(array $filters, int $page, int $limit): array
    {
        $result = $this->campaigns->paginate_records($filters, $page, $limit);
        $result['data'] = array_map([$this, 'map'], $result['data']);
        return $result;
    }

    /** @return array{month:int,sent:int,delivery_rate:string,reply_rate:string} */
    public function summary(): array
    {
        $table = $this->db->prefixTable('chat_campaigns');
        $month = $this->db->table($table)->where($table . '.deleted', 0)->where($table . '.created_at >=', gmdate('Y-m-01 00:00:00'))->countAllResults();
        $rows = $this->db->table($table)->select($table . '.metrics_json')->where($table . '.deleted', 0)->get()->getResultArray();
        $sent = $delivered = $replied = 0;
        foreach ($rows as $row) {
            $metrics = $this->json((string) ($row['metrics_json'] ?? ''));
            $sent += max(0, (int) ($metrics['sent'] ?? 0));
            $delivered += max(0, (int) ($metrics['delivered'] ?? 0));
            $replied += max(0, (int) ($metrics['replied'] ?? 0));
        }
        return [
            'month' => $month,
            'sent' => $sent,
            'delivery_rate' => ($sent > 0 ? round($delivered * 100 / $sent, 1) : 0) . '%',
            'reply_rate' => ($sent > 0 ? round($replied * 100 / $sent, 1) : 0) . '%',
        ];
    }

    public function get(int $id): ?array
    {
        $row = $this->campaigns->get_by_id($id);
        if (!$row) return null;
        $mapped = $this->map($row);
        $recipients = $this->db->table('chat_campaign_recipients')->where('campaign_id', $id)->where('deleted', 0)->orderBy('id', 'ASC')->get()->getResultArray();
        $mapped['numbers'] = array_map(fn (array $r): array => ['numero' => $r['phone_normalized'], 'variaveis' => $this->json((string) ($r['variables_json'] ?? ''))], $recipients);
        return $mapped;
    }

    public function audience_preview(array $input): array
    {
        $instanceId = (int) ($input['instance_id'] ?? 0);
        if (!$this->instances->get_by_id($instanceId)) {
            throw new InvalidArgumentException('Instancia da campanha invalida.');
        }
        $include = $this->normalizeTags($input['include_tags'] ?? []);
        $exclude = $this->normalizeTags($input['exclude_tags'] ?? []);
        $source = strtolower(trim((string) ($input['audience_source'] ?? 'contacts')));
        if (!in_array($source, ['contacts', 'manual', 'csv', 'students'], true)) {
            throw new InvalidArgumentException('Fonte de publico invalida.');
        }
        $contactsTable = $this->db->prefixTable('chat_contacts');
        $contactTagsTable = $this->db->prefixTable('chat_contact_tags');
        $tagsTable = $this->db->prefixTable('chat_tags');
        $rows = $source === 'contacts' ? $this->db->table($contactsTable)
            ->select($contactsTable . '.id, ' . $contactsTable . '.name, ' . $contactsTable . '.phone_normalized, ' . $contactsTable . '.company, ' . $contactsTable . '.city, ' . $contactsTable . '.opt_out')
            ->select('GROUP_CONCAT(DISTINCT LOWER(' . $tagsTable . '.normalized_name) SEPARATOR \',\') AS tag_names', false)
            ->join($contactTagsTable, $contactTagsTable . '.contact_id = ' . $contactsTable . '.id AND ' . $contactTagsTable . '.deleted = 0', 'left')
            ->join($tagsTable, $tagsTable . '.id = ' . $contactTagsTable . '.tag_id AND ' . $tagsTable . '.deleted = 0', 'left')
            ->where($contactsTable . '.deleted', 0)
            ->groupStart()->where($contactsTable . '.instance_id', $instanceId)->orWhere($contactsTable . '.instance_id IS NULL', null, false)->groupEnd()
            ->groupBy($contactsTable . '.id')
            ->limit(10000)
            ->get()->getResultArray() : [];
        $recipients = [];
        $excludedOptOut = 0;
        $excludedFilter = 0;
        foreach ($rows as $row) {
            $tags = array_filter(explode(',', (string) ($row['tag_names'] ?? '')));
            if ($include && array_diff($include, $tags)) {
                $excludedFilter++;
                continue;
            }
            if ($exclude && array_intersect($exclude, $tags)) {
                $excludedFilter++;
                continue;
            }
            if (!empty($row['opt_out'])) {
                $excludedOptOut++;
                continue;
            }
            $phone = (string) $row['phone_normalized'];
            $recipients[$phone] = ['contact_id' => (int) $row['id'], 'phone' => $phone, 'name' => (string) $row['name'], 'company' => (string) ($row['company'] ?? ''), 'city' => (string) ($row['city'] ?? '')];
        }
        $invalid = 0;
        if ($source === 'students') {
            foreach ($this->studentAudienceRows($input) as $row) {
                try {
                    $phone = $this->contacts->normalize_phone((string) ($row['responsavel_phone'] ?? ''));
                    $contact = $this->db->table($contactsTable)
                        ->select('id, opt_out')
                        ->where('phone_normalized', $phone)
                        ->where('deleted', 0)
                        ->groupStart()->where('instance_id', $instanceId)->orWhere('instance_id IS NULL', null, false)->groupEnd()
                        ->orderBy('opt_out', 'DESC')->get(1)->getRowArray();
                    if ($contact && !empty($contact['opt_out'])) {
                        $excludedOptOut++;
                        continue;
                    }
                    $studentName = trim((string) ($row['nome_aluno'] ?? ''));
                    $responsibleName = trim((string) ($row['responsavel_nome'] ?? ''));
                    $variables = array_filter([
                        'nome' => $responsibleName,
                        'responsavel' => $responsibleName,
                        'nome_responsavel' => $responsibleName,
                        'aluno' => $studentName,
                        'nome_aluno' => $studentName,
                        'matricula' => trim((string) ($row['matricula'] ?? '')),
                        'turma' => trim((string) ($row['turma'] ?? '')),
                        'unidade' => trim((string) ($row['unidade_nome'] ?? '')),
                    ], static fn ($value): bool => $value !== '');
                    $entry = [
                        'contact_id' => $contact ? (int) $contact['id'] : null,
                        'phone' => $phone,
                        'name' => $responsibleName ?: $phone,
                        'company' => trim((string) ($row['unidade_nome'] ?? '')),
                        'city' => trim((string) ($row['unidade_cidade'] ?? '')),
                        'variables' => $variables,
                    ];
                    if (isset($recipients[$phone])) {
                        $previous = $recipients[$phone];
                        $names = array_filter(array_map('trim', explode(', ', (string) ($previous['variables']['alunos'] ?? ''))));
                        if ($studentName && !in_array($studentName, $names, true)) $names[] = $studentName;
                        if ($names) {
                            $entry['variables']['alunos'] = implode(', ', $names);
                            $entry['variables']['aluno'] = $entry['variables']['alunos'];
                            $entry['variables']['nome_aluno'] = $entry['variables']['alunos'];
                        }
                        $recipients[$phone]['variables'] = array_merge($previous['variables'] ?? [], $entry['variables']);
                    } else {
                        $entry['variables']['alunos'] = $studentName;
                        $recipients[$phone] = $entry;
                    }
                } catch (InvalidArgumentException $exception) {
                    $invalid++;
                }
            }
        }
        $manualNumbers = $source === 'students' ? [] : (is_array($input['manual_numbers'] ?? null) ? $input['manual_numbers'] : (is_array($input['numbers'] ?? null) ? $input['numbers'] : []));
        foreach ($manualNumbers as $entry) {
            $number = is_array($entry) ? ($entry['phone'] ?? $entry['numero'] ?? $entry['number'] ?? '') : $entry;
            $customVariables = is_array($entry) ? ($entry['variables'] ?? $entry['variaveis'] ?? []) : [];
            if (!is_array($customVariables)) $customVariables = [];
            foreach (['name' => 'name', 'nome' => 'name', 'company' => 'company', 'empresa' => 'company', 'city' => 'city', 'cidade' => 'city'] as $sourceKey => $targetKey) {
                if (is_array($entry) && isset($entry[$sourceKey]) && is_scalar($entry[$sourceKey])) $customVariables[$targetKey] = trim((string) $entry[$sourceKey]);
            }
            try {
                $phone = $this->contacts->normalize_phone((string) $number);
                $optOut = $this->db->table('chat_contacts')->select('id, name, company, city, opt_out')->where('phone_normalized', $phone)->where('deleted', 0)->groupStart()->where('instance_id', $instanceId)->orWhere('instance_id IS NULL', null, false)->groupEnd()->orderBy('opt_out', 'DESC')->get(1)->getRowArray();
                if ($optOut && !empty($optOut['opt_out'])) {
                    $excludedOptOut++;
                    continue;
                }
                $recipients[$phone] = [
                    'contact_id' => $optOut ? (int) $optOut['id'] : null,
                    'phone' => $phone,
                    'name' => trim((string) ($customVariables['name'] ?? $optOut['name'] ?? $phone)) ?: $phone,
                    'company' => trim((string) ($customVariables['company'] ?? $optOut['company'] ?? '')),
                    'city' => trim((string) ($customVariables['city'] ?? $optOut['city'] ?? '')),
                    'variables' => array_slice($customVariables, 0, 100, true),
                ];
            } catch (InvalidArgumentException $exception) {
                $invalid++;
            }
        }
        $items = array_values($recipients);
        return [
            'count' => count($items),
            'excluded_opt_out' => $excludedOptOut,
            'excluded_by_filter' => $excludedFilter,
            'invalid' => $invalid,
            'sample' => array_slice($items, 0, 20),
            'recipients' => $items,
        ];
    }

    public function save(array $input, int $actorId, ?int $id = null): array
    {
        $key = trim((string) ($input['idempotency_key'] ?? ''));
        $lock = $id ? 'chat_campaign_dispatch_' . $id : 'chat_campaign_save_' . substr(hash('sha256', $key ?: $this->uuid()), 0, 40);
        $acquired = $this->db->query('SELECT GET_LOCK(?, 10) acquired', [$lock])->getRowArray();
        if ((int) ($acquired['acquired'] ?? 0) !== 1) throw new RuntimeException('Campanha ocupada; tente novamente.', 409);
        try {
            if (!$id && $key !== '') {
                $existing = $this->db->table('chat_campaigns')->where('idempotency_key', hash('sha256', $key))->get(1)->getRowArray();
                if ($existing) {
                    $schedule = $this->json((string) $existing['schedule_json']);
                    if (!empty($existing['deleted']) || (int) $existing['created_by'] !== $actorId || ($schedule['request_hash'] ?? '') !== hash('sha256', json_encode($input))) {
                        throw new RuntimeException('Identificador de cadastro ja utilizado por outra solicitacao.', 409);
                    }
                    return $this->get((int) $existing['id']) ?: [];
                }
            }
            $this->db->transBegin();
            try {
                $saved = $this->saveUnlocked($input, $actorId, $id);
                if (!$this->db->transStatus()) throw new RuntimeException('Nao foi possivel salvar a campanha.');
                $this->db->transCommit();
                return $saved;
            } catch (\Throwable $e) {
                $this->db->transRollback();
                throw $e;
            }
        } finally {
            $this->db->query('SELECT RELEASE_LOCK(?)', [$lock]);
        }
    }

    private function saveUnlocked(array $input, int $actorId, ?int $id = null): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $instanceId = (int) ($input['instance_id'] ?? 0);
        if ($name === '' || mb_strlen($name) > 191) throw new InvalidArgumentException('Informe um nome de campanha valido.');
        $instance = $this->instances->get_by_id($instanceId);
        if (!$instance || empty($instance['active'])) throw new InvalidArgumentException('Selecione uma instancia ativa.');

        $defaultCampaignType = (($instance['provider_type'] ?? 'evolution') === 'meta_cloud') ? 'official' : 'unofficial';
        $campaignType = strtolower(trim((string) ($input['campaign_type'] ?? $defaultCampaignType)));
        if (!in_array($campaignType, ['official','unofficial'], true)) throw new InvalidArgumentException('Tipo de campanha invalido.');
        $dispatchMode = 'internal_queue';
        if ($campaignType === 'official' && ($instance['provider_type'] ?? '') !== 'meta_cloud') throw new InvalidArgumentException('Campanha oficial exige uma instancia Meta Cloud API.');
        if ($campaignType === 'unofficial' && ($instance['provider_type'] ?? '') !== 'evolution') throw new InvalidArgumentException('Campanha nao oficial exige uma instancia Evolution.');

        $templateId = !empty($input['template_id']) ? (int) $input['template_id'] : null;
        $template = $templateId ? $this->templates->get_by_id($templateId) : null;
        $message = trim((string) ($input['message'] ?? $input['message_content'] ?? ''));
        $templateParameters = $input['template_parameters'] ?? $input['template_parameters_json'] ?? [];
        if (is_string($templateParameters)) {
            $decoded = json_decode($templateParameters, true);
            if (!is_array($decoded)) throw new InvalidArgumentException('Parametros do template oficial invalidos.');
            $templateParameters = $decoded;
        }
        if (!is_array($templateParameters)) $templateParameters = [];
        if ($campaignType === 'official') {
            if (!$template || (int) ($template['instance_id'] ?? 0) !== $instanceId || strtolower((string) ($template['provider_status'] ?? '')) !== 'approved') {
                throw new InvalidArgumentException('Selecione um template oficial aprovado desta instancia.');
            }
            $message = $message !== '' ? $message : (string) ($template['message_content'] ?? ('[Template] ' . $template['name']));
            $templateParameters = $this->validateOfficialTemplateComponents(
                $templateParameters,
                $this->json((string) ($template['components_json'] ?? ''))
            );
        }
        if ($message === '' || mb_strlen($message) > 10000) throw new InvalidArgumentException('Informe uma mensagem de ate 10000 caracteres.');
        $this->validateTemplateVariables($message);

        $before = $id ? $this->campaigns->get_by_id($id) : null;
        if ($id && !$before) throw new RuntimeException('Campanha nao encontrada.', 404);
        if ($before && in_array((string) $before['status'], ['running','completed'], true)) {
            throw new RuntimeException('Pause ou duplique a campanha antes de alterar seu conteudo.', 409);
        }
        $preview = $this->audience_preview($input);
        if ($preview['count'] < 1) throw new InvalidArgumentException('O publico da campanha ficou vazio apos filtros e opt-outs.');

        $correlationId = $before['correlation_id'] ?? $this->uuid();
        $idempotencyKey = $before['idempotency_key'] ?? hash('sha256', trim((string) ($input['idempotency_key'] ?? '')) ?: $correlationId);
        $requestedType = (string) ($input['schedule_type'] ?? $input['type'] ?? 'draft');
        if ($requestedType === 'one_time') $requestedType = !empty($input['start_date']) ? 'scheduled' : 'draft';
        if ($requestedType === 'triggered') $requestedType = 'draft';
        $timezone = trim((string) ($input['timezone'] ?? $this->settings->get_value('campaign_recurring_timezone', 'America/Sao_Paulo')));
        $schedule = [
            'type' => $this->scheduleType($requestedType),
            'at' => Campaign_schedule::date($input['schedule_at'] ?? ((string) ($input['start_date'] ?? '') !== '' ? trim((string) $input['start_date']) . ' ' . trim((string) ($input['start_time'] ?? '00:00')) : null), $timezone),
            'days_of_week' => $this->normalizeWeekdays(is_array($input['days_of_week'] ?? null) ? $input['days_of_week'] : (is_array($input['weekdays'] ?? null) ? $input['weekdays'] : [])),
            'start_immediately' => filter_var($input['start_immediately'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'timezone' => $timezone,
            'ends_at' => Campaign_schedule::date($input['ends_at'] ?? null, $timezone),
            'interval_seconds' => Campaign_schedule::interval($input['interval_seconds'] ?? 15),
            'window_seconds' => 120,
            'request_hash' => hash('sha256', json_encode($input)),
        ];
        if ($schedule['ends_at'] && (!$schedule['at'] || strtotime($schedule['ends_at']) <= strtotime($schedule['at']))) throw new InvalidArgumentException('O fim da campanha deve ser posterior ao inicio.');
        if ($schedule['type'] === 'scheduled' && !$schedule['at']) throw new InvalidArgumentException('Informe a data e o horario do disparo.');
        if ($schedule['type'] === 'recurring') {
            if (!$schedule['at'] || $schedule['days_of_week'] === []) throw new InvalidArgumentException('Campanha recorrente exige data inicial, horario e pelo menos um dia da semana.');
            try { new \DateTimeZone($schedule['timezone']); } catch (\Throwable $e) { throw new InvalidArgumentException('Fuso horario da campanha invalido.'); }
            $schedule['next_at'] = Campaign_schedule::occurrence($schedule, strtotime($schedule['at']));
            if (!$schedule['next_at']) throw new InvalidArgumentException('Nenhum dia de disparo dentro do periodo informado.');
        }
        $mediaId = !empty($input['media_id']) ? (int) $input['media_id'] : null;
        if ($mediaId) {
            if ($campaignType === 'official') throw new InvalidArgumentException('Use os parametros do template para midia em campanhas oficiais.');
            (new Media_service())->validateCampaignMedia($mediaId, $instanceId);
        }
        $scheduledType = in_array($schedule['type'], ['scheduled','recurring'], true);
        $status = $before['status'] ?? ($scheduledType ? 'scheduled' : 'draft');
        if ($before && $before['status'] === 'draft' && $scheduledType) $status = 'scheduled';
        if ($schedule['start_immediately']) $status = 'running';
        $audience = [
            'source' => (string) ($input['audience_source'] ?? 'contacts'),
            'include_tags' => $this->normalizeTags($input['include_tags'] ?? []),
            'exclude_tags' => $this->normalizeTags($input['exclude_tags'] ?? []),
            'student_status' => strtolower(trim((string) ($input['student_status'] ?? 'active'))) === 'all' ? 'all' : 'active',
            'manual_numbers_count' => count(is_array($input['manual_numbers'] ?? null) ? $input['manual_numbers'] : (is_array($input['numbers'] ?? null) ? $input['numbers'] : [])),
            'recipient_count' => $preview['count'],
            'excluded_opt_out' => $preview['excluded_opt_out'],
        ];
        $payload = [
            'instance_id' => $instanceId, 'external_id' => $before['external_id'] ?? null,
            'name' => $name, 'description' => mb_substr(trim((string) ($input['description'] ?? '')), 0, 5000) ?: null,
            'status' => $status, 'audience_json' => json_encode($audience, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'message_content' => $message, 'media_id' => $mediaId,
            'schedule_json' => json_encode($schedule, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'metrics_json' => json_encode(['audience'=>$preview['count'],'sent'=>0,'delivered'=>0,'read'=>0,'replied'=>0,'failed'=>0,'pending'=>$preview['count']], JSON_UNESCAPED_UNICODE),
            'correlation_id' => $correlationId, 'idempotency_key' => $idempotencyKey, 'last_error' => null,
            'created_by' => $before['created_by'] ?? $actorId,
            'campaign_type' => $campaignType, 'template_id' => $templateId,
            'template_parameters_json' => json_encode($templateParameters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'dispatch_mode' => $dispatchMode,
            'rate_limit_per_minute' => min(1000, max(1, (int) ($input['rate_limit_per_minute'] ?? $this->settings->get_value('campaign_default_rate_limit_per_minute', 20)))),
            'started_at' => $status === 'running' ? ($before['started_at'] ?? gmdate('Y-m-d H:i:s')) : null,
            'finished_at' => null,
        ];
        if ($id) $this->campaigns->update_record($id, $payload); else $id = $this->campaigns->create_record($payload);
        $this->storeRecipients($id, $preview['recipients']);

        $this->campaigns->update_record($id, [
            'external_id' => 'local-' . $id,
            'dispatch_mode' => 'internal_queue',
            'last_sync_at' => gmdate('Y-m-d H:i:s'),
        ]);
        if ($status === 'running') {
            (new Campaign_dispatch_service())->scheduleDue();
        }
        $saved = $this->get($id) ?: [];
        $this->audit->record(
            $actorId,
            $before ? 'campaign.updated' : 'campaign.created',
            'campaign',
            $id,
            $instanceId,
            $before ?: [],
            $saved,
            $correlationId
        );
        return $saved;
    }

    public function duplicate(int $id, int $actorId): array
    {
        $row = $this->campaigns->get_by_id($id);
        if (!$row) throw new RuntimeException('Campanha nao encontrada.', 404);
        $newId = $this->campaigns->create_record([
            'instance_id' => $row['instance_id'], 'name' => 'Copia de ' . $row['name'], 'description' => $row['description'], 'status' => 'draft',
            'audience_json' => $row['audience_json'], 'message_content' => $row['message_content'], 'media_id' => $row['media_id'], 'schedule_json' => $row['schedule_json'],
            'metrics_json' => json_encode(['audience'=>0,'sent'=>0,'delivered'=>0,'read'=>0,'replied'=>0,'failed'=>0,'pending'=>0], JSON_UNESCAPED_UNICODE),
            'correlation_id' => $this->uuid(), 'idempotency_key' => hash('sha256', random_bytes(32)), 'created_by' => $actorId,
            'campaign_type' => $row['campaign_type'] ?? 'unofficial', 'template_id' => $row['template_id'] ?? null,
            'template_parameters_json' => $row['template_parameters_json'] ?? null, 'dispatch_mode' => $row['dispatch_mode'] ?? 'internal_queue',
            'rate_limit_per_minute' => $row['rate_limit_per_minute'] ?? 20, 'started_at' => null, 'finished_at' => null,
        ]);
        $recipients = $this->db->table('chat_campaign_recipients')->where('campaign_id', $id)->where('deleted', 0)->get()->getResultArray();
        $this->storeRecipients($newId, array_map(static fn (array $recipient): array => [
            'contact_id' => !empty($recipient['contact_id']) ? (int) $recipient['contact_id'] : null,
            'phone' => (string) $recipient['phone_normalized'],
            'variables' => json_decode((string) ($recipient['variables_json'] ?? ''), true) ?: [],
        ], $recipients));
        $this->audit->record($actorId, 'campaign.duplicated', 'campaign', $newId, (int) $row['instance_id'], ['source_id' => $id], ['status' => 'draft']);
        return $this->get($newId) ?: [];
    }

    public function toggle(int $id, int $actorId): array
    {
        $row = $this->campaigns->get_by_id($id);
        if (!$row) throw new RuntimeException('Campanha nao encontrada.', 404);
        if (in_array($row['status'], ['cancelled', 'completed'], true)) throw new RuntimeException('Duplique a campanha encerrada para iniciar outro disparo.', 409);
        $pausing = !in_array((string) $row['status'], ['paused', 'draft', 'failed'], true);
        $schedule = $this->json((string) $row['schedule_json']);
        if (!$pausing && Campaign_schedule::expired($schedule)) throw new RuntimeException('O periodo da campanha terminou.', 409);
        $status = $pausing ? 'paused' : (!empty($schedule['at']) ? 'scheduled' : 'running');
        $payload = [
            'status' => $status,
            'dispatch_mode' => 'internal_queue',
            'external_id' => 'local-' . $id,
            'last_sync_at' => gmdate('Y-m-d H:i:s'),
            'last_error' => null,
        ];
        if (!$pausing && empty($row['started_at'])) {
            $payload['started_at'] = gmdate('Y-m-d H:i:s');
        }
        $this->campaigns->update_record($id, $payload);
        if (!$pausing) {
            (new Campaign_dispatch_service())->scheduleDue();
        }
        $this->audit->record($actorId, $pausing ? 'campaign.paused' : 'campaign.resumed', 'campaign', $id, (int) $row['instance_id'], ['status' => $row['status']], ['status' => $status], (string) $row['correlation_id']);
        if ($pausing) $this->notifyCampaign($id, (string) $row['name'], 'Campanha pausada', 'O disparo foi pausado.', 'warning', 'campaign-paused|' . $id . '|' . gmdate('YmdHi'));
        return $this->get($id) ?: [];
    }

    public function stop(int $id, int $actorId): array
    {
        (new Campaign_dispatch_service())->stop($id);
        $this->audit->record($actorId, 'campaign.stopped', 'campaign', $id);
        return $this->get($id) ?: [];
    }

    public function delete(int $id, int $actorId): void
    {
        (new Campaign_dispatch_service())->stop($id);
        $row = $this->campaigns->get_by_id($id);
        if (!$row) throw new RuntimeException('Campanha nao encontrada.', 404);
        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('chat_campaign_recipients')->where('campaign_id', $id)->update(['deleted'=>1,'updated_at'=>$now]);
        $this->db->table('chat_campaign_run_recipients')->where('campaign_id', $id)->update(['deleted'=>1,'updated_at'=>$now]);
        $this->db->table('chat_campaign_runs')->where('campaign_id', $id)->update(['deleted'=>1,'updated_at'=>$now]);
        $this->campaigns->soft_delete($id);
        $this->audit->record($actorId, 'campaign.deleted', 'campaign', $id, (int) $row['instance_id'], $row);
    }

    public function health(): array
    {
        $pending = $this->db->table('chat_campaign_run_recipients')
            ->where('deleted', 0)
            ->whereIn('status', ['pending', 'retry', 'sending'])
            ->countAllResults();
        $running = $this->db->table('chat_campaign_runs')
            ->where('deleted', 0)
            ->where('status', 'running')
            ->countAllResults();
        $failedLastHour = $this->db->table('chat_campaign_run_recipients')
            ->where('deleted', 0)
            ->where('status', 'failed')
            ->where('updated_at >=', gmdate('Y-m-d H:i:s', time() - 3600))
            ->countAllResults();

        return [
            'success' => true,
            'provider' => 'internal_queue',
            'pending_recipients' => $pending,
            'running_occurrences' => $running,
            'failed_last_hour' => $failedLastHour,
            'checked_at' => gmdate(DATE_ATOM),
        ];
    }

    /** @return array{data:array<int,array<string,mixed>>,meta:array<string,mixed>} */
    public function runs(int $campaignId, int $page = 1, int $limit = 20): array
    {
        if (!$this->campaigns->get_by_id($campaignId)) {
            throw new InvalidArgumentException('Campanha nao encontrada.', 404);
        }
        $page = max(1, $page);
        $limit = min(100, max(1, $limit));
        $builder = $this->db->table('chat_campaign_runs')
            ->where('campaign_id', $campaignId)
            ->where('deleted', 0);
        $total = (clone $builder)->countAllResults();
        $rows = $builder->orderBy('id', 'DESC')
            ->limit($limit, ($page - 1) * $limit)
            ->get()->getResultArray();

        return [
            'data' => array_map(function (array $row): array {
                $metrics = $this->json((string) ($row['metrics_json'] ?? ''));
                return [
                    'id' => (int) $row['id'],
                    'campaign_id' => (int) $row['campaign_id'],
                    'occurrence_key' => $row['occurrence_key'] ?: null,
                    'status' => (string) ($row['status'] ?? 'pending'),
                    'scheduled_at' => $row['scheduled_at'] ?: null,
                    'started_at' => $row['started_at'] ?: null,
                    'finished_at' => $row['finished_at'] ?: null,
                    'recipient_count' => (int) ($row['recipient_count'] ?? 0),
                    'metrics' => $metrics,
                    'error_message' => $row['error_message'] ?: null,
                ];
            }, $rows),
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'has_more' => $page * $limit < $total,
            ],
        ];
    }

    /** @return array{data:array<int,array<string,mixed>>,meta:array<string,mixed>} */
    public function run_recipients(int $campaignId, int $runId, array $filters = [], int $page = 1, int $limit = 50): array
    {
        $run = $this->db->table('chat_campaign_runs')
            ->where('id', $runId)
            ->where('campaign_id', $campaignId)
            ->where('deleted', 0)
            ->get(1)->getRowArray();
        if (!$run) {
            throw new InvalidArgumentException('Execucao de campanha nao encontrada.', 404);
        }

        $page = max(1, $page);
        $limit = min(200, max(1, $limit));
        $recipients = $this->db->prefixTable('chat_campaign_run_recipients');
        $contacts = $this->db->prefixTable('chat_contacts');
        $builder = $this->db->table($recipients)
            ->select($recipients . '.*, ' . $contacts . '.name AS contact_name')
            ->join($contacts, $contacts . '.id=' . $recipients . '.contact_id AND ' . $contacts . '.deleted=0', 'left')
            ->where($recipients . '.campaign_id', $campaignId)
            ->where($recipients . '.run_id', $runId)
            ->where($recipients . '.deleted', 0);
        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        if ($status !== '' && $status !== 'all') {
            $builder->where($recipients . '.status', $status);
        }
        $search = preg_replace('/\D+/', '', (string) ($filters['search'] ?? '')) ?: '';
        if ($search !== '') {
            $builder->like($recipients . '.phone_normalized', $search, 'both');
        }
        $total = (clone $builder)->countAllResults();
        $rows = $builder->orderBy($recipients . '.id', 'ASC')
            ->limit($limit, ($page - 1) * $limit)
            ->get()->getResultArray();

        return [
            'data' => array_map(function (array $row): array {
                return [
                    'id' => (int) $row['id'],
                    'run_id' => (int) $row['run_id'],
                    'contact_id' => !empty($row['contact_id']) ? (int) $row['contact_id'] : null,
                    'contact_name' => trim((string) ($row['contact_name'] ?? '')) ?: null,
                    'phone' => (string) ($row['phone_normalized'] ?? ''),
                    'status' => (string) ($row['status'] ?? 'pending'),
                    'attempts' => (int) ($row['attempts'] ?? 0),
                    'max_attempts' => (int) ($row['max_attempts'] ?? 0),
                    'external_message_id' => $row['external_message_id'] ?: null,
                    'error_message' => $row['error_message'] ?: null,
                    'queued_at' => $row['queued_at'] ?: null,
                    'last_attempt_at' => $row['last_attempt_at'] ?: null,
                    'sent_at' => $row['sent_at'] ?: null,
                    'delivered_at' => $row['delivered_at'] ?: null,
                    'read_at' => $row['read_at'] ?: null,
                    'replied_at' => $row['replied_at'] ?: null,
                ];
            }, $rows),
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'has_more' => $page * $limit < $total,
                'run' => [
                    'id' => (int) $run['id'],
                    'status' => (string) ($run['status'] ?? ''),
                    'recipient_count' => (int) ($run['recipient_count'] ?? 0),
                ],
            ],
        ];
    }

    public function list_templates(): array
    {
        $result = $this->templates->paginate_records(['active' => 1], 1, 200);
        return array_map([$this, 'mapTemplate'], $result['data']);
    }

    public function save_template(array $input, int $actorId, ?int $id = null): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $message = trim((string) ($input['message'] ?? $input['message_content'] ?? ''));
        if ($name === '' || mb_strlen($name) > 191 || $message === '' || mb_strlen($message) > 10000) {
            throw new InvalidArgumentException('Nome e mensagem do template sao obrigatorios.');
        }
        $this->validateTemplateVariables($message);
        if ($id && !$this->templates->get_by_id($id)) {
            throw new RuntimeException('Template nao encontrado.', 404);
        }
        $payload = ['name' => $name, 'message_content' => $message, 'media_id' => !empty($input['media_id']) ? (int) $input['media_id'] : null, 'active' => filter_var($input['active'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 1 : 0, 'created_by' => $actorId];
        if ($id) $this->templates->update_record($id, $payload); else $id = $this->templates->create_record($payload);
        return $this->mapTemplate($this->templates->get_by_id($id) ?: []);
    }

    public function delete_template(int $id): void
    {
        if (!$this->templates->get_by_id($id)) throw new RuntimeException('Template nao encontrado.', 404);
        $this->templates->soft_delete($id);
    }

    /** @return array{count:int,invalid:int,duplicates:int,entries:array<int,array<string,mixed>>} */
    public function import_audience(string $path, ?string $extension = null): array
    {
        [$header, $grid] = $this->readAudienceFile($path, $extension);
        $headerIndexes = [];
        foreach ($header as $index => $label) $headerIndexes[$this->normalizeAudienceHeader((string) $label)] = (int) $index;

        $phoneIndex = $this->audienceColumn($headerIndexes, ['telefone', 'celular', 'whatsapp', 'numero', 'phone', 'fone']);
        $hasHeader = $phoneIndex !== null || $this->audienceColumn($headerIndexes, ['nome', 'name', 'nome_responsavel', 'responsavel']) !== null;
        if (!$hasHeader) {
            $rows = array_merge([$header], $grid);
            $phoneIndex = $this->detectPhoneColumn($rows);
            $headerIndexes = [];
        } else {
            if ($phoneIndex === null) throw new InvalidArgumentException('A planilha precisa ter uma coluna Telefone.');
            $rows = $grid;
        }

        $columns = [
            'nome' => $this->audienceColumn($headerIndexes, ['nome', 'name', 'nome_responsavel', 'responsavel']),
            'nome_aluno' => $this->audienceColumn($headerIndexes, ['nome_aluno', 'aluno', 'student', 'nome_do_aluno']),
            'matricula' => $this->audienceColumn($headerIndexes, ['matricula', 'registro', 'registration']),
            'turma' => $this->audienceColumn($headerIndexes, ['turma', 'classe', 'class']),
            'unidade' => $this->audienceColumn($headerIndexes, ['unidade', 'unidade_nome', 'filial']),
            'var1' => $this->audienceColumn($headerIndexes, ['var1', 'variavel1', 'variavel_1']),
            'var2' => $this->audienceColumn($headerIndexes, ['var2', 'variavel2', 'variavel_2']),
        ];

        $entries = [];
        $seen = [];
        $invalid = 0;
        $duplicates = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $rawPhone = trim((string) ($row[$phoneIndex] ?? ''));
            $phone = preg_replace('/\D+/', '', $rawPhone) ?: '';
            if (strlen($phone) < 10 || strlen($phone) > 15) {
                $invalid++;
                continue;
            }
            if (isset($seen[$phone])) {
                $duplicates++;
                continue;
            }
            $seen[$phone] = true;
            $variables = [];
            foreach ($columns as $key => $index) {
                if ($index === null) continue;
                $value = mb_substr(trim((string) ($row[$index] ?? '')), 0, 500);
                if ($value !== '') $variables[$key] = $value;
            }
            if (isset($variables['nome'])) {
                $variables['responsavel'] = $variables['nome'];
                $variables['nome_responsavel'] = $variables['nome'];
            }
            if (isset($variables['nome_aluno'])) {
                $variables['aluno'] = $variables['nome_aluno'];
                $variables['alunos'] = $variables['nome_aluno'];
            }
            $entries[] = ['numero' => $phone, 'variaveis' => $variables];
        }

        return ['count' => count($entries), 'invalid' => $invalid, 'duplicates' => $duplicates, 'entries' => $entries];
    }

    /** @return array{body:string,content_type:string,filename:string} */
    public function audience_template(): array
    {
        $headers = ['telefone', 'nome_responsavel', 'nome_aluno', 'matricula', 'turma', 'unidade', 'var1', 'var2'];
        if (!is_file($this->spreadsheetAutoloadPath())) {
            $handle = fopen('php://temp', 'r+');
            if ($handle === false) throw new RuntimeException('Nao foi possivel montar o modelo de destinatarios.', 503);
            fputcsv($handle, $headers, ';');
            fputcsv($handle, ['5511999999999', 'Maria', 'Joao', '123', 'Infantil', 'Unidade Centro', 'teste 1', 'teste 2'], ';');
            rewind($handle);
            $contents = (string) stream_get_contents($handle);
            fclose($handle);
            return [
                'body' => "\xEF\xBB\xBF" . $contents,
                'content_type' => 'text/csv; charset=UTF-8',
                'filename' => 'modelo-destinatarios-whatsapp.csv',
            ];
        }
        $this->loadSpreadsheetLibrary();
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Destinatarios');
        foreach ($headers as $column => $header) $sheet->setCellValueByColumnAndRow($column + 1, 1, $header);
        $sheet->setCellValueByColumnAndRow(1, 2, '5511999999999');
        $sheet->setCellValueByColumnAndRow(2, 2, 'Maria');
        $sheet->setCellValueByColumnAndRow(3, 2, 'Joao');
        $sheet->setCellValueByColumnAndRow(4, 2, '123');
        $sheet->setCellValueByColumnAndRow(5, 2, 'Infantil');
        $sheet->setCellValueByColumnAndRow(6, 2, 'Unidade Centro');
        $sheet->setCellValueByColumnAndRow(7, 2, 'teste 1');
        $sheet->setCellValueByColumnAndRow(8, 2, 'teste 2');
        $sheet->freezePane('A2');
        foreach (range(1, count($headers)) as $column) $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $sheet->getStyle('A1:H1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('DCEBFA');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $contents = (string) ob_get_clean();
        $spreadsheet->disconnectWorksheets();
        return [
            'body' => $contents,
            'content_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'filename' => 'modelo-destinatarios-whatsapp.xlsx',
        ];
    }

    /** @return array{0:array<int,string>,1:array<int,array<int,mixed>>} */
    private function readAudienceFile(string $path, ?string $extension = null): array
    {
        if (!is_file($path)) throw new InvalidArgumentException('Nao foi possivel ler a planilha.');
        $extension = strtolower(trim((string) ($extension ?: pathinfo($path, PATHINFO_EXTENSION))));
        if ($extension === 'csv') return $this->readAudienceCsv($path);
        $this->loadSpreadsheetLibrary();
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $grid = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            $spreadsheet->disconnectWorksheets();
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException('Nao foi possivel ler a planilha. Salve o arquivo como XLSX ou CSV e tente novamente.');
        }
        $grid = array_values(array_filter($grid, static fn ($row): bool => is_array($row) && array_filter($row, static fn ($cell): bool => trim((string) $cell) !== '')));
        if (!$grid) throw new InvalidArgumentException('A planilha esta vazia.');
        $header = array_map(static fn ($cell): string => trim((string) $cell), array_shift($grid));
        return [$header, array_values($grid)];
    }

    /** @return array{0:array<int,string>,1:array<int,array<int,mixed>>} */
    private function readAudienceCsv(string $path): array
    {
        $handle = @fopen($path, 'r');
        if ($handle === false) throw new InvalidArgumentException('Nao foi possivel ler a planilha.');
        $delimiter = $this->audienceDelimiter($path);
        $grid = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $row = array_map(static fn ($cell): string => trim((string) $cell), $row);
            if (array_filter($row, static fn (string $cell): bool => $cell !== '')) $grid[] = $row;
        }
        fclose($handle);
        if (!$grid) throw new InvalidArgumentException('A planilha esta vazia.');
        $grid[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($grid[0][0] ?? '')) ?: '';
        $header = array_map(static fn ($cell): string => trim((string) $cell), array_shift($grid));
        return [$header, array_values($grid)];
    }

    private function spreadsheetAutoloadPath(): string
    {
        return rtrim(APPPATH, '/\\') . '/ThirdParty/PHPOffice-PhpSpreadsheet/vendor/autoload.php';
    }

    private function loadSpreadsheetLibrary(): void
    {
        $autoload = $this->spreadsheetAutoloadPath();
        if (!is_file($autoload)) throw new RuntimeException('Leitor de arquivos Excel indisponivel nesta instalacao. Baixe o modelo e envie o CSV compativel com Excel.', 503);
        require_once $autoload;
    }

    private function audienceDelimiter(string $path): string
    {
        $handle = @fopen($path, 'r');
        if (!$handle) return ',';
        $line = (string) fgets($handle);
        fclose($handle);
        return substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
    }

    private function normalizeAudienceHeader(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o','ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c']);
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', $value), '_');
    }

    private function audienceColumn(array $headers, array $aliases): ?int
    {
        foreach ($aliases as $alias) {
            $key = $this->normalizeAudienceHeader((string) $alias);
            if (array_key_exists($key, $headers)) return (int) $headers[$key];
        }
        return null;
    }

    private function detectPhoneColumn(array $rows): ?int
    {
        $scores = [];
        foreach (array_slice($rows, 0, 20) as $row) {
            foreach ((array) $row as $index => $value) {
                $digits = preg_replace('/\D+/', '', (string) $value) ?: '';
                if (strlen($digits) >= 10 && strlen($digits) <= 15) $scores[$index] = ($scores[$index] ?? 0) + 1;
            }
        }
        if (!$scores) throw new InvalidArgumentException('A planilha precisa ter uma coluna Telefone.');
        arsort($scores);
        return (int) array_key_first($scores);
    }

    /** @return array<int,array<string,mixed>> */
    private function studentAudienceRows(array $input): array
    {
        $students = $this->db->prefixTable('grupo_donato_alunos');
        $responsibles = $this->db->prefixTable('grupo_donato_responsaveis');
        $units = $this->db->prefixTable('grupo_donato_unidades');
        if (!$this->db->tableExists($students) || !$this->db->tableExists($responsibles)) return [];
        $builder = $this->db->table($students)
            ->select($responsibles . '.id AS responsavel_id, ' . $responsibles . '.nome AS responsavel_nome, COALESCE(NULLIF(' . $responsibles . '.whats, \'\'), NULLIF(' . $responsibles . '.celular, \'\'), \'\') AS responsavel_phone, ' . $students . '.nome_aluno, ' . $students . '.matricula, ' . $students . '.turma', false)
            ->where($students . '.deleted', 0)
            ->where($responsibles . '.deleted', 0)
            ->join($responsibles, $responsibles . '.id=' . $students . '.responsavel_id', 'inner');
        if ($this->db->tableExists($units)) {
            $builder->select($units . '.nome_unidade AS unidade_nome, ' . $units . '.cidade AS unidade_cidade', false)
                ->join($units, $units . '.id=' . $students . '.unidade_id', 'left');
        }
        if (strtolower(trim((string) ($input['student_status'] ?? 'active'))) !== 'all') $builder->where($students . '.status', 'Ativo');
        $unitId = (int) ($input['unit_id'] ?? 0);
        if ($unitId > 0) $builder->where($students . '.unidade_id', $unitId);
        return $builder->orderBy($students . '.nome_aluno', 'ASC')->limit(10000)->get()->getResultArray();
    }

    private function storeRecipients(int $campaignId, array $recipients): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('chat_campaign_recipients')->where('campaign_id', $campaignId)->where('deleted', 0)->update(['deleted'=>1,'updated_at'=>$now]);
        $maxAttempts = min(20, max(1, (int) $this->settings->get_value('campaign_recipient_max_attempts', 5)));
        foreach ($recipients as $recipient) {
            $phone = (string) $recipient['phone'];
            $variables = is_array($recipient['variables'] ?? null) ? $recipient['variables'] : [];
            foreach (['name','company','city'] as $key) if (isset($recipient[$key]) && is_scalar($recipient[$key])) $variables[$key] = trim((string) $recipient[$key]);
            $existing = $this->db->table('chat_campaign_recipients')->where('campaign_id', $campaignId)->where('phone_hash', hash('sha256', $phone))->get(1)->getRowArray();
            $payload = [
                'campaign_id'=>$campaignId, 'run_id'=>null, 'contact_id'=>$recipient['contact_id'] ?? null, 'phone_hash'=>hash('sha256', $phone), 'phone_normalized'=>$phone,
                'variables_json'=>json_encode(array_slice($variables, 0, 100, true), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status'=>'pending','external_message_id'=>null,'error_message'=>null,'sent_at'=>null,'delivered_at'=>null,'read_at'=>null,'replied_at'=>null,
                'attempts'=>0,'max_attempts'=>$maxAttempts,'available_at'=>null,'last_attempt_at'=>null,'updated_at'=>$now,'deleted'=>0,
            ];
            if ($existing) $this->db->table('chat_campaign_recipients')->where('id', (int) $existing['id'])->update($payload);
            else { $payload['created_at']=$now; $this->db->table('chat_campaign_recipients')->insert($payload); }
        }
    }

    /** @return array<int,string> */
    private function notifyCampaign(int $id, string $name, string $title, string $message, string $level, string $dedupe): void
    {
        try {
            (new Notification_service())->create('campaign', $title, $name . ': ' . mb_substr($message, 0, 1500), 'campaign', $id, null, $level, $dedupe);
        } catch (\Throwable $exception) {
            // Notification failures cannot change campaign provider semantics.
        }
    }

    private function map(array $row): array
    {
        $audience = $this->json((string) ($row['audience_json'] ?? ''));
        $schedule = $this->json((string) ($row['schedule_json'] ?? ''));
        $metrics = $this->json((string) ($row['metrics_json'] ?? ''));
        $templateParameters = $this->json((string) ($row['template_parameters_json'] ?? ''));
        $localAt = !empty($schedule['at']) ? (new \DateTimeImmutable($schedule['at']))->setTimezone(new \DateTimeZone($schedule['timezone'] ?? 'America/Sao_Paulo')) : null;
        return [
            'id'=>(int)$row['id'], 'external_id'=>$row['external_id'] ?: null, 'instance_id'=>(int)$row['instance_id'], 'name'=>(string)$row['name'],
            'description'=>(string)($row['description']??''), 'status'=>$this->normalizeStatus((string)($row['status']??'draft')), 'message'=>(string)$row['message_content'],
            'media_id'=>isset($row['media_id'])?(int)$row['media_id']:null, 'audience'=>$audience, 'schedule'=>$schedule, 'metrics'=>$metrics,
            'campaign_type'=>(string)($row['campaign_type']??'unofficial'), 'dispatch_mode'=>(string)($row['dispatch_mode']??'internal_queue'),
            'template_id'=>!empty($row['template_id'])?(int)$row['template_id']:null, 'template_parameters'=>$templateParameters,
            'rate_limit_per_minute'=>(int)($row['rate_limit_per_minute']??20),
            'audience_count'=>(int)($audience['recipient_count']??$metrics['audience']??0), 'last_error'=>$row['last_error']?:null,
            'audience_source'=>(string)($audience['source']??'contacts'), 'include_tags'=>is_array($audience['include_tags']??null)?$audience['include_tags']:[],
            'exclude_tags'=>is_array($audience['exclude_tags']??null)?$audience['exclude_tags']:[], 'student_status'=>(string)($audience['student_status']??'active'), 'numbers'=>[],
            'type'=>(($schedule['type']??'')==='recurring'?'recurring':'one_time'), 'start_date'=>$localAt ? $localAt->format('Y-m-d') : '',
            'start_time'=>$localAt ? $localAt->format('H:i:s') : '',
            'ends_at'=>$schedule['ends_at']??null, 'interval_seconds'=>(int)($schedule['interval_seconds']??0),
            'timezone'=>(string)($schedule['timezone']??'America/Sao_Paulo'), 'weekdays'=>is_array($schedule['days_of_week']??null)?$schedule['days_of_week']:[],
            'next_at'=>$schedule['next_at']??null,
            'scheduled'=>!empty($schedule['next_at']??$schedule['at']??null)?date('d/m/Y H:i',strtotime((string)($schedule['next_at']??$schedule['at']))):'Sem agendamento',
            'sent'=>(int)($metrics['sent']??0), 'delivered'=>(int)($metrics['delivered']??0), 'read'=>(int)($metrics['read']??0), 'replied'=>(int)($metrics['replied']??0),
            'failed'=>(int)($metrics['failed']??0), 'pending'=>(int)($metrics['pending']??0),
            'started_at'=>$row['started_at']??null, 'finished_at'=>$row['finished_at']??null,
            'last_sync_at'=>$row['last_sync_at']??null, 'created_at'=>$row['created_at']??null, 'updated_at'=>$row['updated_at']??null,
        ];
    }

    private function mapTemplate(array $row): array
    {
        return [
            'id'=>(int)($row['id']??0), 'instance_id'=>!empty($row['instance_id'])?(int)$row['instance_id']:null,
            'name'=>(string)($row['name']??''), 'message'=>(string)($row['message_content']??''),
            'media_id'=>isset($row['media_id'])?(int)$row['media_id']:null, 'active'=>!empty($row['active']),
            'provider_template_id'=>$row['provider_template_id']??null, 'language_code'=>(string)($row['language_code']??''),
            'category'=>(string)($row['category']??''), 'provider_status'=>(string)($row['provider_status']??''),
            'components'=>$this->json((string)($row['components_json']??'')), 'last_synced_at'=>$row['last_synced_at']??null,
        ];
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        $map = ['active' => 'running', 'started' => 'running', 'stopped' => 'paused', 'done' => 'completed', 'error' => 'failed', 'inactive' => 'paused'];
        $status = $map[$status] ?? $status;
        return in_array($status, ['draft', 'scheduled', 'running', 'paused', 'completed', 'failed', 'cancelled'], true) ? $status : 'draft';
    }

    private function scheduleType(string $value): string
    {
        $value = strtolower(trim($value));
        return in_array($value, ['draft', 'scheduled', 'recurring'], true) ? $value : 'draft';
    }

    private function normalizeWeekdays(array $values): array
    {
        $map = ['sun'=>0,'mon'=>1,'tue'=>2,'wed'=>3,'thu'=>4,'fri'=>5,'sat'=>6,'dom'=>0,'seg'=>1,'ter'=>2,'qua'=>3,'qui'=>4,'sex'=>5,'sab'=>6];
        $result = [];
        foreach ($values as $value) {
            $key = strtolower(trim((string) $value));
            $day = array_key_exists($key, $map) ? $map[$key] : (is_numeric($value) ? (int) $value : -1);
            if ($day >= 0 && $day <= 6) $result[$day] = $day;
        }
        sort($result);
        return array_values($result);
    }

    private function dateValue($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        $time = strtotime($value);
        if ($time === false) throw new InvalidArgumentException('Data de agendamento invalida.');
        return date(DATE_ATOM, $time);
    }

    private function normalizeTags($tags): array
    {
        if (!is_array($tags)) return [];
        $result = [];
        foreach ($tags as $tag) { $tag = mb_strtolower(trim((string) $tag)); if ($tag !== '' && mb_strlen($tag) <= 100) $result[$tag] = $tag; }
        return array_values(array_slice($result, 0, 50));
    }

    /** @return array<int,array<string,mixed>> */
    private function validateOfficialTemplateComponents(array $components, array $templateDefinition): array
    {
        if (array_keys($components) !== range(0, count($components) - 1) && $components !== []) {
            throw new InvalidArgumentException('Os componentes do template oficial precisam ser uma lista JSON.');
        }
        $clean = [];
        foreach ($components as $component) {
            if (!is_array($component)) throw new InvalidArgumentException('Componente de template oficial invalido.');
            $type = strtolower(trim((string) ($component['type'] ?? '')));
            if (!in_array($type, ['header','body','button'], true)) throw new InvalidArgumentException('Tipo de componente oficial nao suportado: ' . ($type ?: 'vazio') . '.');
            $parameters = $component['parameters'] ?? [];
            if (!is_array($parameters) || count($parameters) > 100) throw new InvalidArgumentException('Parametros de componente oficial invalidos.');
            $cleanParameters = [];
            foreach ($parameters as $parameter) {
                if (!is_array($parameter)) throw new InvalidArgumentException('Parametro de template oficial invalido.');
                $parameterType = strtolower(trim((string) ($parameter['type'] ?? '')));
                if (!in_array($parameterType, ['text','currency','date_time','image','video','document','payload'], true)) {
                    throw new InvalidArgumentException('Tipo de parametro oficial nao suportado: ' . ($parameterType ?: 'vazio') . '.');
                }
                $parameter['type'] = $parameterType;
                $cleanParameters[] = $parameter;
            }
            $entry = ['type' => $type, 'parameters' => $cleanParameters];
            if ($type === 'button') {
                $subType = strtolower(trim((string) ($component['sub_type'] ?? '')));
                $index = trim((string) ($component['index'] ?? ''));
                if (!in_array($subType, ['url','quick_reply'], true) || !preg_match('/^\d{1,2}$/', $index)) {
                    throw new InvalidArgumentException('Botao de template oficial exige sub_type e index validos.');
                }
                $entry['sub_type'] = $subType;
                $entry['index'] = $index;
            }
            $clean[] = $entry;
        }

        // Validate the generated BODY/HEADER parameter count against approved
        // template markers. This catches incomplete campaigns before queueing.
        foreach ($templateDefinition as $definition) {
            if (!is_array($definition)) continue;
            $type = strtolower((string) ($definition['type'] ?? ''));
            if (!in_array($type, ['header','body'], true)) continue;
            preg_match_all('/\{\{\s*\d+\s*\}\}/', (string) ($definition['text'] ?? ''), $matches);
            $required = count($matches[0] ?? []);
            if ($required < 1) continue;
            $provided = null;
            foreach ($clean as $component) if ($component['type'] === $type) { $provided = count($component['parameters']); break; }
            if ($provided !== $required) throw new InvalidArgumentException("O componente {$type} exige {$required} parametro(s); foram informados " . (int) $provided . '.');
        }
        return $clean;
    }

    private function validateTemplateVariables(string $message): void
    {
        preg_match_all('/\{([^{}]+)\}/u', $message, $matches);
        $unknown = array_filter(array_unique($matches[1] ?? []), static fn (string $key): bool => !preg_match('/^[A-Za-z0-9_.-]{1,100}$/', $key));
        if ($unknown) throw new InvalidArgumentException('Variaveis de template desconhecidas: ' . implode(', ', $unknown) . '.');
    }

    private function json(string $value): array
    {
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function uuid(): string
    {
        return bin2hex(random_bytes(8)) . '-' . bin2hex(random_bytes(8));
    }
}
