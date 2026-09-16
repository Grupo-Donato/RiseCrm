<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Efetiva o aluno nas mesmas tabelas legadas usadas pelo operacional.
 * O serviço existe para que o controller não precise conhecer a transação
 * nem reimplementar a regra financeira da matrícula pública.
 */
final class LegacyOnlineStudentService
{
    private BaseConnection $db;
    private $responsaveis;
    private $alunos;
    private $cobrancas;

    public function __construct(?BaseConnection $db = null, $responsaveis = null, $alunos = null, $cobrancas = null)
    {
        $this->db = $db ?: db_connect();
        $this->responsaveis = $responsaveis ?: model("grupo_donato_gestao\\Operacional\\Models\\Bombeiros_responsaveis_model");
        $this->alunos = $alunos ?: model("grupo_donato_gestao\\Operacional\\Models\\Bombeiros_alunos_model");
        $this->cobrancas = $cobrancas ?: model("grupo_donato_gestao\\Operacional\\Models\\Bombeiros_cobrancas_model");
    }

    /** @return array{student_id:int,responsible_id:int,matricula:string} */
    public function create(array $payload, int $unitId, string $contractNumber, bool $manageTransaction = true): array
    {
        if ($unitId < 1 || trim((string) ($payload["nome_aluno"] ?? "")) === '') {
            throw new RuntimeException("Os dados da matrícula estão incompletos.", 422);
        }

        $sequenceLock = false;
        try {
            $sequenceLock = $this->acquireSequenceLock();
            if (!$sequenceLock) {
                throw new RuntimeException("Não foi possível reservar o número da matrícula. Tente novamente.", 409);
            }

            $matricula = $this->nextMatricula();
            if ($manageTransaction) {
                $this->db->transStart();
            }

            $responsibleData = [
                "nome" => (string) $payload["responsavel_nome"],
                "nascimento" => $payload["responsavel_nascimento"] ?: null,
                "rg" => (string) ($payload["responsavel_rg"] ?? ""),
                "cpf" => (string) ($payload["responsavel_cpf"] ?? ""),
                "endereco" => (string) ($payload["responsavel_endereco"] ?? ""),
                "numero" => (string) ($payload["responsavel_numero"] ?? ""),
                "complemento" => (string) ($payload["responsavel_complemento"] ?? ""),
                "bairro" => (string) ($payload["responsavel_bairro"] ?? ""),
                "cep" => (string) ($payload["responsavel_cep"] ?? ""),
                "cidade" => (string) ($payload["responsavel_cidade"] ?? ""),
                "whats" => (string) $payload["responsavel_whats"],
                "celular" => (string) ($payload["responsavel_celular"] ?? ""),
                "recado" => (string) ($payload["responsavel_recado"] ?? ""),
                "email" => ($payload["responsavel_email"] ?? "") ?: null,
                "status" => "Ativo",
                "deleted" => 0,
            ];
            $responsibleId = (int) $this->responsaveis->ci_save($responsibleData);
            if ($responsibleId < 1) {
                throw new RuntimeException("Não foi possível criar o responsável.");
            }

            $studentData = [
                "unidade_id" => $unitId,
                "student_group_unit_id" => $unitId,
                "student_group_id" => null,
                "matricula" => $matricula,
                "responsavel_id" => $responsibleId,
                "nome_aluno" => (string) $payload["nome_aluno"],
                "nascimento_aluno" => (string) $payload["nascimento_aluno"],
                "rg_aluno" => (string) ($payload["rg_aluno"] ?? ""),
                "cpf_aluno" => (string) ($payload["cpf_aluno"] ?? ""),
                "turma" => (string) ($payload["horario"] ?? ""),
                "horario" => (string) ($payload["horario"] ?? ""),
                "curso_nome" => (string) $payload["curso_nome"],
                "num_parcelas" => (int) $payload["num_parcelas"],
                "valor_mensalidade" => (float) $payload["valor_mensalidade"],
                "valor_inscricao" => (float) $payload["valor_inscricao"],
                "data_inscricao" => (string) $payload["data_inscricao"],
                "valor_mensal" => (float) $payload["valor_mensalidade"],
                "data_primeira_parcela" => (string) $payload["data_primeira_parcela"],
                "data_inicio" => (string) $payload["data_inicio"],
                "data_matricula" => date("Y-m-d"),
                "tamanho_camisa" => (string) ($payload["tamanho_camisa"] ?? ""),
                "matricula_efetuada" => 0,
                "uniforme_efetuado" => 0,
                "material_efetuado" => 0,
                "melhor_horario_ligacao" => (string) ($payload["melhor_horario_ligacao"] ?? ""),
                "cidade_assinatura" => (string) $payload["cidade_assinatura"],
                "estado_assinatura" => (string) $payload["estado_assinatura"],
                "dia_assinatura" => (string) ($payload["signed_day"] ?? ""),
                "mes_assinatura" => (string) ($payload["signed_month"] ?? ""),
                "ano_assinatura" => (string) ($payload["signed_year"] ?? ""),
                "assinatura_contratante" => "Contrato " . $contractNumber,
                "li_ciente" => 1,
                "origem_matricula" => "telemarketing",
                "status" => "Ativo",
                "deleted" => 0,
            ];
            $studentId = (int) $this->alunos->ci_save($studentData);
            if ($studentId < 1) {
                throw new RuntimeException("Não foi possível criar o aluno.");
            }

            $this->db->table($this->db->prefixTable("grupo_donato_alunos"))
                ->where("id", $studentId)
                ->update(["student_group_unit_id" => $unitId, "student_group_id" => $studentId]);

            $this->createCharges($studentId, $responsibleId, $unitId, $payload);
            if ($manageTransaction) {
                $this->db->transComplete();
                if ($this->db->transStatus() === false) {
                    throw new RuntimeException("Não foi possível concluir a matrícula.");
                }
            }

            return ["student_id" => $studentId, "responsible_id" => $responsibleId, "matricula" => $matricula];
        } catch (\Throwable $e) {
            if ($manageTransaction) {
                $this->db->transRollback();
            }
            throw $e;
        } finally {
            if ($sequenceLock) {
                $this->db->query("SELECT RELEASE_LOCK('grupo_donato_matricula_aluno')");
            }
        }
    }

