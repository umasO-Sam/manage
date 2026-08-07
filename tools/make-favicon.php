<?php
/**
 * ロゴ画像から public/ のファビコン一式を作り直す。
 *
 *   php tools/make-favicon.php sample/saitokokenlogo2.png public
 *
 * 作り直したら resources/views/partials/favicon.blade.php の ?v= を上げること。
 * 上げないと、ブラウザもサーバーも古い画像を返し続ける。
 *
 * この環境にはImageMagickが無いためGDで処理する。GDのimagecopyresampledは
 * バイリニアのため、598px→16pxのような大幅な縮小で線が飛ぶ。ロゴは細い線と
 * 抜き文字でできているので、面積平均(box filter)で縮小している。アルファは
 * 乗算済みで平均し、最後に戻す(半透明の縁が黒ずむのを防ぐ)。
 */

$srcPath = $argv[1] ?? 'sample/saitokokenlogo2.png';
$outDir = $argv[2] ?? 'public';

$im = imagecreatefrompng($srcPath);
if (! $im) {
    fwrite(STDERR, "読み込めません: $srcPath\n");
    exit(1);
}
imagealphablending($im, false);
imagesavealpha($im, true);

$sw = imagesx($im);
$sh = imagesy($im);

// --- ロゴの外接矩形を求める(周囲の透明・白を落とす) ---
$x0 = $sw; $y0 = $sh; $x1 = -1; $y1 = -1;
for ($y = 0; $y < $sh; $y++) {
    for ($x = 0; $x < $sw; $x++) {
        $c = imagecolorat($im, $x, $y);
        $a = ($c >> 24) & 0x7F;
        $r = ($c >> 16) & 0xFF; $g = ($c >> 8) & 0xFF; $b = $c & 0xFF;
        $lum = 0.299 * $r + 0.587 * $g + 0.114 * $b;
        if ($a < 64 && $lum < 128) {
            if ($x < $x0) $x0 = $x;
            if ($x > $x1) $x1 = $x;
            if ($y < $y0) $y0 = $y;
            if ($y > $y1) $y1 = $y;
        }
    }
}
$bw = $x1 - $x0 + 1;
$bh = $y1 - $y0 + 1;
echo "外接矩形: {$bw}x{$bh} at ({$x0},{$y0})\n";

// --- 外接矩形を正方形の作業キャンバスに載せる(長辺基準・中央) ---
$side = max($bw, $bh);
$work = imagecreatetruecolor($side, $side);
imagealphablending($work, false);
imagesavealpha($work, true);
imagefilledrectangle($work, 0, 0, $side - 1, $side - 1, imagecolorallocatealpha($work, 255, 255, 255, 127));
imagecopy($work, $im, intdiv($side - $bw, 2), intdiv($side - $bh, 2), $x0, $y0, $bw, $bh);

// 作業キャンバスを乗算済みRGBAの配列にする。
$px = [];
for ($y = 0; $y < $side; $y++) {
    $row = [];
    for ($x = 0; $x < $side; $x++) {
        $c = imagecolorat($work, $x, $y);
        $a = 1.0 - ((($c >> 24) & 0x7F) / 127.0);   // 0=透明, 1=不透明
        $row[] = [(($c >> 16) & 0xFF) * $a, (($c >> 8) & 0xFF) * $a, ($c & 0xFF) * $a, $a];
    }
    $px[] = $row;
}

/**
 * 作業キャンバスを、余白 $pad(割合) を付けて $size 四方に面積平均で縮小する。
 * $bg が null なら透明背景、配列なら不透明な背景色の上に合成する。
 */
