<?php

declare(strict_types=1);

namespace grupo_donato_cobranca\Services;

final class SettingsService
{
    private $db;
    private int $unitId;

    public function __construct(int $unitId)
    {
        $this->db = db_connect();
        $this->unitId = $unitId;
    }

    public function all(): array
    {
        $rows = $this->db->table($this->db->prefixTable('gdc_settings'))
            ->select('setting_key,setting_value')
            ->where('unit_id', $this->unitId)->where('deleted', 0)->get()->getResultArray();
        $out = [
            'provider_code' => '',
            'environment' => 'sandbox',
            'financial_account_id' => '',
            'automatic_billing' => '0',
            'connector_label' => '',
        ];
        foreach ($rows as $row) {
            $out[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
        }
        return $out;
    }

    public function get(string $key, string $default = ''): string
    {
        $all = $this->all();
        return array_key_exists($key, $all) ? (string) $all[$key] : $default;
    }

    public function save(array $input, int $actorId): void
    {
        $allowed = ['provider_code', 'environment', 'financial_account_id', 'automatic_billing', 'connector_label'];
        $table = $this->db->prefixTable('gdc_settings');
        foreach ($allowed as $key) {
            $value = trim((string) ($input[$key] ?? ''));
            if ($key === 'environment' && !in_array($value, ['sandbox', 'production'], true)) {
                throw new \DomainException('gdc_invalid_environment');
            }
            if ($key === 'automatic_billing') {
                $value = $value === '1' ? '1' : '0';
            }
            $row = $this->db->table($table)->where('unit_id', $this->unitId)->where('setting_key', $key)->where('deleted', 0)->get(1)->getRow();
            $data = ['setting_value' => $value, 'updated_at' => gmdate('Y-m-d H:i:s'), 'updated_by' => $actorId ?: null];
            if ($row) {
                $this->db->table($table)->where('id', (int) $row->id)->update($data);
            } else {
                $data += ['unit_id' => $this->unitId, 'setting_key' => $key, 'created_at' => gmdate('Y-m-d H:i:s'), 'created_by' => $actorId ?: null, 'deleted' => 0];
                $this->db->table($table)->insert($data);
            }
        }
    }

    public function financialAccounts(): array
    {
        return $this->db->table($this->db->prefixTable('gd_financial_accounts'))
            ->select('id,name,account_type')
            ->where('unit_id', $this->unitId)->where('status', 'active')->where('deleted', 0)
            ->orderBy('name')->get()->getResultArray();
    }
}
