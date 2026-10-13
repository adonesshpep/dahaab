<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ImageEmbeddingService
{
    public function generate(string $imagePath): array
    {
        $url = config('services.image_embeddings.url');
        if (! is_string($url) || $url === '') {
            throw new RuntimeException('The image embedding service URL is not configured.');
        }

        $stream = fopen($imagePath, 'rb');
        if ($stream === false) {
            throw new RuntimeException('The uploaded image could not be opened for embedding.');
        }

        try {
            $response = Http::timeout((int) config('services.image_embeddings.timeout', 60))
                ->attach('image', $stream, basename($imagePath))
                ->post(rtrim($url, '/').'/embeddings');
        } finally {
            fclose($stream);
        }

        if ($response->failed() || ! is_array($response->json('embedding'))) {
            throw new RuntimeException('The image embedding service returned an invalid response.');
        }

        return $response->json('embedding');
    }
}
