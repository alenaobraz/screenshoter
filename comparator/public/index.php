<?php

ini_set('display_errors', '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

file_put_contents('php://stderr', "DEBUG: Request received\n", FILE_APPEND);

if (ob_get_level()) {
    ob_clean();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-Api-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$headers = array_change_key_case(getallheaders(), CASE_LOWER);
$apiKey = $headers['x-api-key'] ?? null;

if ($apiKey !== ($_ENV['DIFF_SERVICE_API_KEY'] ?? '')) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid API key']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$image1Url = $input['image1'] ?? null;
$image2Url = $input['image2'] ?? null;
$threshold = $input['threshold'] ?? 0.1;

if (!$image1Url || !$image2Url) {
    http_response_code(400);
    echo json_encode(['error' => 'image1 and image2 are required']);
    exit;
}

$baseUrl = $_ENV['SCREENSHOT_BASE_URL'] ?? 'http://screenshoter:8080';

function loadImage(string $url, string $baseUrl): ?\Imagick
{
    $fullUrl = str_starts_with($url, 'http') ? $url : $baseUrl . $url;
    file_put_contents('php://stderr', "DEBUG: Loading image from $fullUrl\n", FILE_APPEND);

    $content = @file_get_contents($fullUrl);
    if ($content === false) {
        file_put_contents('php://stderr', "ERROR: Failed to load image: $fullUrl\n", FILE_APPEND);
        return null;
    }

    $imagick = new \Imagick();
    try {
        $imagick->readImageBlob($content);
        $imagick->setImageFormat('png');
        file_put_contents('php://stderr', "DEBUG: Image loaded, size: " . $imagick->getImageWidth() . "x" . $imagick->getImageHeight() . "\n", FILE_APPEND);
        return $imagick;
    } catch (\Exception $e) {
        file_put_contents('php://stderr', "EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
        return null;
    }
}

$img1 = loadImage($image1Url, $baseUrl);
$img2 = loadImage($image2Url, $baseUrl);

if (!$img1 || !$img2) {
    http_response_code(400);
    echo json_encode(['error' => 'Failed to load one or both images']);
    exit;
}

try {
    $img1->resizeImage($img2->getImageWidth(), $img2->getImageHeight(), \Imagick::FILTER_LANCZOS, 1);
    file_put_contents('php://stderr', "DEBUG: Images resized\n", FILE_APPEND);
} catch (\Exception $e) {
    file_put_contents('php://stderr', "EXCEPTION resize: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['error' => 'Resize failed', 'details' => $e->getMessage()]);
    exit;
}

$quantum = \Imagick::getQuantum();
$fuzz = $threshold * $quantum;
$img1->setOption('fuzz', $fuzz);

try {
    [$diff, $differentPixels] = $img1->compareImages($img2, \Imagick::METRIC_ABSOLUTEERRORMETRIC);

    $width = $img1->getImageWidth();
    $height = $img1->getImageHeight();
    $totalPixels = $width * $height;

    $percent = $totalPixels > 0 ? round(($differentPixels / $totalPixels) * 100, 2) : 0;

    // Создаём маску: где есть отличия → 1
    $mask = clone $diff;
    $mask->evaluateImage(2, 0); // >0 → 1

    // Инвертируем маску: 1 → 0 (прозрачность), 0 → 1 (непрозрачность)
    $mask->negateImage(false);

    // Применяем как альфа-канал
    $mask->setImageAlphaChannel(\Imagick::ALPHACHANNEL_COPY);

    // Создаём полупрозрачный красный слой (без setImageOpacity)
    $redLayer = new \Imagick();
    $redLayer->newPseudoImage($width, $height, 'canvas:red');
    $redLayer->setImageAlphaChannel(\Imagick::ALPHACHANNEL_SET);

    // Создаём альфа-канал с 50% прозрачности
    $alpha = new \Imagick();
    $alpha->newPseudoImage($width, $height, 'xc:none');
    $alpha->evaluateImage(\Imagick::EVALUATE_SET, 0.5 * \Imagick::getQuantum(), \Imagick::CHANNEL_GRAY);

    // Накладываем альфа на красный слой
    $redLayer->compositeImage($alpha, \Imagick::COMPOSITE_COPYOPACITY, 0, 0);

    // Применяем маску к прозрачному красному слою
    $redLayer->compositeImage($mask, \Imagick::COMPOSITE_COPYOPACITY, 0, 0);

    // Основа — первый скриншот
    $result = clone $img1;

    // Накладываем красную маску
    $result->compositeImage($redLayer, \Imagick::COMPOSITE_OVER, 0, 0);

    // Сохраняем
    $filename = 'diff-' . time() . '-' . substr(md5($image1Url . $image2Url), 0, 8) . '.png';
    $diffsDir = $_ENV['DIFFS_DIR'] ?? '/app/public/diffs';
    if (!is_dir($diffsDir)) {
        mkdir($diffsDir, 0777, true);
    }
    $filepath = $diffsDir . '/' . $filename;
    $result->writeImage($filepath);

    file_put_contents('php://stderr', "DEBUG: Diff image saved to $filepath\n", FILE_APPEND);

    $img1->clear();
    $img2->clear();
    $diff->clear();
    $mask->clear();
    $redLayer->clear();
    $alpha->clear();
    $result->clear();

    http_response_code(200);
    echo json_encode([
        'percent' => $percent,
        'image_url' => '/diffs/' . basename($filepath),
        'threshold' => $threshold,
        'is_different' => $percent >= ($threshold * 100),
        'method' => 'overlay_diff',
        'different_pixels' => $differentPixels,
        'total_pixels' => $totalPixels,
    ]);
} catch (\Exception $e) {
    file_put_contents('php://stderr', "EXCEPTION compare: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode([
        'error' => 'Image comparison failed',
        'details' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
}