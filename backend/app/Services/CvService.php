<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CvService
{
    protected bool $enabled;
    protected string $url;
    protected float $threshold;
    protected int $timeout;
    protected array $categories;
    protected bool $mock;

    public function __construct()
    {
        $this->enabled = (bool) config('services.cv.enabled', true);
        $this->url = (string) config('services.cv.url', 'http://cv-service:8000');
        $this->threshold = (float) config('services.cv.threshold', 0.80);
        $this->timeout = (int) config('services.cv.timeout', 10);
        $this->categories = (array) config('services.cv.categories', []);
        $this->mock = (bool) config('services.cv.mock', false);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getThreshold(): float
    {
        return $this->threshold;
    }

    public function isCategorySupported(?string $categoryName): bool
    {
        if (!$categoryName) {
            return false;
        }

        return in_array($categoryName, $this->categories, true);
    }

    /**
     * Send photo file to cv-service for analysis.
     * Returns: ['label' => 'RUSAK'|'BAIK'|'SPAM', 'confidence' => float, 'detected_category' => ?string]
     */
    public function analyze(string $photoPath): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        try {
            if (!Storage::disk('public')->exists($photoPath)) {
                Log::error("CvService: photo not found at storage: {$photoPath}");
                return null;
            }

            $fileContents = Storage::disk('public')->get($photoPath);
            $fileName = basename($photoPath);

            $response = Http::timeout($this->timeout)
                ->attach('file', $fileContents, $fileName)
                ->post("{$this->url}/analyze");

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['label']) && isset($data['confidence'])) {
                    return [
                        'label' => (string) $data['label'],
                        'confidence' => (float) $data['confidence'],
                        'detected_category' => $data['detected_category'] ?? null,
                        'probabilities' => $data['probabilities'] ?? null,
                    ];
                }
            }

            Log::warning("CvService: unsuccessful response from CV service", [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error("CvService: exception connecting to CV service - " . $e->getMessage());
            return null;
        }
    }
}
