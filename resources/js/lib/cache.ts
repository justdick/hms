/**
 * Client-side cache implementation with Time-To-Live (TTL) support
 * 
 * This cache is used to store API responses and reduce server requests.
 * All cached data is stored in memory and will be cleared on page refresh.
 * 
 * @example
 * ```typescript
 * // Cache API response for 5 minutes
 * const data = cache.get<ClaimData>('claims-summary');
 * if (!data) {
 *   const response = await fetchClaimsSummary();
 *   cache.set('claims-summary', response, TTL.FIVE_MINUTES);
 * }
 * 
 * // Invalidate cache after update
 * cache.invalidate('claims-summary');
 * ```
 */

/**
 * Internal cache entry structure
 */
interface CacheEntry<T> {
    /** The cached data */
    data: T;
    /** Timestamp when the data was cached (milliseconds) */
    timestamp: number;
    /** Time-to-live in milliseconds */
    ttl: number;
}

/**
 * ClientCache - In-memory cache with automatic expiration
 */
class ClientCache {
    private cache: Map<string, CacheEntry<any>> = new Map();

    /**
     * Retrieve cached data if it exists and hasn't expired
     * 
     * @param key - Unique cache key
     * @returns Cached data or null if not found or expired
     */
    get<T>(key: string): T | null {
        const entry = this.cache.get(key);
        
        if (!entry) {
            return null;
        }

        const now = Date.now();
        const age = now - entry.timestamp;

        // Check if cache has expired
        if (age > entry.ttl) {
            this.cache.delete(key);
            return null;
        }

        return entry.data as T;
    }

    /**
     * Store data in cache with specified TTL
     * 
     * @param key - Unique cache key
     * @param data - Data to cache
     * @param ttl - Time-to-live in milliseconds (default: 5 minutes)
     */
    set<T>(key: string, data: T, ttl: number = 300000): void {
        this.cache.set(key, {
            data,
            timestamp: Date.now(),
            ttl,
        });
    }

    /**
     * Check if a cache entry exists and is still valid
     * 
     * @param key - Cache key to check
     * @returns True if entry exists and hasn't expired
     */
    has(key: string): boolean {
        return this.get(key) !== null;
    }

    /**
     * Remove a specific cache entry
     * 
     * @param key - Cache key to remove
     */
    invalidate(key: string): void {
        this.cache.delete(key);
    }

    /**
     * Remove all cache entries matching a regex pattern
     * 
     * @param pattern - Regex pattern to match cache keys
     * @example
     * ```typescript
     * // Invalidate all coverage-related caches
     * cache.invalidatePattern('coverage-.*');
     * ```
     */
    invalidatePattern(pattern: string): void {
        const regex = new RegExp(pattern);
        const keysToDelete: string[] = [];

        this.cache.forEach((_, key) => {
            if (regex.test(key)) {
                keysToDelete.push(key);
            }
        });

        keysToDelete.forEach(key => this.cache.delete(key));
    }

    /**
     * Clear all cache entries
     */
    clear(): void {
        this.cache.clear();
    }

    /**
     * Get cache statistics
     */
    stats(): { size: number; keys: string[] } {
        return {
            size: this.cache.size,
            keys: Array.from(this.cache.keys()),
        };
    }
}

/**
 * Singleton cache instance
 * Use this instance throughout the application for consistent caching
 */
export const cache = new ClientCache();

/**
 * Common TTL (Time-To-Live) values in milliseconds
 * 
 * @example
 * ```typescript
 * cache.set('data', response, TTL.FIVE_MINUTES);
 * ```
 */
export const TTL = {
    /** 1 minute (60,000 ms) */
    ONE_MINUTE: 60 * 1000,
    /** 5 minutes (300,000 ms) - Default TTL */
    FIVE_MINUTES: 5 * 60 * 1000,
    /** 10 minutes (600,000 ms) */
    TEN_MINUTES: 10 * 60 * 1000,
    /** 30 minutes (1,800,000 ms) */
    THIRTY_MINUTES: 30 * 60 * 1000,
    /** 1 hour (3,600,000 ms) */
    ONE_HOUR: 60 * 60 * 1000,
};
