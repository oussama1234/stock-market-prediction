import { useState, useMemo, useCallback, useRef } from 'react';
import { newsAPI } from '../services/api';

// Hook for aggregated market news with filters and pagination
export const useMarketNews = () => {
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [query, setQuery] = useState('');
  const [datePreset, setDatePreset] = useState('today'); // 'today' | 'last_7d' | 'last_30d' | 'custom'
  const [from, setFrom] = useState(null); // ISO string or null
  const [to, setTo] = useState(null);   // ISO string or null
  const [page, setPage] = useState(1);
  const [limit, setLimit] = useState(9);
  const [hasMore, setHasMore] = useState(true);
  const [lastUpdated, setLastUpdated] = useState(new Date());
  const [totalCount, setTotalCount] = useState(0);

  const inFlight = useRef(false);

  const params = useMemo(() => {
    const p = {
      important_first: true,
      page,
      limit,
    };
    if (query && query.trim()) p.q = query.trim();
    if (datePreset === 'custom') {
      if (from) p.from = from;
      if (to) p.to = to;
    } else {
      p.date = datePreset; // today | last_7d | last_30d
    }
    return p;
  }, [query, datePreset, from, to, page, limit]);

  const resetAndFetch = useCallback(async () => {
    setItems([]);
    setPage(1);
    setHasMore(true);
    setError(null);
    await fetchPage(1, limit, { queryOverride: query, datePresetOverride: datePreset, fromOverride: from, toOverride: to });
  }, [query, datePreset, from, to, limit]);

  const fetchPage = useCallback(async (nextPage = page, nextLimit = limit, overrides = {}) => {
    if (inFlight.current) return;
    try {
      inFlight.current = true;
      setLoading(true);
      setError(null);

      // Build params considering overrides
      const { queryOverride, datePresetOverride, fromOverride, toOverride } = overrides;
      const finalQuery = queryOverride !== undefined ? queryOverride : query;
      const finalPreset = datePresetOverride !== undefined ? datePresetOverride : datePreset;
      const finalFrom = fromOverride !== undefined ? fromOverride : from;
      const finalTo = toOverride !== undefined ? toOverride : to;

      const reqParams = {
        page: nextPage,
        limit: nextLimit,
        important_first: true,
      };
      if (finalQuery && finalQuery.trim()) reqParams.q = finalQuery.trim();
      if (finalPreset === 'custom') {
        if (finalFrom) reqParams.from = finalFrom;
        if (finalTo) reqParams.to = finalTo;
      } else {
        reqParams.date = finalPreset; // today | last_7d | last_30d
      }

      const res = await newsAPI.getMarketAdvanced(reqParams);
      const newItems = res?.data || [];
      const nextHasMore = !!res?.has_more;
      const total = res?.total || res?.count || newItems.length;
      setTotalCount(total);

      const sortByPublishedDesc = (arr) => {
        return [...arr].sort((a, b) => {
          const ta = new Date(a.published_at || 0).getTime();
          const tb = new Date(b.published_at || 0).getTime();
          return tb - ta;
        });
      };

      if (nextPage === 1) {
        setItems(sortByPublishedDesc(newItems));
      } else {
        // Merge then sort to maintain correct chronological order across pages
        setItems((prev) => {
          const map = new Map();
          for (const it of prev) {
            if (it?.url) map.set(it.url, it);
          }
          for (const it of newItems) {
            if (it?.url && !map.has(it.url)) map.set(it.url, it);
          }
          const merged = Array.from(map.values());
          return sortByPublishedDesc(merged);
        });
      }
      setHasMore(nextHasMore);
      setLastUpdated(new Date());
    } catch (err) {
      setError(err?.message || 'Failed to load market news');
    } finally {
      setLoading(false);
      inFlight.current = false;
    }
  }, [page, limit, query, datePreset, from, to]);

  const loadMore = useCallback(async () => {
    if (!hasMore || loading) return;
    const nextPage = page + 1;
    setPage(nextPage);
    await fetchPage(nextPage, limit);
  }, [page, limit, hasMore, loading, fetchPage]);

  return useMemo(() => ({
    // state
    items,
    loading,
    error,
    query,
    datePreset,
    from,
    to,
    page,
    limit,
    hasMore,
    lastUpdated,
    totalCount,

    // setters
    setQuery,
    setDatePreset,
    setFrom,
    setTo,
    setLimit,

    // actions
    fetchPage,
    resetAndFetch,
    loadMore,
  }), [items, loading, error, query, datePreset, from, to, page, limit, hasMore, lastUpdated, totalCount, fetchPage, resetAndFetch, loadMore]);
};

export default useMarketNews;