    private function createCharges(int $studentId, int $responsibleId, int $unitId, array $payload): void
    {
        $firstDue = (string) $payload["data_primeira_parcela"];
        $installments = max(1, (int) $payload["num_parcelas"]);
        for ($i = 0; $i < $installments; $i++) {
            $due = date("Y-m-d", strtotime($firstDue . " +" . $i . " month"));
            $this->saveCharge([
                "aluno_id" => $studentId,
                "responsavel_id" => $responsibleId,
                "unit_id" => $unitId,
                "vencimento" => $due,
                "valor" => (float) $payload["valor_mensalidade"],
                "competencia" => date("m/Y", strtotime($due)),
                "mes_referencia" => (int) date("m", strtotime($due)),
                "ano_referencia" => (int) date("Y", strtotime($due)),
                "descricao" => ($i + 1) . "ª parcela",
                "status" => "Pendente",
                "tipo" => "Mensalidade",
            ]);
        }

        $registrationDate = (string) $payload["data_inscricao"];
        $this->saveCharge([
            "aluno_id" => $studentId,
            "responsavel_id" => $responsibleId,
            "unit_id" => $unitId,
            "vencimento" => $registrationDate,
            "valor" => (float) $payload["valor_inscricao"],
            "competencia" => date("m/Y", strtotime($registrationDate)),
            "mes_referencia" => (int) date("m", strtotime($registrationDate)),
            "ano_referencia" => (int) date("Y", strtotime($registrationDate)),
            "descricao" => "Matrícula",
            "status" => "Pendente",
            "tipo" => "Matrícula",
        ]);

        $today = date("Y-m-d");
        $this->saveCharge([
            "aluno_id" => $studentId,
            "responsavel_id" => $responsibleId,
            "unit_id" => $unitId,
            "vencimento" => $today,
            "valor" => 67.00,
            "competencia" => date("m/Y"),
            "mes_referencia" => (int) date("m"),
            "ano_referencia" => (int) date("Y"),
            "descricao" => "Camiseta",
            "status" => "Pendente",
            "tipo" => "Camiseta",
        ]);
    }

    private function saveCharge(array $data): void
    {
        if (!$this->cobrancas->ci_save($data)) {
            throw new RuntimeException("Não foi possível gerar as cobranças da matrícula.");
        }
    }

    private function acquireSequenceLock(): bool
    {
        $row = $this->db->query("SELECT GET_LOCK('grupo_donato_matricula_aluno', 10) AS lock_value")->getRow();
        return $row && (int) ($row->lock_value ?? 0) === 1;
    }

    private function nextMatricula(): string
    {
        $table = $this->db->prefixTable("grupo_donato_alunos");
        $row = $this->db->query("SELECT MAX(CAST(matricula AS UNSIGNED)) AS last_number FROM `{$table}` WHERE deleted=0 AND matricula REGEXP '^[0-9]+$'")->getRow();
        $next = ((int) ($row->last_number ?? 0)) + 1;
        return str_pad((string) $next, 4, "0", STR_PAD_LEFT);
    }
}
