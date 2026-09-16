<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use App\Libraries\Pdf;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/** Renderiza a versão web e a versão A4 do contrato da matrícula online. */
final class OnlineContractService
{
    public const CONTRACT_VERSION = "camisa9-v1-2026-09";
    private const STORAGE_DIRECTORY = "gd_online_contracts";

    public function contractVersion(): string
    {
        return self::CONTRACT_VERSION;
    }

    public function renderPreview(array $payload): string
    {
        return $this->render($payload, null, "", "", false);
    }

    /**
     * @return array{html:string,html_path:string,pdf_path:string,pdf_sha256:string,signature_path:string}
     */
    public function createFinalFiles(
        array $payload,
        int $draftId,
        string $signedAtUtc,
        string $contractNumber,
        string $signaturePath
    ): array {
        if ($draftId < 1 || trim($contractNumber) === '' || trim($signaturePath) === '') {
            throw new RuntimeException("Não foi possível preparar o contrato.");
        }

        $signatureAbsolute = $this->absolutePath($signaturePath);
        if (!is_file($signatureAbsolute)) {
            throw new RuntimeException("A assinatura não está disponível para gerar o contrato.");
        }

        $directory = self::STORAGE_DIRECTORY . "/draft-" . $draftId;
        $htmlPath = $directory . "/contrato.html";
        $pdfPath = $directory . "/contrato.pdf";
        $html = $this->render($payload, $signedAtUtc, $signatureAbsolute, $contractNumber, true);

        $this->writePrivateFile($htmlPath, $html);
        $pdf = $this->makePdf($html, $contractNumber);
        $this->writePrivateFile($pdfPath, $pdf);

        $pdfAbsolute = $this->absolutePath($pdfPath);
        $hash = hash_file("sha256", $pdfAbsolute);
        if (!$hash) {
            throw new RuntimeException("Não foi possível verificar o contrato gerado.");
        }

        return [
            "html" => $html,
            "html_path" => $htmlPath,
            "pdf_path" => $pdfPath,
            "pdf_sha256" => $hash,
            "signature_path" => $signaturePath,
        ];
    }

    /** Armazena a assinatura como PNG privado, nunca como Base64 na tabela. */
    public function storeSignature(string $dataUrl, int $draftId): array
    {
        $dataUrl = trim($dataUrl);
        if (!preg_match("#^data:image/png;base64,([A-Za-z0-9+/=\r\n]+)$#", $dataUrl, $matches)) {
            throw new RuntimeException("Envie a assinatura desenhada no campo indicado.", 422);
        }

        $binary = base64_decode(str_replace(["\r", "\n"], "", $matches[1]), true);
        if ($binary === false || strlen($binary) < 100 || strlen($binary) > 1048576) {
            throw new RuntimeException("A assinatura está vazia ou excede o tamanho permitido.", 422);
        }

        $image = @getimagesizefromstring($binary);
        if (!$image || ($image["mime"] ?? "") !== "image/png") {
            throw new RuntimeException("A assinatura precisa ser uma imagem PNG válida.", 422);
        }
        $width = (int) ($image[0] ?? 0);
        $height = (int) ($image[1] ?? 0);
        if ($width < 240 || $height < 80 || $width > 2400 || $height > 900) {
            throw new RuntimeException("A área da assinatura possui dimensões inválidas.", 422);
        }
        if (!$this->hasVisibleInk($binary, $width, $height)) {
            throw new RuntimeException("Desenhe sua assinatura antes de continuar.", 422);
        }

        $relativePath = self::STORAGE_DIRECTORY . "/draft-" . max(1, $draftId) . "/signature.png";
        $this->writePrivateFile($relativePath, $binary);

        return [
            "path" => $relativePath,
            "sha256" => hash("sha256", $binary),
        ];
    }

    public function absolutePath(string $relativePath): string
    {
        $relativePath = str_replace(["\\", ".."], ["/", ""], trim($relativePath));
        $relativePath = ltrim($relativePath, "/");
        $root = rtrim((string) WRITEPATH, "/\\") . DIRECTORY_SEPARATOR . "uploads";
        $rootReal = realpath($root) ?: $root;
        $candidate = $rootReal . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $relativePath);
        $candidateDirectory = realpath(dirname($candidate));
        if ($candidateDirectory && !str_starts_with($candidateDirectory, $rootReal . DIRECTORY_SEPARATOR) && $candidateDirectory !== $rootReal) {
            throw new RuntimeException("Arquivo privado inválido.");
        }

