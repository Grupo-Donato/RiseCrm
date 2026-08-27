<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Valida, normaliza e armazena a única versão WebP da foto de um aluno.
 *
 * Os arquivos ficam no volume privado de WRITEPATH e só são entregues por uma
 * rota autenticada. O nome original nunca participa do caminho persistido.
 */
final class StudentPhotoService
{
    public const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;
    public const MAX_DIMENSION = 500;
    public const WEBP_QUALITY = 78;

    // Limita o bitmap descompactado antes que o GD crie buffers adicionais.
    // 25 MP ainda cobre fotos comuns de celular sem pressionar o memory_limit.
    private const MAX_SOURCE_PIXELS = 25000000;
    private const RELATIVE_BASE = "uploads/grupo_donato/alunos";

    private string $storageRoot;

    public function __construct(?string $storageRoot = null)
    {
        $defaultRoot = defined("WRITEPATH") ? WRITEPATH : sys_get_temp_dir();
        $this->storageRoot = rtrim($storageRoot ?: $defaultRoot, "/\\");
    }

    public function hasUpload(?UploadedFile $file): bool
    {
        return $file !== null && $file->getError() !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * @return array{mime:string,type:int,width:int,height:int,size:int}
     */
    public function validate(UploadedFile $file): array
    {
        if (!$file->isValid() || $file->getError() !== UPLOAD_ERR_OK) {
            throw new \DomainException("Não foi possível enviar a foto. Selecione o arquivo novamente.");
        }

        $source = $file->getTempName();
        $actualSize = is_file($source) ? (int) @filesize($source) : 0;
        $reportedSize = (int) $file->getSize();
        $size = max($actualSize, $reportedSize);
        if ($size <= 0) {
            throw new \DomainException("A foto enviada está vazia ou não pôde ser lida.");
        }
        if ($size > self::MAX_UPLOAD_BYTES) {
            throw new \DomainException("A foto deve ter no máximo 10 MB.");
        }

        if (!is_file($source) || !is_readable($source)) {
            throw new \DomainException("A foto enviada não pôde ser lida.");
        }

        $imageInfo = @getimagesize($source);
        $type = (int) ($imageInfo[2] ?? 0);
        $mime = strtolower((string) ($imageInfo["mime"] ?? ""));
        $allowed = [
            IMAGETYPE_JPEG => "image/jpeg",
            IMAGETYPE_PNG => "image/png",
            IMAGETYPE_WEBP => "image/webp",
        ];
        if (!$imageInfo || !isset($allowed[$type]) || $mime !== $allowed[$type]) {
            throw new \DomainException("O arquivo selecionado não é uma imagem JPG, PNG ou WebP válida.");
        }

        try {
            $detectedMime = strtolower((string) $file->getMimeType());
        } catch (\Throwable $e) {
            throw new \DomainException("O conteúdo da foto não pôde ser validado.");
        }
        if ($detectedMime !== $allowed[$type]) {
            throw new \DomainException("O conteúdo do arquivo não corresponde a uma imagem válida.");
        }

        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);
        if ($width <= 0 || $height <= 0 || ($width * $height) > self::MAX_SOURCE_PIXELS) {
            throw new \DomainException("A imagem possui dimensões grandes demais para ser processada com segurança.");
        }

        $this->assertRuntimeSupport();

        return [
            "mime" => $mime,
            "type" => $type,
            "width" => $width,
            "height" => $height,
            "size" => $size,
        ];
    }

