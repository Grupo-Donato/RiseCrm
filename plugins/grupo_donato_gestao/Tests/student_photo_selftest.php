<?php

declare(strict_types=1);

use CodeIgniter\HTTP\Files\UploadedFile;
use grupo_donato_gestao\Services\StudentPhotoService;

/** UploadedFile realmente legível, mas válido fora do SAPI HTTP. */
if (!class_exists("GdStudentPhotoCliUploadedFile", false)) {
    class GdStudentPhotoCliUploadedFile extends UploadedFile
    {
        public function isValid(): bool
        {
            return $this->getError() === UPLOAD_ERR_OK
                && is_file($this->getTempName())
                && is_readable($this->getTempName());
        }
    }
}

if (!function_exists("gd_student_photo_remove_tree")) {
    function gd_student_photo_remove_tree(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }

        foreach ((array) @scandir($path) as $entry) {
            if ($entry === "." || $entry === "..") {
                continue;
            }
            gd_student_photo_remove_tree($path . DIRECTORY_SEPARATOR . $entry);
        }
        @rmdir($path);
    }
}

if (!function_exists("gd_student_photo_add_orientation")) {
    /** Injeta um APP1/EXIF mínimo com a tag Orientation. */
    function gd_student_photo_add_orientation(string $path, int $orientation): void
    {
        $jpeg = (string) file_get_contents($path);
        if (!str_starts_with($jpeg, "\xFF\xD8")) {
            throw new RuntimeException("Fixture EXIF precisa ser JPEG.");
        }

        $tiff = "II" . pack("vV", 42, 8)
            . pack("v", 1)
            . pack("vvV", 0x0112, 3, 1)
            . pack("v", $orientation) . "\x00\x00"
            . pack("V", 0);
        $payload = "Exif\x00\x00" . $tiff;
        $segment = "\xFF\xE1" . pack("n", strlen($payload) + 2) . $payload;
        if (file_put_contents($path, substr($jpeg, 0, 2) . $segment . substr($jpeg, 2)) === false) {
            throw new RuntimeException("Não foi possível gravar EXIF na fixture.");
        }
    }
}

if (!function_exists("gd_student_photo_fixture")) {
    /** Gera imagens reais pelo GD, sem depender de arquivos versionados. */
    function gd_student_photo_fixture(
        string $path,
        int $type,
        int $width,
        int $height,
        ?int $orientation = null
    ): void {
        $image = imagecreatetruecolor($width, $height);
        if (!$image) {
            throw new RuntimeException("Não foi possível criar fixture GD.");
        }

        $background = imagecolorallocate($image, 32, 112, 176);
        $marker = imagecolorallocate($image, 244, 180, 0);
        imagefill($image, 0, 0, $background);
        imagefilledrectangle($image, 0, 0, max(1, intdiv($width, 3)), max(1, intdiv($height, 4)), $marker);

        $saved = match ($type) {
            IMAGETYPE_JPEG => imagejpeg($image, $path, 90),
            IMAGETYPE_PNG => imagepng($image, $path, 6),
            IMAGETYPE_WEBP => imagewebp($image, $path, 85),
            default => false,
        };
        imagedestroy($image);

        if (!$saved || !is_file($path) || filesize($path) <= 0) {
            throw new RuntimeException("Não foi possível salvar fixture GD.");
        }
        if ($orientation !== null) {
            gd_student_photo_add_orientation($path, $orientation);
        }
    }
}

if (!function_exists("gd_student_photo_upload")) {
    function gd_student_photo_upload(
        string $path,
        string $clientName,
        string $clientMime = "application/octet-stream",
        ?int $reportedSize = null,
        int $error = UPLOAD_ERR_OK
    ): GdStudentPhotoCliUploadedFile {
        return new GdStudentPhotoCliUploadedFile(
            $path,
            $clientName,
            $clientMime,
            $reportedSize ?? (is_file($path) ? (int) filesize($path) : 0),
            $error
        );
    }
}

