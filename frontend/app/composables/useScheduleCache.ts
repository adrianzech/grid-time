type ApiCollection<T> = {
  'member'?: T[]
  'hydra:member'?: T[]
  'view'?: ApiCollectionView
  'hydra:view'?: ApiCollectionView
}

type ApiCollectionView = {
  'next'?: string
  'hydra:next'?: string
}

export type ApiSeason = {
  '@id': string
  'id': number
  'year': number
  'name': string
  'scheduleUpdatedAt': string | null
}

export type ApiEvent = {
  '@id': string
  'id': number
  'roundNumber': number
  'name': string
  'countryName': string
  'location': string
  'sourceUrl': string
}

export type ApiSession = {
  '@id': string
  'id': number
  'event': string
  'name': string
  'startsAt': string
  'endsAt': string | null
  'sourceUrl': string
  'trackTimezoneOffset': string | null
}

export type ApiWeekendOverviewSeries = {
  code: string
  name: string
}

export type ApiWeekendOverviewEvent = {
  id: string
  databaseId: number
  roundNumber: number
  name: string
  countryName: string
  location: string
  sourceUrl: string
}

export type ApiWeekendOverviewSession = {
  id: string
  databaseId: number
  event: string
  name: string
  startsAt: string
  endsAt: string | null
  sourceUrl: string
  trackTimezoneOffset: string | null
}

export type ApiWeekendOverviewItem = {
  id: string
  series: ApiWeekendOverviewSeries
  event: ApiWeekendOverviewEvent
  sessions: ApiWeekendOverviewSession[]
}

export type ScheduleCacheStatus = 'idle' | 'loading' | 'refreshing' | 'ready' | 'error'

export type ScheduleCacheEntry = {
  events: ApiEvent[]
  sessions: ApiSession[]
  scheduleUpdatedAt: string | null
  status: ScheduleCacheStatus
  error: string
}

type LoadOptions = {
  force?: boolean
}

function createScheduleCacheEntry(): ScheduleCacheEntry {
  return {
    events: [],
    sessions: [],
    scheduleUpdatedAt: null,
    status: 'idle',
    error: '',
  }
}

function collectionMembers<T>(collection?: ApiCollection<T> | null): T[] {
  return collection?.member ?? collection?.['hydra:member'] ?? []
}

export function useScheduleCache(seriesCodes: readonly string[], primarySeriesCode: string, seasonYear: number) {
  const config = useRuntimeConfig()
  const cache = ref<Record<string, ScheduleCacheEntry>>(
    Object.fromEntries(seriesCodes.map((code) => [code, createScheduleCacheEntry()])),
  )
  const inFlightLoads = new Map<string, Promise<void>>()

  async function initialize(): Promise<void> {
    await loadSeries(primarySeriesCode)
  }

  async function loadSeries(seriesCode: string, options: LoadOptions = {}): Promise<void> {
    const existingLoad = inFlightLoads.get(seriesCode)

    if (existingLoad) {
      return existingLoad
    }

    const load = loadSeriesInternal(seriesCode, options).finally(() => {
      inFlightLoads.delete(seriesCode)
    })

    inFlightLoads.set(seriesCode, load)

    return load
  }

  async function refreshSeries(seriesCode: string): Promise<void> {
    await loadSeries(seriesCode, { force: true })
  }

  async function loadSeriesInternal(seriesCode: string, options: LoadOptions): Promise<void> {
    const current = entry(seriesCode)
    const hasCachedData = current.events.length > 0 || current.sessions.length > 0

    updateEntry(seriesCode, {
      status: hasCachedData ? 'refreshing' : 'loading',
      error: '',
    })

    try {
      const scheduleUpdatedAt = await fetchScheduleVersion(seriesCode)

      if (hasCachedData && !options.force && scheduleUpdatedAt === current.scheduleUpdatedAt) {
        updateEntry(seriesCode, {
          scheduleUpdatedAt,
          status: 'ready',
        })

        return
      }

      const [events, sessions] = await Promise.all([
        fetchEvents(seriesCode),
        fetchSessions(seriesCode),
      ])

      updateEntry(seriesCode, {
        events: events.sort((a, b) => a.roundNumber - b.roundNumber),
        sessions: sessions.sort((a, b) => new Date(a.startsAt).getTime() - new Date(b.startsAt).getTime()),
        scheduleUpdatedAt,
        status: 'ready',
      })
    } catch (error) {
      updateEntry(seriesCode, {
        status: hasCachedData ? 'ready' : 'error',
        error: error instanceof Error ? error.message : 'Could not load schedule.',
      })
    }
  }

  async function fetchScheduleVersion(seriesCode: string): Promise<string | null> {
    const seasons = await fetchCollection<ApiSeason>('/seasons', {
      'series.code': seriesCode,
      'year': seasonYear,
      'itemsPerPage': 1,
    })

    return seasons[0]?.scheduleUpdatedAt ?? null
  }

  async function fetchEvents(seriesCode: string): Promise<ApiEvent[]> {
    return fetchCollection<ApiEvent>('/events', {
      'season.series.code': seriesCode,
      'season.year': seasonYear,
      'order[roundNumber]': 'asc',
    })
  }

  async function fetchSessions(seriesCode: string): Promise<ApiSession[]> {
    return fetchCollection<ApiSession>('/sessions', {
      'event.season.series.code': seriesCode,
      'event.season.year': seasonYear,
      'order[startsAt]': 'asc',
    })
  }

  async function fetchCollection<T>(path: string, query: Record<string, string | number>): Promise<T[]> {
    const results: T[] = []
    let page = 1
    let hasNextPage = true

    while (hasNextPage) {
      const collection = await $fetch<ApiCollection<T>>(path, {
        baseURL: config.public.apiBase,
        query: {
          ...query,
          page,
        },
      })

      results.push(...collectionMembers(collection))
      hasNextPage = Boolean(collection.view?.next ?? collection.view?.['hydra:next'] ?? collection['hydra:view']?.next ?? collection['hydra:view']?.['hydra:next'])
      page += 1
    }

    return results
  }

  async function fetchWeekendOverview(year: number, windowStart: Date, windowEnd: Date): Promise<ApiWeekendOverviewItem[]> {
    return fetchCollection<ApiWeekendOverviewItem>('/weekend-overview', {
      year,
      windowStart: windowStart.toISOString(),
      windowEnd: windowEnd.toISOString(),
    })
  }

  function entry(seriesCode: string): ScheduleCacheEntry {
    return cache.value[seriesCode] ?? createScheduleCacheEntry()
  }

  function updateEntry(seriesCode: string, update: Partial<ScheduleCacheEntry>): void {
    cache.value = {
      ...cache.value,
      [seriesCode]: {
        ...entry(seriesCode),
        ...update,
      },
    }
  }

  return {
    cache,
    fetchWeekendOverview,
    initialize,
    loadSeries,
    refreshSeries,
  }
}
