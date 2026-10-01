<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/functions.php';

$ascending = array_fill(0, 8, range(0, 8));
$descending = array_fill(0, 8, range(8, 0));
$allZeroes = image_hash_from_grayscale($ascending);
$allOnes = image_hash_from_grayscale($descending);

$checks = [
    'ascending pixels produce a 64-bit hash' => $allZeroes === str_repeat('0', 64),
    'descending pixels produce a 64-bit hash' => $allOnes === str_repeat('1', 64),
    'identical hashes have distance zero' => image_hash_distance($allZeroes, $allZeroes) === 0,
    'opposite hashes have distance 64' => image_hash_distance($allZeroes, $allOnes) === 64,
    'malformed hashes are rejected' => image_hash_distance('invalid', $allZeroes) === 999,
    'invalid pixel dimensions are rejected' => image_hash_from_grayscale([]) === null,
];

if (function_exists('imagecreatefromstring')) {
    $imagePaths = [];
    $generatedHashes = [];
    foreach ([1, -1] as $direction) {
        $path = tempnam(sys_get_temp_dir(), 'dhash_');
        if ($path === false) {
            $checks['temporary image files can be created'] = false;
            break;
        }
        $imagePaths[] = $path;

        $image = imagecreatetruecolor(90, 80);
        for ($y = 0; $y < 80; $y++) {
            for ($x = 0; $x < 90; $x++) {
                $gray = $direction === 1 ? $x * 255 / 89 : (89 - $x) * 255 / 89;
                $color = imagecolorallocate($image, (int)$gray, (int)$gray, (int)$gray);
                imagesetpixel($image, $x, $y, $color);
            }
        }
        imagepng($image, $path);
        imagedestroy($image);
        $generatedHashes[] = make_image_hash($path);
    }

    if (count($generatedHashes) === 2) {
        $checks['real image data produces a 64-bit dHash'] =
            is_string($generatedHashes[0]) && strlen($generatedHashes[0]) === 64;
        $checks['visually identical image input has distance zero'] =
            image_hash_distance($generatedHashes[0], $generatedHashes[0]) === 0;
        $checks['opposite gradients have distance 64'] =
            image_hash_distance($generatedHashes[0], $generatedHashes[1]) === 64;
    }

    foreach ($imagePaths as $path) {
        unlink($path);
    }
} else {
    echo "SKIP: GD integration checks (PHP GD extension is disabled)" . PHP_EOL;
}

$failed = false;
foreach ($checks as $description => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . ': ' . $description . PHP_EOL;
    $failed = $failed || !$passed;
}

exit($failed ? 1 : 0);