    /** Processa o upload e devolve somente o caminho relativo persistível. */
    public function store(UploadedFile $file, int $studentId): string
    {
        if ($studentId <= 0) {
            throw new \InvalidArgumentException("Identificador de aluno inválido.");
        }

        $metadata = $this->validate($file);
        $source = $file->getTempName();
        $relativeDirectory = self::RELATIVE_BASE . "/" . $studentId;
        $directory = $this->storageRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $relativeDirectory);
        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new \DomainException("Não foi possível preparar o armazenamento da foto.");
        }

        try {
            $token = bin2hex(random_bytes(16));
            $temporaryToken = bin2hex(random_bytes(12));
        } catch (\Throwable $e) {
            throw new \DomainException("Não foi possível preparar um nome seguro para a foto.");
        }
        $filename = "profile-" . $token . ".webp";
        $relativePath = $relativeDirectory . "/" . $filename;
        $finalPath = $directory . DIRECTORY_SEPARATOR . $filename;
        $temporaryPath = $directory . DIRECTORY_SEPARATOR . ".photo-" . $temporaryToken . ".tmp";
        $image = null;

        try {
            // O resize ocorre antes da rotação. Como o limite é uma caixa
            // simétrica de 500x500, a orientação final continua dentro do
            // limite e evitamos o bug de dimensões do handler após rotate 90°.
            $image = \Config\Services::image("gd")->withFile($source);
            $scale = min(
                1,
                self::MAX_DIMENSION / $metadata["width"],
                self::MAX_DIMENSION / $metadata["height"]
            );
            if ($scale < 1) {
                $targetWidth = max(1, (int) floor($metadata["width"] * $scale));
                $targetHeight = max(1, (int) floor($metadata["height"] * $scale));
                $image->resize($targetWidth, $targetHeight, false);
            }

            $saved = $image
                ->reorient(true)
                ->convert(IMAGETYPE_WEBP)
                ->save($temporaryPath, self::WEBP_QUALITY);
            if (!$saved || !is_file($temporaryPath) || filesize($temporaryPath) <= 0) {
                throw new \RuntimeException("image_save_failed");
            }

            $outputInfo = @getimagesize($temporaryPath);
            if (($outputInfo[2] ?? null) !== IMAGETYPE_WEBP
                || (int) ($outputInfo[0] ?? 0) > self::MAX_DIMENSION
                || (int) ($outputInfo[1] ?? 0) > self::MAX_DIMENSION) {
                throw new \RuntimeException("image_output_invalid");
            }

            if (!@rename($temporaryPath, $finalPath)) {
                throw new \RuntimeException("image_publish_failed");
            }
            @chmod($finalPath, 0640);

            return $relativePath;
        } catch (\DomainException $e) {
            throw $e;
        } catch (\Throwable $e) {
            log_message("error", "GD foto de aluno: falha ao processar imagem (" . get_class($e) . ").");
            throw new \DomainException("Não foi possível processar a foto. Verifique o arquivo e tente novamente.");
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
            unset($image);
            // Uploads HTTP já são temporários; antecipamos a limpeza sem tocar
            // em arquivos comuns usados pelos testes/CLI.
            if (is_uploaded_file($source)) {
                @unlink($source);
            }
        }
    }

    /** Resolve um caminho persistido somente se ele pertencer ao aluno informado. */
    public function absolutePath(?string $relativePath, int $studentId): ?string
    {
        $relativePath = trim((string) $relativePath);
        if ($studentId <= 0 || !preg_match(
            "#^uploads/grupo_donato/alunos/([1-9][0-9]*)/profile-([a-f0-9]{32})\\.webp$#D",
            $relativePath,
            $matches
        ) || (int) $matches[1] !== $studentId) {
            return null;
        }

        $base = $this->storageRoot . DIRECTORY_SEPARATOR
            . str_replace("/", DIRECTORY_SEPARATOR, self::RELATIVE_BASE . "/" . $studentId);
        $candidate = $this->storageRoot . DIRECTORY_SEPARATOR
            . str_replace("/", DIRECTORY_SEPARATOR, $relativePath);
        $realBase = realpath($base);
        $realCandidate = realpath($candidate);
        if (!$realBase || !$realCandidate) {
            return null;
        }

        $prefix = rtrim($realBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($realCandidate, $prefix) || !is_file($realCandidate)) {
            return null;
        }

        return $realCandidate;
    }

    /** Remove apenas a foto validada do aluno; nunca aceita caminho arbitrário. */
    public function remove(?string $relativePath, int $studentId): bool
    {
        $absolutePath = $this->absolutePath($relativePath, $studentId);
        if (!$absolutePath) {
            return $relativePath === null || trim((string) $relativePath) === "";
        }

        if (!@unlink($absolutePath)) {
            log_message("warning", "GD foto de aluno: não foi possível remover um arquivo antigo.");
            return false;
        }

        $directory = dirname($absolutePath);
        $entries = @scandir($directory);
        if (is_array($entries) && count(array_diff($entries, [".", ".."])) === 0) {
            @rmdir($directory);
        }

        return true;
    }

    private function assertRuntimeSupport(): void
    {
        $requiredFunctions = [
            "imagecreatefromjpeg",
            "imagecreatefrompng",
            "imagecreatefromwebp",
            "imagewebp",
            "exif_read_data",
        ];
        foreach ($requiredFunctions as $function) {
            if (!function_exists($function)) {
                throw new \DomainException("O servidor ainda não possui suporte completo a WebP e orientação de fotos.");
            }
        }
    }
}
