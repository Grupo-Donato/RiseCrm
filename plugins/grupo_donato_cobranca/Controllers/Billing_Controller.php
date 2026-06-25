<?php

declare(strict_types=1);

namespace grupo_donato_cobranca\Controllers;

use App\Controllers\Security_Controller;
use grupo_donato_cobranca\Config\Permissions;
use grupo_donato_cobranca\Services\AccessService;
use grupo_donato_cobranca\Services\UnitService;

abstract class Billing_Controller extends Security_Controller
{
    protected AccessService $access;
    protected int $unitId;

    public function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();
        $this->access = new AccessService($this->login_user);
        $this->access->require(Permissions::VIEW);
        $this->unitId = UnitService::activeUnitId($this->login_user);
    }

    protected function render(string $view, array $data = [])
    {
        return $this->template->rander('grupo_donato_cobranca\\Views\\' . str_replace('/', '\\', $view), $data);
    }

    protected function fragment(string $view, array $data = [])
    {
        return $this->template->view('grupo_donato_cobranca\\Views\\' . str_replace('/', '\\', $view), $data);
    }

    protected function actorId(): int
    {
        return (int) ($this->login_user->id ?? 0);
    }

    protected function success(string $message, array $extra = []): void
    {
        echo json_encode(array_merge(['success' => true, 'message' => $message], $extra));
    }

    protected function fail(\Throwable $e): void
    {
        $key = $e->getMessage();
        log_message('error', 'GDC request: ' . get_class($e) . ': ' . $key);
        $message = str_starts_with($key, 'gdc_') ? app_lang($key) : app_lang('error_occurred');
        echo json_encode(['success' => false, 'message' => $message]);
    }

    protected function filters(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $this->request->getPost($key) ?? $this->request->getGet($key);
        }
        return $out;
    }
}
