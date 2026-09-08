<?php
declare(strict_types=1);
if (getenv('RISE_DB_NAME') !== 'campaign_test') { fwrite(STDERR, "Use only the disposable campaign_test database.\n"); exit(1); }
$root = dirname(__DIR__, 3);
define('FCPATH', $root . '/');
$_SERVER = array_replace($_SERVER, ['CI_ENVIRONMENT'=>'development','HTTP_HOST'=>'localhost','SCRIPT_NAME'=>'/index.php','REQUEST_URI'=>'/','REQUEST_METHOD'=>'GET','SERVER_PORT'=>'80']);
define('ENVIRONMENT', 'development'); define('CI_DEBUG', true); chdir($root);
require FCPATH . 'app/Config/Paths.php'; $paths = new Config\Paths(); require $paths->systemDirectory . '/Boot.php';
CodeIgniter\Boot::bootConsole($paths); Config\Services::autoloader()->addNamespace('Chatwoot_plugin', dirname(__DIR__));
(new Chatwoot_plugin\Libraries\Migration_runner())->migrate();
use Chatwoot_plugin\Services\Campaign_service;
use Chatwoot_plugin\Services\Campaign_dispatch_service;
use Chatwoot_plugin\Services\Chat_service;
class CampaignFakeChat extends Chat_service {
    public array $calls = []; public bool $fail = false;
    public function __construct() {}
    public function send_text(int $conversationId, string $text, string $clientMessageId, int $actorId = 0, ?int $replyToMessageId = null): array {
        if ($this->fail) throw new RuntimeException('Simulated temporary failure');
        $this->calls[] = $text;
        return ['id'=>count($this->calls), 'status'=>'sent', 'external_message_id'=>$clientMessageId];
    }
    public function send_template(int $conversationId, string $templateName, string $languageCode, array $components, string $clientMessageId, int $actorId = 0): array {
        return $this->send_text($conversationId, 'template:' . $templateName, $clientMessageId);
    }
}
class CampaignFakeEvolution extends Chatwoot_plugin\Libraries\Evolution_client {
    public int $calls = 0; public array $captions = [];
    public function __construct() {}
    public function send_media(string $number, string $media, string $mimeType, string $mediaType, string $fileName = '', string $caption = '', $instance = null, array $options = []): array {
        $this->calls++; $this->captions[] = $caption;
        return ['success'=>true,'message_id'=>'CAMPAIGN-MEDIA-' . $this->calls,'status_code'=>200,'data'=>['key'=>['id'=>'CAMPAIGN-MEDIA-' . $this->calls,'remoteJid'=>$number . '@s.whatsapp.net','fromMe'=>true]]];
    }
}
$db = db_connect('default'); $checks = 0;
$check = static function (bool $ok, string $name) use (&$checks): void { if (!$ok) throw new RuntimeException($name); $checks++; echo "[OK] $name\n"; };
$instances = new Chatwoot_plugin\Models\Chat_instances_model();
$instanceId = $instances->upsert_instance('campaign-test-' . uniqid(), ['evolution_instance_name'=>'campaign-test-instance-' . uniqid(),'name'=>'Campaign test','base_url'=>'https://evolution.invalid','provider_type'=>'evolution','connection_status'=>'connected','active'=>1]);
$fake = new CampaignFakeChat(); $client = new CampaignFakeEvolution();
$providers = new Chatwoot_plugin\Services\Provider_manager(evolutionFactory: static fn() => $client);
$media = new Chatwoot_plugin\Services\Media_service(providers: $providers);
$dispatch = new Campaign_dispatch_service(chat: $fake, media: $media);
$service = new Campaign_service();
$input = ['name'=>'Automatic test','instance_id'=>$instanceId,'message'=>'Olá {nome}, pedido {pedido}', 'audience_source'=>'manual','numbers'=>[['numero'=>'+55 (11) 98888-0001','variaveis'=>['nome'=>'Ana','pedido'=>'123']], ['numero'=>'5511988880001','variaveis'=>['nome'=>'Ana','pedido'=>'123']], ['numero'=>'5511988880002','variaveis'=>['nome'=>'Bia','pedido'=>'456']]], 'schedule_type'=>'recurring','schedule_at'=>date(DATE_ATOM, time()-30),'weekdays'=>[0,1,2,3,4,5,6],'timezone'=>'America/Sao_Paulo','ends_at'=>date(DATE_ATOM,time()+86400*3),'interval_seconds'=>15,'idempotency_key'=>uniqid('campaign-')];
$c = $service->save($input, 1); $id = $c['id'];
$check($c['audience_count'] === 2, 'duplicate phones collapse');
$check($service->save($input, 1)['id'] === $id, 'create retry returns same campaign');
try { $service->save(array_replace($input, ['message'=>'Changed']), 1); throw new LogicException('accepted duplicate token'); } catch (RuntimeException $e) { $check($e->getCode() === 409, 'same token with different payload rejected'); }
$check($c['numbers'][0]['variaveis']['pedido'] === '123', 'edit preserves custom variables');
$dispatch->scheduleDue(); $dispatch->scheduleDue();
$runs = $db->table('chat_campaign_runs')->where('campaign_id',$id)->get()->getResultArray();
$check(count($runs) === 1, 'scheduler retry creates only one occurrence');
$recipients = $db->table('chat_campaign_run_recipients')->where('campaign_id',$id)->orderBy('phone_normalized','ASC')->get()->getResultArray();
$a = $dispatch->dispatchRecipient($id,(int)$recipients[0]['id']);
$check($a['status'] === 'sent' && $fake->calls === ['Olá Ana, pedido 123'], 'personalized text dispatched');
$check(!empty($dispatch->dispatchRecipient($id,(int)$recipients[0]['id'])['duplicate']), 'recipient retry does not resend');
$b = $dispatch->dispatchRecipient($id,(int)$recipients[1]['id']);
$check(($b['reason'] ?? '') === 'campaign_interval' && count($fake->calls) === 1, 'minimum spacing blocks burst');
$dispatch->stop($id); $dispatch->stop($id);
$check($service->get($id)['status'] === 'cancelled', 'stop is idempotent');
$check(($dispatch->dispatchRecipient($id,(int)$recipients[1]['id'])['reason'] ?? '') === 'campaign_not_running', 'queued recipient blocked after stop');
// Missed recurring windows advance to a future allowed day, without replay.
$late = $service->save(array_replace($input,['idempotency_key'=>uniqid(),'schedule_at'=>date(DATE_ATOM,time()-86400*2-3600)]),1);
$dispatch->scheduleDue();
$check($db->table('chat_campaign_runs')->where('campaign_id',$late['id'])->countAllResults() === 0, 'missed occurrence is skipped');
$check(strtotime($service->get($late['id'])['next_at']) > time(), 'next occurrence is in the future');
// Original reusable attachment stays intact, real media pipeline uses fake transport.
$path = WRITEPATH . 'uploads/campaign-test.png'; @mkdir(dirname($path),0750,true);
file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zl9sAAAAASUVORK5CYII='));
$mid = (new Chatwoot_plugin\Models\Chat_media_model())->create_record(['instance_id'=>$instanceId,'storage_driver'=>'local','storage_path'=>'campaign-test.png','original_name'=>'campaign.png','mime_type'=>'image/png','media_type'=>'image','file_size'=>filesize($path),'sha256'=>hash_file('sha256',$path),'created_by'=>1]);
$m = $service->save(array_replace($input,['idempotency_key'=>uniqid(),'media_id'=>$mid,'interval_seconds'=>0]),1);
$dispatch->scheduleDue();
$mr = $db->table('chat_campaign_run_recipients')->where('campaign_id',$m['id'])->orderBy('phone_normalized','ASC')->get()->getResultArray();
foreach ($mr as $r) { $result = $dispatch->dispatchRecipient($m['id'],(int)$r['id']); $check(($result['status']??'') === 'sent','media campaign recipient sent: ' . json_encode($result)); }
$check($client->calls === 2 && is_file($path), 'reusable attachment preserved across recipients');
$check($client->captions === ['Olá Ana, pedido 123','Olá Bia, pedido 456'], 'captions personalized');
$conversations = new Chatwoot_plugin\Models\Chat_conversations_model();
$conv = $conversations->get_by_remote_jid($instanceId,'5511988880001@s.whatsapp.net');
$clientId = sprintf('campaign-%d-run-%d-recipient-%d',$m['id'],$mr[0]['run_id'],$mr[0]['id']);
$media->sendCampaignMedia((int)$conv['id'],$mid,'changed on retry',$clientId);
$check($client->calls === 2, 'media pipeline idempotent on retry');
$check($service->get($m['id'])['status'] === 'scheduled', 'recurring campaign schedules next occurrence');
$wrong = $instances->upsert_instance('campaign-wrong-' . uniqid(), ['evolution_instance_name'=>'campaign-other-' . uniqid(),'name'=>'Other','base_url'=>'https://evolution.invalid','provider_type'=>'evolution','connection_status'=>'connected','active'=>1]);
try { $media->validateCampaignMedia($mid,$wrong); throw new LogicException('accepted foreign media'); } catch (InvalidArgumentException $e) { $check(true,'cross-channel attachment rejected'); }
// End date is checked again at send time.
$end = $service->save(array_replace($input,['idempotency_key'=>uniqid()]),1); $dispatch->scheduleDue();
$endSchedule = $end['schedule']; $endSchedule['ends_at'] = date(DATE_ATOM,time()-1);
$db->table('chat_campaigns')->where('id',$end['id'])->update(['schedule_json'=>json_encode($endSchedule)]);
$er = $db->table('chat_campaign_run_recipients')->where('campaign_id',$end['id'])->get(1)->getRowArray();
$check(($dispatch->dispatchRecipient($end['id'],(int)$er['id'])['reason']??'') === 'campaign_expired','end date blocks pending recipient');
$dispatch->scheduleDue(); $check($service->get($end['id'])['status'] === 'cancelled','expired campaign closed');
// Retries retain the recipient identity; a disconnected channel never calls a provider.
$retry = $service->save(array_replace($input,['idempotency_key'=>uniqid(),'interval_seconds'=>0]),1); $dispatch->scheduleDue();
$rr = $db->table('chat_campaign_run_recipients')->where('campaign_id',$retry['id'])->get(1)->getRowArray();
$fake->fail = true; $failed = $dispatch->dispatchRecipient($retry['id'],(int)$rr['id']);
$check(($failed['status']??'') === 'retry', 'temporary failure schedules retry');
$fake->fail = false;
$check(($dispatch->dispatchRecipient($retry['id'],(int)$rr['id'])['reason']??'') === 'recipient_not_available','retry delay is enforced in UTC');
$db->table('chat_campaign_run_recipients')->where('id',$rr['id'])->update(['available_at'=>gmdate('Y-m-d H:i:s',time()-1)]);
$check(($dispatch->dispatchRecipient($retry['id'],(int)$rr['id'])['status']??'') === 'sent','available retry succeeds');
$service->stop($retry['id'],1);
$rate = $service->save(array_replace($input,['idempotency_key'=>uniqid(),'interval_seconds'=>0,'rate_limit_per_minute'=>1]),1); $dispatch->scheduleDue();
$rateRows = $db->table('chat_campaign_run_recipients')->where('campaign_id',$rate['id'])->get()->getResultArray();
$dispatch->dispatchRecipient($rate['id'],(int)$rateRows[0]['id']);
$check(($dispatch->dispatchRecipient($rate['id'],(int)$rateRows[1]['id'])['reason']??'') === 'campaign_interval','per-minute limit enforced at send time');
$service->stop($rate['id'],1);
$opt = $service->save(array_replace($input,['idempotency_key'=>uniqid(),'interval_seconds'=>0]),1); $dispatch->scheduleDue();
$optRow = $db->table('chat_campaign_run_recipients')->where('campaign_id',$opt['id'])->get(1)->getRowArray();
(new Chatwoot_plugin\Models\Chat_contacts_model())->create_record(['instance_id'=>$instanceId,'phone_normalized'=>$optRow['phone_normalized'],'name'=>'Opt-out','opt_out'=>1]);
$beforeCalls = count($fake->calls);
$check(($dispatch->dispatchRecipient($opt['id'],(int)$optRow['id'])['status']??'') === 'opt_out' && count($fake->calls) === $beforeCalls,'late opt-out blocks provider call');
$service->stop($opt['id'],1);
// Official template campaigns retain their existing provider path.
$metaId = $instances->upsert_instance('campaign-meta-' . uniqid(), ['name'=>'Meta test','provider_type'=>'meta_cloud','meta_phone_number_id'=>'123456','meta_waba_id'=>'987654','connection_status'=>'connected','active'=>1]);
$templateId = (new Chatwoot_plugin\Models\Chat_campaign_templates_model())->create_record(['instance_id'=>$metaId,'name'=>'approved_test','message_content'=>'Olá','language_code'=>'pt_BR','provider_status'=>'approved','components_json'=>'[]','active'=>1]);
$official = $service->save(array_replace($input,['idempotency_key'=>uniqid(),'instance_id'=>$metaId,'campaign_type'=>'official','template_id'=>$templateId,'message'=>'Olá','interval_seconds'=>0]),1); $dispatch->scheduleDue();
$or = $db->table('chat_campaign_run_recipients')->where('campaign_id',$official['id'])->get(1)->getRowArray();
$check(($dispatch->dispatchRecipient($official['id'],(int)$or['id'])['status']??'') === 'sent' && end($fake->calls) === 'template:approved_test','official template dispatch remains compatible');
$service->stop($official['id'],1);
echo "$checks campaign integration checks passed.\n";
