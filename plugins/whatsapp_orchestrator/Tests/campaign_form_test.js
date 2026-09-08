'use strict';
const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const source = fs.readFileSync(process.argv[2] || 'Assets/js/hub-workspace.js', 'utf8');
const snippet = source.slice(source.indexOf('    function campaignNumbers()'), source.indexOf('    function uploadCampaignMedia('));
const fields = {
    'impulso-campaign-manual-numbers': '[{"numero":"+5511988880001","variaveis":{"nome":"Ana","pedido":"123"}}]',
    'impulso-campaign-name': 'Campanha', 'impulso-campaign-instance': '7',
    'impulso-campaign-type': 'recurring', 'impulso-campaign-message': 'Olá {nome}, pedido {pedido}',
    'impulso-campaign-timezone': 'America/Sao_Paulo', 'impulso-campaign-start-date': '2026-09-09',
    'impulso-campaign-start-time': '09:30:15', 'impulso-campaign-ends-at': '2026-09-30T18:00',
    'impulso-campaign-interval': '15', 'impulso-campaign-audience-source': 'manual'
};
const ctx = { byId: id => ({value:fields[id] || '', checked:false}), all: () => [{value:'seg'},{value:'qua'}], workspace:{pendingCampaignMediaId:42,campaignIdempotencyKey:'stable-key'} };
vm.createContext(ctx); vm.runInContext(snippet, ctx);
let payload = ctx.campaignPayload();
assert.equal(payload.numbers[0].variaveis.pedido, '123');
assert.equal(payload.ends_at, '2026-09-30T18:00');
assert.equal(payload.interval_seconds, 15);
assert.equal(payload.media_id, 42);
assert.equal(payload.idempotency_key, ctx.campaignPayload().idempotency_key);
assert.equal(payload.start_time, '09:30:15');
fields['impulso-campaign-manual-numbers'] = '+55 (11) 98888-0001\n5511988880002';
assert.equal(ctx.campaignNumbers().join(','), '5511988880001,5511988880002');
fields['impulso-campaign-manual-numbers'] = '[broken';
assert.throws(() => ctx.campaignPayload(), /JSON válido/);
console.log('8 campaign form checks passed.');
