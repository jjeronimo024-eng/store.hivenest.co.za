<?php
declare(strict_types=1);
require_once __DIR__ . '/customer_session.php';

/**
 * Move an uploaded file through private quarantine, signature checks and an
 * optional ClamAV scan before it enters customer-accessible storage.
 */
function hivenest_secure_upload(
    array $file,
    string $destinationDirectory,
    string $relativeDirectory,
    array $allowedExtensions,
    int $maximumBytes
): array {
    $original = basename(str_replace("\0", '', (string)($file['name'] ?? 'upload')));
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    $size = (int)($file['size'] ?? 0);
    $temporary = (string)($file['tmp_name'] ?? '');
    if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($temporary)) {
        return ['original_name' => $original, 'error' => 'Upload failed validation.'];
    }
    if ($size <= 0 || $size > $maximumBytes) {
        return ['original_name' => $original, 'error' => 'File exceeds the permitted size.'];
    }

    $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $allowedExtensions = array_map('strtolower', $allowedExtensions);
    if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
        return ['original_name' => $original, 'error' => 'File type is not allowed.'];
    }
    $dangerous = ['php','phtml','phar','cgi','pl','py','sh','bash','exe','dll','com','bat','cmd','js','html','htm','shtml','asp','aspx','jsp','jar','msi','scr'];
    $segments = array_map('strtolower', explode('.', $original));
    if (array_intersect($dangerous, $segments)) {
        return ['original_name' => $original, 'error' => 'Potentially executable file names are not allowed.'];
    }

    $quarantineRoot = dirname(__DIR__) . '/Backend/quarantine/uploads';
    if (!is_dir($quarantineRoot) && !@mkdir($quarantineRoot, 0700, true) && !is_dir($quarantineRoot)) {
        return ['original_name' => $original, 'error' => 'Upload quarantine is unavailable.'];
    }
    $quarantine = $quarantineRoot . '/' . bin2hex(random_bytes(20)) . '.upload';
    if (!move_uploaded_file($temporary, $quarantine)) {
        return ['original_name' => $original, 'error' => 'Could not quarantine uploaded file.'];
    }

    $reject = static function (string $message) use ($quarantine, $original): array {
        @unlink($quarantine);
        return ['original_name' => $original, 'error' => $message];
    };
    $sample = (string)@file_get_contents($quarantine, false, null, 0, min($size, 1024 * 1024));
    if (preg_match('/<\?(?:php|=)|\b(?:eval|base64_decode|shell_exec|passthru)\s*\(/i', $sample)) {
        return $reject('Executable content was detected.');
    }
    if ($extension === 'svg' && preg_match('/<(?:script|foreignObject)\b|on[a-z]+\s*=|javascript:|<!ENTITY/i', $sample)) {
        return $reject('Unsafe active SVG content was detected.');
    }

    $mime = 'application/octet-stream';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)($finfo->file($quarantine) ?: $mime);
    }
    $mimeRules = [
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
        'gif' => ['image/gif'], 'webp' => ['image/webp'], 'svg' => ['image/svg+xml','text/xml','application/xml'],
        'pdf' => ['application/pdf'], 'txt' => ['text/plain','application/octet-stream'],
        'log' => ['text/plain','application/octet-stream'], 'zip' => ['application/zip','application/x-zip-compressed'],
        'doc' => ['application/msword','application/CDFV2','application/octet-stream'],
        'docx' => ['application/zip','application/x-zip-compressed','application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'psd' => ['image/vnd.adobe.photoshop','application/octet-stream'],
        'ai' => ['application/pdf','application/postscript','application/octet-stream'],
    ];
    if (isset($mimeRules[$extension]) && !in_array($mime, $mimeRules[$extension], true)) {
        return $reject('File content does not match its extension.');
    }

    $env = function_exists('hivenest_customer_session_env') ? hivenest_customer_session_env() : [];
    $scanner = trim((string)($env['CLAMAV_BINARY'] ?? getenv('CLAMAV_BINARY') ?: ''));
    $scanRequired = in_array(strtolower((string)($env['UPLOAD_SCAN_REQUIRED'] ?? 'false')), ['1','true','yes','on'], true);
    $scanStatus = 'static_checks_passed';
    if ($scanner !== '' && function_exists('proc_open')) {
        $pipes = [];
        $process = @proc_open([$scanner, '--no-summary', $quarantine], [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
        if (is_resource($process)) {
            fclose($pipes[0]);
            stream_get_contents($pipes[1]); stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            $scanExit = proc_close($process);
            if ($scanExit === 1) return $reject('Malware was detected in the uploaded file.');
            if ($scanExit !== 0 && $scanRequired) return $reject('Malware scanning is temporarily unavailable.');
            if ($scanExit === 0) $scanStatus = 'clamav_clean';
        } elseif ($scanRequired) {
            return $reject('Malware scanning is temporarily unavailable.');
        }
    } elseif ($scanRequired) {
        return $reject('Malware scanning is not configured.');
    }

    if (!is_dir($destinationDirectory) && !@mkdir($destinationDirectory, 0750, true) && !is_dir($destinationDirectory)) {
        return $reject('Upload storage is unavailable.');
    }
    $stored = bin2hex(random_bytes(16)) . '.' . $extension;
    $destination = rtrim($destinationDirectory, '/\\') . DIRECTORY_SEPARATOR . $stored;
    if (!@rename($quarantine, $destination)) {
        return $reject('Could not release the validated upload.');
    }
    @chmod($destination, 0640);
    return [
        'original_name' => $original,
        'stored_name' => $stored,
        'relative_path' => trim($relativeDirectory, '/\\') . '/' . $stored,
        'size' => $size,
        'extension' => $extension,
        'mime' => $mime,
        'scan_status' => $scanStatus,
    ];
}
