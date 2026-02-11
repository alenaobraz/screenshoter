<?php

namespace App;

class ScreenshotController
{
    private string $screenshotsDir;

    public function __construct()
    {
        $this->screenshotsDir = $_ENV['SCREENSHOTS_DIR'] ?? '/app/public/screenshots';
        if (!is_dir($this->screenshotsDir)) {
            mkdir($this->screenshotsDir, 0777, true);
        }
    }

    public function handle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $apiKey = getallheaders()['X-Api-Key'] ?? null;
        if ($apiKey !== $_ENV['SCREENSHOT_API_KEY']) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid API key']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $url = $input['url'] ?? null;
        $width = $input['width'] ?? 1920;
        $height = $input['height'] ?? 1080;

        if (!$url) {
            http_response_code(400);
            echo json_encode(['error' => 'URL is required']);
            return;
        }

        $filename = sprintf('%s-%s.png', time(), substr(bin2hex(random_bytes(6)), 0, 8));
        $filepath = $this->screenshotsDir . '/' . $filename;
        $publicUrl = '/screenshots/' . $filename;

        try {
            // Создаём экземпляр
            $browser = \Spatie\Browsershot\Browsershot::url($url);

            // Устанавливаем бинарники
            $browser->setNodeBinary('/usr/bin/node');
            $browser->setNpmBinary('/usr/bin/npm');
            $browser->setChromePath('/usr/bin/chromium-browser');

            // Устанавливаем аргументы напрямую
            $browser->setOption('args', [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--single-process',
                '--disable-gpu',
                '--headless=new',
                '--log-level=0',
                '--v=1',
                '--enable-logging',
            ]);

            // Остальные настройки
            $browser->windowSize((int)$width, (int)$height)
                ->timeout(180);
            // Заменяем waitUntilNetworkIdle() на более стабильное условие
            $browser->setOption('waitUntil', ['domcontentloaded']);
            $browser->setOption('delay', 3000); // Пауза 3 секунды после загрузки DOM

            // Сохраняем
            $browser->save($filepath);

            http_response_code(200);
            echo json_encode([
                'screenshot_url' => $publicUrl,
                'filename' => $filename,
                'size' => compact('width', 'height'),
            ]);
        } catch (\Exception $e) {
            error_log('Screenshot failed: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'error' => 'Failed to take screenshot',
                'details' => $e->getMessage(),
            ]);
        }
    }
}