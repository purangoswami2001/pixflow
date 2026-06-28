<?php

$source = $argv[1] ?? '';
$dest = $argv[2] ?? '';
$size = (int) ($argv[3] ?? 100);

if ($source === '' || $dest === '' || !is_file($source)) {
    fwrite(STDERR, "Usage: php resize-logo.php <source> <dest> [size]\n");
    exit(1);
}

$src = imagecreatefrompng($source);
$w = imagesx($src);
$h = imagesy($src);
$out = imagecreatetruecolor($size, $size);
imagealphablending($out, false);
imagesavealpha($out, true);
$transparent = imagecolorallocatealpha($out, 0, 0, 0, 127);
imagefilledrectangle($out, 0, 0, $size, $size, $transparent);
imagecopyresampled($out, $src, 0, 0, 0, 0, $size, $size, $w, $h);
imagepng($out, $dest);
imagedestroy($src);
imagedestroy($out);

echo "Saved {$size}x{$size} to {$dest}\n";
