<?php

/**
 * Storing an uploaded image.
 *
 * The cover photo and every sub image go through here, so the checks are
 * written once:
 *
 *   - the file really IS an image — the type is sniffed from the file's own
 *     contents, never trusted from the name the browser sent (a file called
 *     "photo.jpg" can easily be a script);
 *   - it is a type we can display (JPG, PNG, WebP, GIF);
 *   - it fits the size limit;
 *   - it is renamed to something random, so a name like "../../config.php"
 *     cannot escape the upload folder or overwrite another file.
 */

/** The largest single image we accept: 2MB. */
const NEWS_IMAGE_MAX_BYTES = 2 * 1024 * 1024;

/** How many sub images one news item may have. */
const NEWS_GALLERY_MAX_FILES = 8;

/** Where uploads live, relative to the public folder. */
const NEWS_UPLOAD_DIR = 'uploads/news';

/**
 * File types we accept, as the browser is allowed to offer them, mapped to the
 * extension we save them with.
 */
function newsAllowedImageTypes(): array
{
    return [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
}

/**
 * Store one uploaded image and return its stored path.
 *
 * @param array $file one entry from $_FILES (not the whole array).
 * @return array{0:?string,1:?string} [stored path, error message]. Exactly one
 *                                    of the two is set.
 */
function newsStoreImage(array $file): array
{
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }

    if ($errorCode !== UPLOAD_ERR_OK) {
        return [null, newsUploadErrorMessage($errorCode)];
    }

    // A file that is not really an uploaded file (a path someone typed in, or a
    // second request trying to read another request's file) is refused. This is
    // the check that stops PHP being tricked into moving /etc/passwd.
    if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
        return [null, 'That file could not be uploaded. Please try again.'];
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        return [null, 'That file is empty.'];
    }
    if ($size > NEWS_IMAGE_MAX_BYTES) {
        return [null, 'Image too large. Maximum ' . newsFormatBytes(NEWS_IMAGE_MAX_BYTES) . ' per image.'];
    }

    // Sniffed from the contents, not from the filename.
    $mimeType = '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = (string) $finfo->file((string) $file['tmp_name']);
    } elseif (function_exists('getimagesize')) {
        $info = @getimagesize((string) $file['tmp_name']);
        $mimeType = (string) ($info['mime'] ?? '');
    }

    $allowed = newsAllowedImageTypes();
    if (!isset($allowed[$mimeType])) {
        return [null, 'Invalid image format. Only JPG, PNG, WebP and GIF are allowed.'];
    }

    $directory = dirname(__DIR__) . '/' . NEWS_UPLOAD_DIR . '/';
    if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
        return [null, 'The upload folder could not be created. Check the folder permissions.'];
    }

    // A name of our own: nothing the visitor typed is reused, so the file can
    // never overwrite another one or point somewhere outside this folder.
    $filename = 'news_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mimeType];
    $target = $directory . $filename;

    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
        return [null, 'The image could not be saved. Check the upload folder permissions.'];
    }

    @chmod($target, 0644);

    return [NEWS_UPLOAD_DIR . '/' . $filename, null];
}

/**
 * Turn PHP's upload error number into something an admin can act on.
 */
function newsUploadErrorMessage(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            // The file was stopped by the SERVER's own limit, which is smaller
            // than ours. Saying "too large" on its own would be confusing,
            // because it may well be under our 2MB rule.
            return 'That image was too large for the server to accept. Try a smaller one '
                . '(or ask your host to raise upload_max_filesize and post_max_size).';
        case UPLOAD_ERR_PARTIAL:
            return 'The upload was cut off. Please try again.';
        case UPLOAD_ERR_NO_TMP_DIR:
        case UPLOAD_ERR_CANT_WRITE:
            return 'The server could not store the file. This is a hosting problem, not your image.';
        case UPLOAD_ERR_EXTENSION:
            return 'A server setting stopped that upload.';
        default:
            return 'That file could not be uploaded. Please try again.';
    }
}

/**
 * Delete a stored image file.
 *
 * The path always comes from our own database, but it is checked anyway: the
 * resolved file has to sit inside the uploads folder before anything is
 * removed. A stored path that points elsewhere does nothing.
 */
function newsDeleteImageFile(?string $storedPath): void
{
    $storedPath = trim((string) $storedPath);
    if ($storedPath === '') {
        return;
    }

    $uploadsRoot = realpath(dirname(__DIR__) . '/' . NEWS_UPLOAD_DIR);
    if ($uploadsRoot === false) {
        return;
    }

    // Accept both "uploads/news/x.jpg" and "/uploads/news/x.jpg".
    $relative = ltrim($storedPath, '/');
    if (strpos($relative, '..') !== false) {
        return;
    }

    $real = realpath(dirname(__DIR__) . '/' . $relative);
    if ($real === false || !is_file($real)) {
        return;
    }

    if (strpos($real, $uploadsRoot) !== 0) {
        return;
    }

    @unlink($real);
}

/**
 * "2MB" from 2097152, for messages.
 */
function newsFormatBytes(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return rtrim(rtrim(number_format($bytes / (1024 * 1024), 1), '0'), '.') . 'MB';
    }
    if ($bytes >= 1024) {
        return rtrim(rtrim(number_format($bytes / 1024, 1), '0'), '.') . 'KB';
    }
    return $bytes . 'B';
}

/**
 * Rebuild $_FILES['images'] into a plain list of single files.
 *
 * PHP hands a multi-file input back as five parallel arrays (name[0], name[1],
 * ...), which is awkward to loop over. This turns it into the simple list
 * everyone expects.
 *
 * @return array<int,array> one entry per chosen file
 */
function newsCollectUploadedFiles(string $field): array
{
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
        return [];
    }

    $entry = $_FILES[$field];

    // A single file arrives as plain values, not arrays.
    if (!is_array($entry['name'])) {
        return $entry['error'] === UPLOAD_ERR_NO_FILE ? [] : [$entry];
    }

    $files = [];
    foreach (array_keys($entry['name']) as $index) {
        $file = [
            'name'     => $entry['name'][$index] ?? '',
            'type'     => $entry['type'][$index] ?? '',
            'tmp_name' => $entry['tmp_name'][$index] ?? '',
            'error'    => $entry['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size'     => $entry['size'][$index] ?? 0,
        ];

        if ((int) $file['error'] !== UPLOAD_ERR_NO_FILE) {
            $files[] = $file;
        }
    }

    return $files;
}
