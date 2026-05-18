<?php
/**
 * Inline listing walk-around video player.
 * @var array<string,mixed> $listing Row with video_path, video_mime, video_size_bytes
 */
$vp = trim((string)($listing['video_path'] ?? ''));
if ($vp === '') {
  return;
}
$vm = (string)($listing['video_mime'] ?? 'video/mp4');
$vsize = listing_video_human_size(isset($listing['video_size_bytes']) ? (int)$listing['video_size_bytes'] : null);
?>
<div class="card reveal listing-video" style="margin-bottom:1rem;overflow:hidden">
  <video controls preload="metadata" playsinline style="width:100%;display:block;background:#000">
    <source src="<?= APP_BASE_URL ?>/<?= h(public_file($vp)) ?>" type="<?= h($vm) ?>">
    Your browser does not support inline video. <a href="<?= APP_BASE_URL ?>/<?= h(public_file($vp)) ?>">Download the video</a>.
  </video>
  <div class="pad" style="padding:.85rem 1rem;display:flex;align-items:center;gap:.6rem;flex-wrap:wrap">
    <span class="pill ok">Walk-around video</span>
    <?php if ($vsize !== ''): ?>
      <span class="sub" style="margin:0;font-size:.9rem"><?= h($vsize) ?> · Review before approving.</span>
    <?php else: ?>
      <span class="sub" style="margin:0;font-size:.9rem">Review this clip before approving the listing.</span>
    <?php endif; ?>
  </div>
</div>
