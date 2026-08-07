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
 * バイリニアのため、720px→16pxのような大幅な縮小で線が飛ぶ。ロゴは細い線と
 * 抜き文字でできているので、面積平均(box filter)で縮小している。アルファは
 * 乗算済みで平均し、最後に戻す(半透明の縁が黒ずむのを防ぐ)。
 *
 * 元画像の白い縁取りは26pxしかなく、16pxまで縮めると0.6px相当にしかならない。
 * 暗い背景での視認性はこの縁取りが担っているので、縮小する前に外側へ
 * 太らせる(DILATE)。
 */

// 元画像を1画素ずつPHPの配列に持つため、既定の128Mでは足りない。
ini_set('memory_limit', '1024M');

// 白い縁取りを外側へ太らせる量(元画像の画素)。0で元のまま。
const DILATE = 45;

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

// 元画像を [r,g,b,不透明度] の配列にする。
$src = [];
$opaque = [];
for ($y = 0; $y < $sh; $y++) {
    $rowS = []; $rowO = [];
    for ($x = 0; $x < $sw; $x++) {
        $c = imagecolorat($im, $x, $y);
        $a = 1.0 - ((($c >> 24) & 0x7F) / 127.0);   // 0=透明, 1=不透明
        $rowS[] = [($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF, $a];
        $rowO[] = $a >= 0.5;
    }
    $src[] = $rowS;
    $opaque[] = $rowO;
}

// --- 白い縁取りを外側へ太らせる ---
if (DILATE > 0) {
    $dist = distanceFromOpaque($opaque, $sw, $sh);
    $limit = DILATE * 3;                            // 距離は3倍スケール
    for ($y = 0; $y < $sh; $y++) {
        for ($x = 0; $x < $sw; $x++) {
            if ($opaque[$y][$x]) continue;
            $dv = $dist[$y][$x];
            if ($dv > $limit + 3) continue;
            // 外周1pxぶんをフェードさせ、輪郭のギザギザを防ぐ。
            $a = $dv <= $limit ? 1.0 : max(0.0, 1.0 - ($dv - $limit) / 3.0);
            $src[$y][$x] = [255, 255, 255, max($src[$y][$x][3], $a)];
        }
    }
}

// --- ロゴの外接矩形を求める ---
// 黒い画素ではなく「透明でない画素」で測る。このロゴは黒い図形の外周を
// 白いアウトラインが縁取っており、その白は透明ではなく不透明な白。
// 黒だけで測ると白い縁取りを丸ごと切り落としてしまい、暗い背景に置いたときに
// ロゴが背景へ溶けて見えなくなる(縁取りは暗い背景での視認性そのもの)。
$x0 = $sw; $y0 = $sh; $x1 = -1; $y1 = -1;
for ($y = 0; $y < $sh; $y++) {
    for ($x = 0; $x < $sw; $x++) {
        if ($src[$y][$x][3] > 0.25) {
            if ($x < $x0) $x0 = $x;
            if ($x > $x1) $x1 = $x;
            if ($y < $y0) $y0 = $y;
            if ($y > $y1) $y1 = $y;
        }
    }
}
$bw = $x1 - $x0 + 1;
$bh = $y1 - $y0 + 1;
echo '外接矩形: ' . $bw . 'x' . $bh . ' at (' . $x0 . ',' . $y0 . ')' . (DILATE > 0 ? '  ※縁取りを' . DILATE . "px太らせた後\n" : "\n");

// --- 外接矩形を正方形の作業キャンバスに載せる(長辺基準・中央)。値は乗算済み ---
$side = max($bw, $bh);
$ox = intdiv($side - $bw, 2);
$oy = intdiv($side - $bh, 2);
$px = [];
for ($y = 0; $y < $side; $y++) {
    $row = [];
    for ($x = 0; $x < $side; $x++) {
        $sx = $x - $ox + $x0;
        $sy = $y - $oy + $y0;
        if ($sx < $x0 || $sx > $x1 || $sy < $y0 || $sy > $y1) {
            $row[] = [0.0, 0.0, 0.0, 0.0];
        } else {
            [$r, $g, $b, $a] = $src[$sy][$sx];
            $row[] = [$r * $a, $g * $a, $b * $a, $a];
        }
    }
    $px[] = $row;
}

/**
 * 作業キャンバスを、余白 $pad(割合) を付けて $size 四方に面積平均で縮小する。
 * $bg が null なら透明背景、配列なら不透明な背景色の上に合成する。
 */
/**
 * 各画素から最寄りの不透明画素までの距離を、チャンファー距離変換(3-4近似)で求める。
 * 総当たりだと画素数×半径の二乗で効かないので、前方・後方の2パスで近似する。
 * 戻り値の単位は3倍スケール(1px = 3)。
 */
function distanceFromOpaque(array $opaque, int $w, int $h): array
{
    $INF = 1 << 20;
    $d = [];
    for ($y = 0; $y < $h; $y++) {
        $row = [];
        for ($x = 0; $x < $w; $x++) {
            $row[] = $opaque[$y][$x] ? 0 : $INF;
        }
        $d[] = $row;
    }

    for ($y = 0; $y < $h; $y++) {            // 左上 → 右下
        for ($x = 0; $x < $w; $x++) {
            if ($d[$y][$x] === 0) continue;
            $m = $d[$y][$x];
            if ($x > 0) $m = min($m, $d[$y][$x - 1] + 3);
            if ($y > 0) $m = min($m, $d[$y - 1][$x] + 3);
            if ($x > 0 && $y > 0) $m = min($m, $d[$y - 1][$x - 1] + 4);
            if ($x < $w - 1 && $y > 0) $m = min($m, $d[$y - 1][$x + 1] + 4);
            $d[$y][$x] = $m;
        }
    }
    for ($y = $h - 1; $y >= 0; $y--) {       // 右下 → 左上
        for ($x = $w - 1; $x >= 0; $x--) {
            if ($d[$y][$x] === 0) continue;
            $m = $d[$y][$x];
            if ($x < $w - 1) $m = min($m, $d[$y][$x + 1] + 3);
            if ($y < $h - 1) $m = min($m, $d[$y + 1][$x] + 3);
            if ($x < $w - 1 && $y < $h - 1) $m = min($m, $d[$y + 1][$x + 1] + 4);
            if ($x > 0 && $y < $h - 1) $m = min($m, $d[$y + 1][$x - 1] + 4);
            $d[$y][$x] = $m;
        }
    }

    return $d;
}

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

// --- 透明背景のファビコン。余白は取らず、白い縁取りまで最大限に写す ---
$targets = [
    'favicon-32.png' => 32,
    'favicon-192.png' => 192,
    'favicon-512.png' => 512,
];
foreach ($targets as $name => $size) {
    $img = render($px, $side, $size, 0.0, null);
    file_put_contents("$outDir/$name", pngBytes($img));
    echo "$name ({$size}x{$size})\n";
}

// --- apple-touch-icon は白背景(iOSは透明を黒で埋める)。角を丸められるので少しだけ余白を残す ---
$img = render($px, $side, 180, 0.04, [255, 255, 255]);
file_put_contents("$outDir/apple-touch-icon.png", pngBytes($img));
echo "apple-touch-icon.png (180x180, 白背景)\n";

// --- favicon.ico は 16/32/48 のPNGを収めた形式(既存と同じ) ---
$icoSizes = [16, 32, 48];
$entries = [];
foreach ($icoSizes as $size) {
    $entries[$size] = pngBytes(render($px, $side, $size, 0.0, null));
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
echo 'favicon.ico (' . implode('/', $icoSizes) . ", PNG埋め込み)\n";