if (!function_exists("gd_student_photo_selftest")) {
    function gd_student_photo_selftest(): void
    {
        echo "# Foto do aluno operacional\n";

        $requiredFunctions = [
            "imagecreatetruecolor",
            "imagejpeg",
            "imagepng",
            "imagewebp",
            "imagecreatefromjpeg",
            "imagecreatefrompng",
            "imagecreatefromwebp",
            "exif_read_data",
        ];
        $missingFunctions = array_values(array_filter(
            $requiredFunctions,
            static fn(string $function): bool => !function_exists($function)
        ));
        gd_assert(
            "runtime possui GD, WebP e EXIF para fotos",
            $missingFunctions === [],
            $missingFunctions ? "faltando: " . implode(", ", $missingFunctions) : ""
        );
        if ($missingFunctions) {
            return;
        }

        $temporaryRoot = rtrim(sys_get_temp_dir(), "/\\")
            . DIRECTORY_SEPARATOR . "gd-student-photo-selftest-" . bin2hex(random_bytes(8));
        $fixtureRoot = $temporaryRoot . DIRECTORY_SEPARATOR . "fixtures";
        $storageRoot = $temporaryRoot . DIRECTORY_SEPARATOR . "storage";

        try {
            if (!mkdir($fixtureRoot, 0700, true) && !is_dir($fixtureRoot)) {
                throw new RuntimeException("Não foi possível criar diretório temporário.");
            }

            $service = new StudentPhotoService($storageRoot);
            $jpg = $fixtureRoot . DIRECTORY_SEPARATOR . "large.jpg";
            $png = $fixtureRoot . DIRECTORY_SEPARATOR . "small.png";
            $webp = $fixtureRoot . DIRECTORY_SEPARATOR . "source.webp";
            $orientedJpg = $fixtureRoot . DIRECTORY_SEPARATOR . "oriented.jpg";
            gd_student_photo_fixture($jpg, IMAGETYPE_JPEG, 1200, 800);
            gd_student_photo_fixture($png, IMAGETYPE_PNG, 320, 200);
            gd_student_photo_fixture($webp, IMAGETYPE_WEBP, 420, 260);
            gd_student_photo_fixture($orientedJpg, IMAGETYPE_JPEG, 240, 400, 6);

            $noFile = gd_student_photo_upload(
                $fixtureRoot . DIRECTORY_SEPARATOR . "not-created",
                "",
                "application/octet-stream",
                0,
                UPLOAD_ERR_NO_FILE
            );
            gd_assert(
                "[1] cadastro sem foto continua opcional",
                !$service->hasUpload(null) && !$service->hasUpload($noFile)
            );

            $jpgUpload = gd_student_photo_upload($jpg, "foto celular.JPG", "application/octet-stream");
            $jpgMetadata = $service->validate($jpgUpload);
            $jpgPath = $service->store($jpgUpload, 101);
            $jpgAbsolute = $service->absolutePath($jpgPath, 101);
            gd_assert(
                "[2] JPG válido é aceito e armazenado",
                $jpgMetadata["mime"] === "image/jpeg" && is_string($jpgAbsolute) && is_file($jpgAbsolute)
            );

            $pngUpload = gd_student_photo_upload($png, "foto.png", "text/plain");
            $pngMetadata = $service->validate($pngUpload);
            $pngPath = $service->store($pngUpload, 102);
            $pngAbsolute = $service->absolutePath($pngPath, 102);
            gd_assert(
                "[3] PNG válido é aceito pelo MIME real",
                $pngMetadata["mime"] === "image/png"
                    && $pngUpload->getMimeType() === "image/png"
                    && is_string($pngAbsolute)
                    && is_file($pngAbsolute)
            );

            $webpUpload = gd_student_photo_upload($webp, "foto.webp", "image/jpeg");
            $webpMetadata = $service->validate($webpUpload);
            $webpPath = $service->store($webpUpload, 103);
            $webpAbsolute = $service->absolutePath($webpPath, 103);
            gd_assert(
                "[4] WebP válido é aceito pelo MIME real",
                $webpMetadata["mime"] === "image/webp"
                    && $webpUpload->getMimeType() === "image/webp"
                    && is_string($webpAbsolute)
                    && is_file($webpAbsolute)
            );

            $disguised = $fixtureRoot . DIRECTORY_SEPARATOR . "disguised.jpg";
            file_put_contents($disguised, "<?php echo 'não é imagem';");
            $disguisedUpload = gd_student_photo_upload($disguised, "avatar.jpg", "image/jpeg");
            gd_assert(
                "[5] executável disfarçado de JPG é rejeitado",
                gd_throws(
                    static fn() => $service->validate($disguisedUpload),
                    "O arquivo selecionado não é uma imagem JPG, PNG ou WebP válida."
                )
            );

            $oversize = $fixtureRoot . DIRECTORY_SEPARATOR . "oversize.jpg";
            if (!copy($jpg, $oversize)) {
                throw new RuntimeException("Não foi possível copiar fixture grande.");
            }
            $oversizeHandle = fopen($oversize, "c+b");
            if (!$oversizeHandle || !ftruncate($oversizeHandle, StudentPhotoService::MAX_UPLOAD_BYTES + 1)) {
                if (is_resource($oversizeHandle)) {
                    fclose($oversizeHandle);
                }
                throw new RuntimeException("Não foi possível ampliar fixture para 10 MB.");
            }
            fclose($oversizeHandle);
            clearstatcache(true, $oversize);
            $oversizeUpload = gd_student_photo_upload($oversize, "grande.jpg", "image/jpeg");
            gd_assert(
                "[6] arquivo real acima de 10 MB é rejeitado",
                filesize($oversize) === StudentPhotoService::MAX_UPLOAD_BYTES + 1
                    && gd_throws(
                        static fn() => $service->validate($oversizeUpload),
                        "A foto deve ter no máximo 10 MB."
                    )
            );

            $jpgOutputInfo = is_string($jpgAbsolute) ? getimagesize($jpgAbsolute) : false;
            gd_assert(
                "[7] imagem grande é reduzida proporcionalmente para a caixa 500x500",
                is_array($jpgOutputInfo)
                    && (int) $jpgOutputInfo[0] === 500
                    && (int) $jpgOutputInfo[1] === 333
            );

            $pngOutputInfo = is_string($pngAbsolute) ? getimagesize($pngAbsolute) : false;
            gd_assert(
                "[8] imagem menor não sofre upscale",
                is_array($pngOutputInfo)
                    && (int) $pngOutputInfo[0] === 320
                    && (int) $pngOutputInfo[1] === 200
            );

            gd_assert(
                "[9] saída é obrigatoriamente WebP",
                is_array($jpgOutputInfo)
                    && (int) $jpgOutputInfo[2] === IMAGETYPE_WEBP
                    && str_ends_with($jpgPath, ".webp")
                    && is_array($pngOutputInfo)
                    && (int) $pngOutputInfo[2] === IMAGETYPE_WEBP
            );

            $replacementPath = $service->store(gd_student_photo_upload($webp, "nova.png"), 101);
            $replacementAbsolute = $service->absolutePath($replacementPath, 101);
            $oldStillExistsBeforeRemoval = is_string($jpgAbsolute) && is_file($jpgAbsolute);
            $oldRemoved = $service->remove($jpgPath, 101);
            gd_assert(
                "[10] substituição publica nome novo antes de apagar a foto anterior",
                $replacementPath !== $jpgPath
                    && $oldStillExistsBeforeRemoval
                    && $oldRemoved
                    && is_string($replacementAbsolute)
                    && is_file($replacementAbsolute)
                    && !is_file((string) $jpgAbsolute)
            );

            $replacementRemoved = $service->remove($replacementPath, 101);
            gd_assert(
                "[11] remoção apaga o arquivo e invalida seu caminho",
                $replacementRemoved
                    && !is_file((string) $replacementAbsolute)
                    && $service->absolutePath($replacementPath, 101) === null
            );

            $preservedPath = $service->store(gd_student_photo_upload($png, "anterior.png"), 112);
            $preservedAbsolute = $service->absolutePath($preservedPath, 112);
            $processingFailed = false;
            try {
                $service->store($disguisedUpload, 112);
            } catch (DomainException) {
                $processingFailed = true;
            }
            $controllerSource = (string) file_get_contents(__DIR__ . "/../Operacional/Controllers/Bombeiros.php");
            $saveAlunoSource = strstr($controllerSource, "public function save_aluno", false) ?: "";
            $saveAlunoSource = strstr($saveAlunoSource, "public function save_responsavel", true) ?: $saveAlunoSource;
            $storePosition = strpos($saveAlunoSource, "studentPhotoService->store");
            $commitPosition = strpos($saveAlunoSource, '$db->transComplete();');
            $oldRemovalPosition = strpos($saveAlunoSource, 'if ($foto_alterada && $foto_anterior)');
            $rowLockPosition = strpos($saveAlunoSource, 'FOR UPDATE');
            gd_assert(
                "[12] falha preserva foto anterior, troca é serializada e remoção só ocorre após commit",
                $processingFailed
                    && is_string($preservedAbsolute)
                    && is_file($preservedAbsolute)
                    && $service->absolutePath($preservedPath, 112) === $preservedAbsolute
                    && is_int($storePosition)
                    && is_int($commitPosition)
                    && is_int($oldRemovalPosition)
                    && is_int($rowLockPosition)
                    && str_contains($saveAlunoSource, '$dados_foto = ["photo_path"')
                    && str_contains($saveAlunoSource, 'ci_save($dados_foto, $save_id)')
                    && $rowLockPosition < $storePosition
                    && $storePosition < $commitPosition
                    && $commitPosition < $oldRemovalPosition
            );

            $unsafeNameUpload = gd_student_photo_upload($png, "../../shell.php", "application/x-php");
            $safePath = $service->store($unsafeNameUpload, 113);
            $safeAbsolute = $service->absolutePath($safePath, 113);
            gd_assert(
                "[13] nome e caminho são seguros, privados e vinculados ao aluno",
                (bool) preg_match(
                    '#^uploads/grupo_donato/alunos/113/profile-[a-f0-9]{32}\\.webp$#D',
                    $safePath
                )
                    && !str_contains($safePath, "shell")
                    && !str_contains($safePath, "..")
                    && is_string($safeAbsolute)
                    && ((fileperms($safeAbsolute) & 0777) === 0640)
                    && $service->absolutePath($safePath, 114) === null
                    && $service->absolutePath("../../etc/passwd", 113) === null
            );

            $viewSource = (string) file_get_contents(__DIR__ . "/../Operacional/Views/modal_aluno.php");
            gd_assert(
                "[14] aluno sem foto usa avatar padrão com fallback estático",
                str_contains($viewSource, '$student_photo_default_url = get_avatar();')
                    && str_contains($viewSource, ': $student_photo_default_url;')
                    && str_contains($viewSource, 'loading="lazy"')
                    && str_contains($viewSource, 'onerror="this.onerror=null;this.src=')
            );

            $managePermission = strpos(
                $saveAlunoSource,
                '_usuario_tem_acesso_unidade($this->_active_unit_id(), "can_manage_students")'
            );
            $uploadRead = strpos($saveAlunoSource, 'getFile("student_photo")');
            gd_assert(
                "[15] alteração e leitura da foto exigem permissão no backend",
                is_int($managePermission)
                    && is_int($uploadRead)
                    && $managePermission < $uploadRead
                    && str_contains($controllerSource, '"can_view_students"')
                    && str_contains($controllerSource, '"can_manage_student_photo"')
                    && str_contains($viewSource, 'if ($can_manage_student_photo)')
            );

            $orientation = exif_read_data($orientedJpg);
            $orientedPath = $service->store(gd_student_photo_upload($orientedJpg, "celular.jpg"), 116);
            $orientedAbsolute = $service->absolutePath($orientedPath, 116);
            $orientedInfo = is_string($orientedAbsolute) ? getimagesize($orientedAbsolute) : false;
            gd_assert(
                "EXIF Orientation 6 rotaciona a saída",
                (int) ($orientation["Orientation"] ?? 0) === 6
                    && is_array($orientedInfo)
                    && (int) $orientedInfo[0] === 400
                    && (int) $orientedInfo[1] === 240
            );

            $migrationSource = (string) file_get_contents(
                __DIR__ . "/../Database/Schema/Versions/V050_add_operational_student_photo_path.php"
            );
            gd_assert(
                "V050 adiciona photo_path na tabela real grupo_donato_alunos",
                str_contains($migrationSource, 'return "050";')
                    && str_contains($migrationSource, '$prefix . "grupo_donato_alunos"')
                    && str_contains($migrationSource, '"photo_path"')
            );

            $storedFiles = [];
            if (is_dir($storageRoot)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($storageRoot, FilesystemIterator::SKIP_DOTS)
                );
                foreach ($iterator as $storedFile) {
                    if ($storedFile->isFile()) {
                        $storedFiles[] = $storedFile->getPathname();
                    }
                }
            }
            gd_assert(
                "storage contém somente versões WebP finais, sem temporários ou originais",
                $storedFiles !== [] && !array_filter(
                    $storedFiles,
                    static fn(string $path): bool => !preg_match('/profile-[a-f0-9]{32}\\.webp$/D', $path)
                )
            );
        } catch (Throwable $e) {
            gd_assert(
                "self-test de foto conclui sem exceção inesperada",
                false,
                get_class($e) . ": " . $e->getMessage()
            );
        } finally {
            gd_student_photo_remove_tree($temporaryRoot);
            gd_assert(
                "self-test remove todas as fixtures e fotos temporárias",
                !file_exists($temporaryRoot),
                $temporaryRoot
            );
        }
    }
}
