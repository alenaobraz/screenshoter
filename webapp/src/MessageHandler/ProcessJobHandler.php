<?php

namespace App\MessageHandler;

use App\Entity\Job;
use App\Message\ProcessJob;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
class ProcessJobHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private HttpClientInterface $client,
        private LoggerInterface $logger
    ) {}

    public function __invoke(ProcessJob $message): void
    {
        $job = $this->em->getRepository(Job::class)->findOneBy(['uuid' => $message->getJobUuid()]);
        if (!$job) {
            $this->logger->error("Job not found: " . $message->getJobUuid());
            return;
        }

        $job->setStatus(Job::STATUS_PROCESSING);
        $this->em->flush();

        $results = [];

        foreach ($job->getUrls() as $pair) {
            try {
                // Шаг 1: Получить скриншоты
                $beforeScreenshot = $this->takeScreenshot($pair['before'], $job->getOptions());
                $afterScreenshot = $this->takeScreenshot($pair['after'], $job->getOptions());

                // Шаг 2: Сравнить
                $diffResult = $this->compareScreenshots($beforeScreenshot, $afterScreenshot, $job->getOptions());

                $results[] = [
                    'beforeScreenshot' => $beforeScreenshot,
                    'afterScreenshot' => $afterScreenshot,
                    'urls' => $pair,
                    'difference_percent' => $diffResult['percent'],
                    'diff_image_url' => $diffResult['image_url'],
                ];

                // Сохранить результат
                $job->setStatus(Job::STATUS_COMPLETED);
                $job->setResult(json_encode($results));
                $this->em->flush();
            } catch (\Exception $e) {
                $this->logger->error("Failed to process pair: " . $e->getMessage());
                // Сохранить ошибку
                $job->setStatus(Job::STATUS_FAILED);
                $job->setResult($e->getMessage());
                $this->em->flush();
            }
        }
    }

    private function takeScreenshot(string $url, array $options): string
    {
        $this->logger->info('Taking screenshot', [
            'url' => $url,
            'service_url' => $_ENV['SCREENSHOT_SERVICE_URL'] . '/screenshot',
            'api_key' => $_ENV['SCREENSHOT_API_KEY'] ? '*** SET ***' : '*** NULL ***',
        ]);

        try {
            $response = $this->client->request('POST', $_ENV['SCREENSHOT_SERVICE_URL'] . '/screenshot', [
                'headers' => [
                    'X-Api-Key' => $_ENV['SCREENSHOT_API_KEY'], // ← исправлено: X-Api-Key
                ],
                'json' => [
                    'url' => $url,
                    'width' => $options['width'] ?? 1920,
                    'height' => $options['height'] ?? 1080,
                    'headers' => $options['headers'] ?? [],
                ],
                'timeout' => 70.0,
            ]);

            $data = $response->toArray();
            return $data['screenshot_url'];
        } catch (\Exception $e) {
            $this->logger->error('Screenshot request failed', [
                'url' => $url,
                'message' => $e->getMessage(),
                'response' => $e->getCode() >= 400 && $e->getResponse() ? $e->getResponse()->getContent(false) : null,
            ]);
            throw $e;
        }
    }

    private function compareScreenshots(string $img1, string $img2, array $options): array
    {
        $this->logger->info('Comparing screenshots', [
            'image1' => $img1,
            'image2' => $img2,
            'threshold' => $options['threshold'] ?? 0.1,
        ]);

        try {
            $response = $this->client->request('POST', $_ENV['DIFF_SERVICE_URL'], [
                'headers' => [
                    'X-Api-Key' => $_ENV['DIFF_SERVICE_API_KEY'] ?? null,
                ],
                'json' => [
                    'image1' => $img1,
                    'image2' => $img2,
                    'threshold' => $options['threshold'] ?? 0.1,
                ],
                'timeout' => 60.0,
            ]);

            $data = $response->toArray();
            $this->logger->info('Diff result', $data);

            return $data;
        } catch (\Exception $e) {
            $this->logger->error('Diff request failed', [
                'image1' => $img1,
                'image2' => $img2,
                'message' => $e->getMessage(),
                'response' => $e->getCode() >= 400 && $e->getResponse() ? $e->getResponse()->getContent(false) : null,
            ]);
            throw $e;
        }
    }
}
