<?php

namespace App\Services\ApiClients;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

abstract class BaseApiClient
{
    protected string $baseUrl;
    protected string $apiKey;
    protected int $rateLimit;
    protected int $cacheTtl = 900; // 15 minutes default
    
    /**
     * Make HTTP GET request with caching and error handling
     */
    protected function get(string $endpoint, array $params = [], ?int $cacheTtl = null): array
    {
        $cacheKey = $this->getCacheKey($endpoint, $params);
        $ttl = $cacheTtl ?? $this->cacheTtl;
        
        // Try cache first
        if ($cached = Cache::get($cacheKey)) {
            Log::info("Cache hit for {$endpoint}", ['params' => $params]);
            return is_array($cached) ? $cached : [];
        }
        
        try {
            // Reduced timeout from 30s to 10s for faster failure
            // Reduced retries from 3 to 0 to avoid cascading delays
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->retry(0)
                ->get($this->baseUrl . $endpoint, array_merge($params, $this->getAuthParams()));
            
            if ($response->successful()) {
                $data = $response->json();
                if (is_array($data)) {
                    Cache::put($cacheKey, $data, $ttl);
                    Log::info("API call successful for {$endpoint}");
                    return $data;
                }
                Log::warning("API returned non-array data for {$endpoint}");
                return [];
            }
            
            Log::warning("API call failed for {$endpoint}", [
                'status' => $response->status(),
            ]);
            
            return [];
            
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Connection timeout or network error - don't log full trace to reduce noise
            Log::warning("API connection failed for {$endpoint}: " . $e->getMessage());
            return [];
        } catch (\Exception $e) {
            Log::warning("API exception for {$endpoint}: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Generate cache key from endpoint and params
     */
    protected function getCacheKey(string $endpoint, array $params): string
    {
        $key = get_class($this) . ':' . $endpoint . ':' . md5(json_encode($params));
        return str_replace('\\', '_', $key);
    }
    
    /**
     * Get authentication parameters (to be overridden by child classes)
     */
    abstract protected function getAuthParams(): array;
    
    /**
     * Clear cache for this client
     */
    public function clearCache(): void
    {
        $prefix = str_replace('\\', '_', get_class($this));
        // Note: This is a simplified version. In production, you'd want a more sophisticated cache clearing strategy
        Cache::flush();
        Log::info("Cache cleared for " . get_class($this));
    }
}
