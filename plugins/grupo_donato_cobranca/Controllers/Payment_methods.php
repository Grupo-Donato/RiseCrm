<?php

declare(strict_types=1);

namespace grupo_donato_cobranca\Controllers;

use grupo_donato_cobranca\Config\Permissions;
use grupo_donato_cobranca\Services\PaymentMethodService;

final class Payment_methods extends Billing_Controller
{
    private PaymentMethodService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new PaymentMethodService($this->unitId, $this->actorId(), $this->login_user);
    }

    public function index()
    {
        return $this->render('payment_methods/index', ['customers' => $this->service->customers(), 'can_manage' => $this->access->can(Permissions::MANAGE)]);
    }

    public function data()
    {
        try {
            $result = $this->service->page(append_server_side_filtering_commmon_params($this->filters(['status'])));
            $result['data'] = array_map(function ($row) {
                $options = $this->access->can(Permissions::MANAGE) && $row->status === 'active'
                    ? ajax_anchor(get_uri('cobranca/payment-methods/deactivate'), '<i data-feather="x-circle" class="icon-16"></i>', ['class' => 'btn btn-default btn-sm', 'data-post-id' => $row->id, 'data-reload-on-success' => 1, 'title' => app_lang('gdc_deactivate_card')])
                    : '';
                return [
                    'customer' => esc($row->customer_name),
                    'card' => esc(trim(($row->brand ?: 'Cartão') . ' •••• ' . ($row->last4 ?: '----'))),
                    'expires' => ($row->exp_month && $row->exp_year) ? sprintf('%02d/%d', $row->exp_month, $row->exp_year) : '-',
                    'default' => $row->is_default ? app_lang('yes') : app_lang('no'),
                    'status' => app_lang('gdc_payment_method_status_' . $row->status),
                    'options' => $options,
                ];
            }, $result['data']);
            return $this->response->setJSON($result);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    public function session()
    {
        try {
            $this->access->require(Permissions::MANAGE);
            $result = $this->service->createSession((int) $this->request->getPost('customer_account_id'));
            $this->success(app_lang('gdc_tokenization_started'), [
                'checkout_url' => (string) ($result['checkout_url'] ?? ''),
                'client_token' => (string) ($result['client_token'] ?? ''),
                'session_id' => (string) ($result['session_id'] ?? ''),
            ]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    public function deactivate()
    {
        try {
            $this->access->require(Permissions::MANAGE);
            $this->service->deactivate((int) $this->request->getPost('id'));
            $this->success(app_lang('record_saved'));
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }
}