function render(array $px, int $side, int $size, float $pad, ?array $bg, float $contrast = 1.0): \GdImage
{
    $out = imagecreatetruecolor($size, $size);
    imagealphablending($out, false);
    imagesavealpha($out, true);

    $logo = (int) round($size * (1.0 - 2 * $pad));   // ロゴが占める画素数
    $off = intdiv($size - $logo, 2);
    $scale = $side / $logo;                          // 出力1px あたりの元画素数

    for ($oy = 0; $oy < $size; $oy++) {
        for ($ox = 0; $ox < $size; $ox++) {
            $r = $g = $b = $a = 0.0;

            $lx = $ox - $off;
            $ly = $oy - $off;
            if ($lx >= 0 && $lx < $logo && $ly >= 0 && $ly < $logo) {
                // この出力画素が覆う元画像の範囲を、端数を重みにして平均する。
                $fx0 = $lx * $scale; $fx1 = ($lx + 1) * $scale;
                $fy0 = $ly * $scale; $fy1 = ($ly + 1) * $scale;
                $total = 0.0;

                for ($sy = (int) floor($fy0); $sy < min((int) ceil($fy1), $side); $sy++) {
                    $wy = min($fy1, $sy + 1) - max($fy0, $sy);
                    if ($wy <= 0) continue;
                    for ($sx = (int) floor($fx0); $sx < min((int) ceil($fx1), $side); $sx++) {
                        $wx = min($fx1, $sx + 1) - max($fx0, $sx);
                        if ($wx <= 0) continue;
                        $w = $wx * $wy;
                        $p = $px[$sy][$sx];
                        $r += $p[0] * $w; $g += $p[1] * $w; $b += $p[2] * $w; $a += $p[3] * $w;
                        $total += $w;
                    }
                }
                if ($total > 0) { $r /= $total; $g /= $total; $b /= $total; $a /= $total; }

                // 16px級では平均の結果が中間グレーばかりになり、輪郭がぼやける。
                // 不透明度だけをS字に寄せて、線を締める。
                if ($contrast > 1.0 && $a > 0 && $a < 1) {
                    $adj = max(0.0, min(1.0, ($a - 0.5) * $contrast + 0.5));
                    if ($a > 0.0001) { $r *= $adj / $a; $g *= $adj / $a; $b *= $adj / $a; }
                    $a = $adj;
                }
            }

            if ($bg !== null) {
                // 不透明な背景の上に合成(iOSは透明部分を黒で埋めるため)。
                $r = $r + $bg[0] * (1 - $a);
                $g = $g + $bg[1] * (1 - $a);
                $b = $b + $bg[2] * (1 - $a);
                $alpha = 0;
            } else {
                // 乗算済みを戻す。
                if ($a > 0.0001) { $r /= $a; $g /= $a; $b /= $a; }
                $alpha = (int) round((1 - $a) * 127);
            }

            $col = imagecolorallocatealpha(
                $out,
                max(0, min(255, (int) round($r))),
                max(0, min(255, (int) round($g))),
                max(0, min(255, (int) round($b))),
                max(0, min(127, $alpha))
            );
            imagesetpixel($out, $ox, $oy, $col);
        }
    }

    return $out;
}

function pngBytes(\GdImage $img): string
{
    ob_start();
    imagepng($img, null, 9);

    return ob_get_clean();
}

// --- 透明背景のファビコン。小さいほど余白を詰めて字面を稼ぐ ---
$targets = [
    'favicon-32.png' => [32, 0.02],
    'favicon-192.png' => [192, 0.04],
    'favicon-512.png' => [512, 0.04],
];
foreach ($targets as $name => [$size, $pad]) {
    $img = render($px, $side, $size, $pad, null);
    file_put_contents("$outDir/$name", pngBytes($img));
    echo "$name ({$size}x{$size}, 余白" . round($pad * 100) . "%)\n";
}

// --- apple-touch-icon は白背景。iOSが角を丸めるぶん余白を多めに取る ---
$img = render($px, $side, 180, 0.10, [255, 255, 255]);
file_put_contents("$outDir/apple-touch-icon.png", pngBytes($img));
echo "apple-touch-icon.png (180x180, 白背景, 余白10%)\n";

// --- favicon.ico は 16/32/48 のPNGを収めた形式(既存と同じ) ---
// 16pxだけは、そのまま平均すると全体が中間グレーに沈んで外形が読めないので締める。
$icoSizes = [16 => 1.8, 32 => 1.0, 48 => 1.0];
$entries = [];
foreach ($icoSizes as $size => $contrast) {
    $entries[$size] = pngBytes(render($px, $side, $size, 0.02, null, $contrast));
}

$count = count($entries);
$ico = pack('vvv', 0, 1, $count);
$offset = 6 + 16 * $count;
foreach ($entries as $size => $data) {
    $ico .= pack('CCCCvvVV', $size, $size, 0, 0, 1, 32, strlen($data), $offset);
    $offset += strlen($data);
}
foreach ($entries as $data) {
    $ico .= $data;
}
file_put_contents("$outDir/favicon.ico", $ico);
echo 'favicon.ico (' . implode('/', array_keys($icoSizes)) . ", PNG埋め込み)\n";
