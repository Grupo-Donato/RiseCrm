<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Config\Constants;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

/** Ajusta o catálogo físico para quatro churrasqueiras e um salão. */
final class V066_adjust_barbecue_catalog extends SchemaVersion
{
    public function version(): string { return "066"; }
    public function description(): string { return "Renomeia a CH5 para churrasqueira/salão e retira a CH6 do catálogo ativo."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_resources";
        if (!$db->tableExists($table)) {
            return;
        }

        $now = function_exists("get_current_utc_time") ? get_current_utc_time() : gmdate("Y-m-d H:i:s");
        $rows = $db->table($table)
            ->select("id,unit_id,code,resource_type")
            ->where("resource_type", Constants::BARBECUE_RESOURCE_TYPE)
            ->whereIn("code", ["CH5", "CH5-SL", "CH6"])
            ->where("deleted", 0)
            ->get()
            ->getResult();

        $byUnit = [];
        foreach ($rows as $row) {
            $byUnit[(int) $row->unit_id][(string) $row->code] = $row;
        }

        foreach ($byUnit as $resources) {
            $oldSalon = $resources["CH5"] ?? null;
            $salon = $resources["CH5-SL"] ?? null;

            if ($oldSalon && !$salon) {
                // A alteração conserva o mesmo ID para não quebrar reservas,
                // séries, preços ou histórico que apontem para a antiga CH5.
                $db->table($table)->where("id", (int) $oldSalon->id)->update([
                    "code" => "CH5-SL",
                    "name" => "Churrasqueira 5 / Salão",
                    "updated_at" => $now,
                    "updated_by" => null,
                ]);
            } elseif ($salon) {
                // Se a configuração já tiver sido parcialmente aplicada,
                // somente normaliza o nome da unidade de salão.
                $db->table($table)->where("id", (int) $salon->id)->update([
                    "name" => "Churrasqueira 5 / Salão",
                    "is_active" => 1,
                    "is_bookable" => 1,
                    "updated_at" => $now,
                    "updated_by" => null,
                ]);

                // Não deixa uma CH5 duplicada continuar aparecendo no menu.
                if ($oldSalon) {
                    $db->table($table)->where("id", (int) $oldSalon->id)->update([
                        "is_active" => 0,
                        "is_bookable" => 0,
                        "deleted" => 1,
                        "updated_at" => $now,
                        "updated_by" => null,
                    ]);
                }
            }

            if (!empty($resources["CH6"])) {
                // Exclusão lógica: reservas e vínculos antigos continuam
                // íntegros, mas o recurso não aparece para novas locações.
                $db->table($table)->where("id", (int) $resources["CH6"]->id)->update([
                    "is_active" => 0,
                    "is_bookable" => 0,
                    "deleted" => 1,
                    "updated_at" => $now,
                    "updated_by" => null,
                ]);
            }
        }
    }
}
