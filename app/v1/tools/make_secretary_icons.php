<?php
// Makes the Dazu Secretary app icons (public/assets/icons/secretary-*.png) from the Dazu logo (public/assets/images/logo/logo.png): the green "A" and graduation cap, on white.
//   php app/v1/tools/make_secretary_icons.php          Only writes new files in public/assets/icons/ named secretary-*.png. Needs the GD extension.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 3);
$logo = imagecreatefrompng($root . '/public/assets/images/logo/logo.png');
if (!$logo) { fwrite(STDERR, "Cannot read the logo.\n"); exit(1); }
// the emblem (the A and the cap) is the middle part of the wide logo, above the "TRAINING INSTITUTE" line
[$sx, $sy, $sw, $sh] = [255, 0, 335, 378];
// the crop's edges still touch a sliver of the black D and Z of the wordmark: paint those dark pixels white (the emblem itself is green)
$emb = imagecreatetruecolor($sw, $sh);
imagefill($emb, 0, 0, imagecolorallocate($emb, 255, 255, 255));
imagealphablending($emb, true);
imagecopy($emb, $logo, 0, 0, $sx, $sy, $sw, $sh);
for ($y = 0; $y < $sh; $y++) {
    for ($x = 0; $x < $sw; $x++) {
        if ($x > 48 && $x < $sw - 28) continue;
        $c = imagecolorat($emb, $x, $y); $r = ($c >> 16) & 255; $g = ($c >> 8) & 255; $b = $c & 255;
        if (abs($r - $g) < 28 && abs($g - $b) < 28 && $g < 245) imagesetpixel($emb, $x, $y, 0xFFFFFF);
    }
}
$logo = $emb; [$sx, $sy] = [0, 0];
$make = function (int $size, float $fill, string $name) use ($logo, $root, $sx, $sy, $sw, $sh): void {
    $im = imagecreatetruecolor($size, $size);
    imagealphablending($im, true);
    imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
    $h = (int) round($size * $fill); $w = (int) round($h * $sw / $sh);
    imagecopyresampled($im, $logo, (int) (($size - $w) / 2), (int) (($size - $h) / 2), $sx, $sy, $w, $h, $sw, $sh);
    imagepng($im, $root . '/public/assets/icons/' . $name, 9);
    imagedestroy($im);
    echo "wrote $name\n";
};
foreach ([72, 96, 128, 144, 152, 180, 192, 384, 512] as $s) $make($s, 0.80, "secretary-$s.png");     // "any": fills most of the square
foreach ([192, 512] as $s) $make($s, 0.58, "secretary-maskable-$s.png");                           // "maskable": inside the safe zone (the phone may crop the corners)
