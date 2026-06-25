<?php

declare(strict_types=1);

namespace grupo_donato_cobranca\Controllers;

use grupo_donato_cobranca\Config\Permissions;
use grupo_donato_cobranca\Services\SubscriptionService;

final class Subscriptions extends Billing_Controller
{
    private SubscriptionService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new SubscriptionService($this->unitId, $this->actorId(), $this->login_user);
    }

    public function index()
    {
        return $this->render('subscriptions/index', ['can_manage' => $this->access->can(Permissions::MANAGE)]);
    }

    public function data()
    {
        try {
            $result = $this->service->page(append_server_side_filtering_commmon_params($this->filters(['status'])));
            $result['data'] = array_map(function ($row) {
                $card = $row->collection_method === 'credit_card' ? trim(($row->brand ?: 'Cartão') . ' •••• ' . ($row->last4 ?: '----')) : '-';
                $options = '';
                if ($this->access->can(Permissions::MANAGE)) {
                    $options .= modal_anchor(get_uri('cobranca/subscriptions/modal'), '<i data-feather="edit" class="icon-16"></i>', ['class' => 'btn btn-default btn-sm', 'title' => app_lang('gdc_edit_subscription'), 'data-post-id' => $row->id]);
                    if ($row->status === 'active') {
                        $options .= ajax_anchor(get_uri('cobranca/subscriptions/status'), '<i data-feather="pause" class="icon-16"></i>', ['class' => 'btn btn-default btn-sm', 'data-post-id' => $row->id, 'data-post-status' => 'paused', 'data-reload-on-success' => 1]);
                    } elseif ($row->status === 'paused') {
                        $options .= ajax_anchor(get_uri('cobranca/subscriptions/status'), '<i data-feather="play" class="icon-16"></i>', ['class' => 'btn btn-default btn-sm', 'data-post-id' => $row->id, 'data-post-status' => 'active', 'data-reload-on-success' => 1]);
                    }
                }
                return [
                    'customer' => esc($row->customer_name),
                    'source' => app_lang('gdc_source_' . $row->source_type) . ($row->source_id ? ' #' . (int) $row->source_id : ''),
                    'method' => app_lang('gdc_method_' . $row->collection_method),
                    'card' => esc($card),
                    'day' => (int) $row->charge_day,
                    'status' => app_lang('gdc_subscription_status_' . $row->status),
                    'last_charge' => $row->last_charge_at ? format_to_datetime($row->last_charge_at) : '-',
                    'options' => $options,
                ];
            }, $result['data']);
            return $this->response->setJSON($result);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    public function modal()
    {
        try {
            $this->access->require(Permissions::MANAGE);
            $id = (int) $this->request->getPost('id');
            return $this->fragment('subscriptions/modal', [
                'subscription' => $id ? $this->service->get($id) : null,
                'customers' => $this->service->customers(),
                'payment_methods' => $this->service->paymentMethods(),
            ]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    public function save()
    {
        try {
            $this->access->require(Permissions::MANAGE);
            $result = $this->service->save([
                'customer_account_id' => $this->request->getPost('customer_account_id'),
                'source_type' => $this->request->getPost('source_type'),
                'source_id' => $this->request->getPost('source_id'),
                'collection_method' => $this->request->getPost('collection_method'),
                'payment_method_id' => $this->request->getPost('payment_method_id'),
                'charge_day' => $this->request->getPost('charge_day'),
                'max_attempts' => $this->request->getPost('max_attempts'),
                'retry_interval_days' => $this->request->getPost('retry_interval_days'),
                'notes' => $this->request->getPost('notes'),
            ], (int) $this->request->getPost('id'));
            $this->success(app_lang('record_saved'), $result);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    public function status()
    {
        try {
            $this->access->require(Permissions::MANAGE);
            $this->service->setStatus((int) $this->request->getPost('id'), (string) $this->request->getPost('status'));
            $this->success(app_lang('record_saved'));
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }
}