        return $candidate;
    }

    private function render(array $payload, ?string $signedAtUtc, string $signatureAbsolute, string $contractNumber, bool $pdf): string
    {
        $signedAt = $signedAtUtc ? $this->localDateParts($signedAtUtc) : null;
        return view("grupo_donato_gestao\\Operacional\\Views\\matricula_contrato", [
            "payload" => $payload,
            "signed_at" => $signedAt,
            "signature_src" => $signatureAbsolute,
            "contract_number" => $contractNumber,
            "contract_version" => self::CONTRACT_VERSION,
            "pdf" => $pdf,
        ]);
    }

    private function makePdf(string $html, string $contractNumber): string
    {
        try {
            $pdf = new Pdf("online_contract");
            $pdf->SetCreator("Grupo Donato");
            $pdf->SetAuthor("Escola de Futebol Camisa 9");
            $pdf->SetTitle("Contrato " . $contractNumber);
            $pdf->SetMargins(18, 16, 18);
            $pdf->SetAutoPageBreak(true, 16);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->AddPage("P", "A4");
            $pdf->writeHTML($html, true, false, true, false, "");
            $content = $pdf->Output("", "S");
        } catch (\Throwable $e) {
            log_message("error", "Contrato online: falha ao gerar PDF: " . $e->getMessage());
            throw new RuntimeException("Não foi possível gerar o PDF do contrato.");
        }

        if (!is_string($content) || !str_starts_with($content, "%PDF")) {
            throw new RuntimeException("Não foi possível validar o PDF do contrato.");
        }

        return $content;
    }

    private function writePrivateFile(string $relativePath, string $contents): void
    {
        $absolutePath = $this->absolutePath($relativePath);
        $directory = dirname($absolutePath);
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException("Não foi possível preparar o armazenamento do contrato.");
        }
        if (@file_put_contents($absolutePath, $contents, LOCK_EX) === false) {
            throw new RuntimeException("Não foi possível salvar o contrato.");
        }
        @chmod($absolutePath, 0640);
    }

    private function hasVisibleInk(string $binary, int $width, int $height): bool
    {
        if (!function_exists("imagecreatefromstring")) {
            return true;
        }
        $image = @imagecreatefromstring($binary);
        if (!$image) {
            return false;
        }
        $stepX = max(1, (int) floor($width / 80));
        $stepY = max(1, (int) floor($height / 30));
        for ($x = 0; $x < $width; $x += $stepX) {
            for ($y = 0; $y < $height; $y += $stepY) {
                $color = imagecolorat($image, $x, $y);
                $alpha = ($color >> 24) & 0x7F;
                $red = ($color >> 16) & 0xFF;
                $green = ($color >> 8) & 0xFF;
                $blue = $color & 0xFF;
                if ($alpha < 120 || $red < 220 || $green < 220 || $blue < 220) {
                    imagedestroy($image);
                    return true;
                }
            }
        }
        imagedestroy($image);
        return false;
    }

    /** @return array{day:string,month:string,year:string,datetime:string} */
    private function localDateParts(string $utc): array
    {
        try {
            $date = new DateTimeImmutable($utc, new DateTimeZone("UTC"));
            $date = $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
        } catch (\Throwable $e) {
            $date = new DateTimeImmutable("now");
        }
        $months = [1 => "janeiro", 2 => "fevereiro", 3 => "março", 4 => "abril", 5 => "maio", 6 => "junho", 7 => "julho", 8 => "agosto", 9 => "setembro", 10 => "outubro", 11 => "novembro", 12 => "dezembro"];
        return [
            "day" => $date->format("d"),
            "month" => $months[(int) $date->format("n")] ?? $date->format("m"),
            "year" => $date->format("Y"),
            "datetime" => $date->format("d/m/Y H:i:s"),
        ];
    }
}
