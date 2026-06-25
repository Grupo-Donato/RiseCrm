<?php

declare(strict_types=1);

namespace grupo_donato_cobranca\Services;

use grupo_donato_cobranca\Config\Permissions;

final class AccessService
{
    private object $user;
    public function __construct(object $user) { $this->user = $user; }
    public function can(string $key): bool { return Permissions::can($this->user, $key); }
    public function require(string $key): void { Permissions::require($this->user, $key); }
}
