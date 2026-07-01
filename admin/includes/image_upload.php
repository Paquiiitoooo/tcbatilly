<?php

function delete_local_upload(?string $path, string $base_url): void
{
    if (!$path || strpos($path, $base_url) !== 0) {
        return;
    }

    $file_path = rtrim($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 2), DIRECTORY_SEPARATOR) . $path;
    if (is_file($file_path)) {
        @unlink($file_path);
    }
}

function save_cropped_image(?string $data_url, string $folder, string $prefix, ?string $existing_path = null): ?string
{
    if (!$data_url || trim($data_url) === '') {
        return $existing_path;
    }

    if (!preg_match('#^data:image/(png|jpe?g|webp);base64,#i', $data_url, $matches)) {
        throw new RuntimeException('Image invalide. Merci de recadrer le fichier avant enregistrement.');
    }

    $extension = strtolower($matches[1]);
    $extension = $extension === 'jpeg' ? 'jpg' : $extension;
    if (!in_array($extension, ALLOWED_EXTENSIONS, true)) {
        throw new RuntimeException('Format non autorise. Formats acceptes: ' . implode(', ', ALLOWED_EXTENSIONS));
    }

    $payload = substr($data_url, strpos($data_url, ',') + 1);
    $binary = base64_decode($payload, true);
    if ($binary === false || strlen($binary) === 0) {
        throw new RuntimeException('Impossible de lire l image recadree.');
    }

    if (strlen($binary) > MAX_UPLOAD_SIZE) {
        throw new RuntimeException('Le fichier recadre est trop volumineux.');
    }

    $info = @getimagesizefromstring($binary);
    if ($info === false) {
        throw new RuntimeException('Le fichier recadre doit etre une image valide.');
    }

    $safe_folder = trim($folder, '/\\');
    $upload_dir = rtrim(UPLOAD_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safe_folder . DIRECTORY_SEPARATOR;
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $target_name = uniqid($prefix . '_', true) . '.' . $extension;
    $target_path = $upload_dir . $target_name;
    if (file_put_contents($target_path, $binary) === false) {
        throw new RuntimeException('Impossible d enregistrer l image recadree.');
    }

    $base_url = '/assets/uploads/' . str_replace('\\', '/', $safe_folder) . '/';
    delete_local_upload($existing_path, $base_url);

    return $base_url . $target_name;
}
