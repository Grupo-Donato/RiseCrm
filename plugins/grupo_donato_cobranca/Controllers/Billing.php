<?php

declare(strict_types=1);

namespace grupo_donato_cobranca\Controllers;

use grupo_donato_cobranca\Config\Permissions;
use grupo_donato_cobranca\Services\ChargeService;

final class Billing extends Billing_Controller
{
    private ChargeService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ChargeService($this->unitId, $this->actorId(), $this->login_user);
    }

    public function index()
    {
        return $this->render('dashboard/index', ['metrics' => $this->service->dashboard(), 'can_manage' => $this->access->can(Permissions::MANAGE)]);
    }

    public function charges()
    {
        return $this->render('charges/index', ['can_manage' => $this->access->can(Permissions::MANAGE)]);
    }

    public function charges_data()
    {
        try {
            $options = append_server_side_filtering_commmon_params($this->filters(['status', 'collection_method']));
            $result = $this->service->page($options);
            $result['data'] = array_map(function ($row) {
                $actions = anchor(get_uri('cobranca/charges/view/' . $row->id), '<i data-feather="eye" class="icon-16"></i>', ['class' => 'btn btn-default btn-sm', 'title' => app_lang('gdc_view_charge')]);
                return [
                    'charge' => anchor(get_uri('cobranca/charges/view/' . $row->id), esc(substr((string) $row->charge_uuid, 0, 8))),
                    'receivable' => esc($row->receivable_number),
                    'customer' => esc($row->customer_name),
                    'method' => app_lang('gdc_method_' . $row->collection_method),
                    'amount' => to_currency((float) $row->amount),
                    'due' => format_to_date($row->due_date, false),
                    'status' => '<span class="badge bg-' . $this->statusClass((string) $row->status) . '">' . app_lang('gdc_status_' . $row->status) . '</span>',
                    'external' => esc($row->external_charge_id ?: '-'),
                    'options' => $actions,
                ];
            }, $result['data']);
            return $this->response->setJSON($result);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    public function charge_modal()
    {
        try {
            $this->access->require(Permissions::MANAGE);
            $receivables = $this->service->openReceivables();
            $methods = [];
            foreach ($receivables as $receivable) {
                $methods[(int) $receivable['customer_account_id']] = $this->service->paymentMethodsForCustomer((int) $receivable['customer_account_id']);
            }
            return $this->fragment('charges/modal', ['receivables' => $receivables, 'payment_methods' => $methods]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    public function create_charge()
    {
        try {
            $this->access->require(Permissions::MANAGE);
            $result = $this->service->create([
                'receivable_id' => $this->request->getPost('receivable_id'),
                'collection_method' => $this->request->getPost('collection_method'),
                'payment_method_id' => $this->request->getPost('payment_method_id'),
            ]);
            $this->success(app_lang('gdc_charge_created'), ['id' => (int) ($result['id'] ?? 0), 'duplicate' => !empty($result['duplicate'])]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    public function view_charge($id)
    {
        $charge = $this->service->get((int) $id);
        if (!$charge) {
            return show_404();
        }
        return $this->render('charges/view', ['charge' => $charge, 'can_manage' => $this->access->can(Permissions::MANAGE)]);
    }

    public function sync_charge()
    {
        try {
            $this->access->require(Permissions::MANAGE);
            $result = $this->service->sync((int) $this->request->getPost('id'));
            $this->success(app_lang('gdc_charge_synced'), $result);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    public function cancel_charge()
    {
        try {
            $this->access->require(Permissions::MANAGE);
            $result = $this->service->cancel((int) $this->request->getPost('id'));
            $this->success(app_lang('gdc_charge_cancelled'), $result);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    private function statusClass(string $status): string
    {
        return match ($status) {
            'paid' => 'success',
            'pending', 'processing', 'partially_paid' => 'warning',
            'failed', 'review' => 'danger',
            'cancelled', 'expired', 'refunded' => 'secondary',
            default => 'light',
        };
    }
}
