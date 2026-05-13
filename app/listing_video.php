<?php

declare(strict_types=1);

const LISTING_VIDEO_MAX_BYTES = 25 * 1024 * 1024; // 25 MB
const LISTING_VIDEO_ALLOWED_MIMES = [
  'video/mp4'        => 'mp4',
  'video/quicktime'  => 'mov',
  'video/webm'       => 'webm',
  'video/x-m4v'      => 'm4v',
];

function listing_video_dir(): string {
  return __DIR__ . '/../public/uploads/videos';
}

function listing_video_ensure_dir(): void {
  $dir = listing_video_dir();
  if (!is_dir($dir)) {
    @mkdir($dir, 0777, true);
  }
}

/**
 * @param array{name:string,type:string,tmp_name:string,error:int,size:int} $file
 * @return array{ok:bool,err?:string,rel_path?:string,mime?:string,size?:int}
 */
function listing_video_validate_and_save(int $listingId, array $file): array {
  $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
  if ($err === UPLOAD_ERR_NO_FILE) {
    return ['ok' => false, 'err' => 'No video file selected.'];
  }
  if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
    return ['ok' => false, 'err' => 'Video is larger than the server upload limit.'];
  }
  if ($err !== UPLOAD_ERR_OK) {
    return ['ok' => false, 'err' => 'Video upload failed (error ' . $err . ').'];
  }
  $tmp = (string)($file['tmp_name'] ?? '');
  $size = (int)($file['size'] ?? 0);
  $mime = (string)($file['type'] ?? '');
  if (!is_uploaded_file($tmp)) {
    return ['ok' => false, 'err' => 'Invalid upload.'];
  }
  if ($size <= 0) {
    return ['ok' => false, 'err' => 'Video appears empty.'];
  }
  if ($size > LISTING_VIDEO_MAX_BYTES) {
    return ['ok' => false, 'err' => 'Video must be 25 MB or smaller (about 1 minute at 720p).'];
  }

  $ext = LISTING_VIDEO_ALLOWED_MIMES[$mime] ?? null;
  if ($ext === null && function_exists('mime_content_type')) {
    $detected = (string)@mime_content_type($tmp);
    if ($detected !== '') {
      $mime = $detected;
      $ext = LISTING_VIDEO_ALLOWED_MIMES[$mime] ?? null;
    }
  }
  if ($ext === null) {
    return ['ok' => false, 'err' => 'Only MP4, MOV or WEBM video formats are allowed.'];
  }

  listing_video_ensure_dir();
  $name = 'lv' . $listingId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
  $dest = listing_video_dir() . '/' . $name;
  if (!@move_uploaded_file($tmp, $dest)) {
    return ['ok' => false, 'err' => 'Could not store the video on the server.'];
  }
  return [
    'ok' => true,
    'rel_path' => 'uploads/videos/' . $name,
    'mime' => $mime,
    'size' => $size,
  ];
}

function listing_video_delete(?string $relPath): void {
  if ($relPath === null || $relPath === '') return;
  $abs = __DIR__ . '/../public/' . ltrim($relPath, '/');
  if (is_file($abs)) {
    @unlink($abs);
  }
}

function listing_video_human_size(?int $bytes): string {
  if ($bytes === null || $bytes <= 0) return '';
  if ($bytes < 1024 * 1024) return number_format($bytes / 1024, 0) . ' KB';
  return number_format($bytes / (1024 * 1024), 1) . ' MB';
}
