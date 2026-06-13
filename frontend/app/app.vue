<template>
  <div class="min-h-screen bg-carbon text-zinc-100 antialiased">
    <NuxtRouteAnnouncer />

    <main class="mx-auto flex min-h-screen w-full max-w-7xl flex-col px-4 py-4 sm:px-6 lg:px-8">
      <header class="flex flex-col gap-5 border-b border-white/10 py-5 md:flex-row md:items-end md:justify-between">
        <div class="space-y-3">
          <div class="flex items-center gap-3">
            <span class="h-3 w-10 bg-race-red" />
            <span class="text-xs font-semibold uppercase tracking-[0.26em] text-race-red">Grid Time</span>
          </div>
          <div>
            <h1 class="text-3xl font-black tracking-tight text-white sm:text-5xl">
              Formula 1 Schedule
            </h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-zinc-400 sm:text-base">
              2026 race weekends, sessions, local start times and source-backed schedule data.
            </p>
          </div>
        </div>

        <div class="grid grid-cols-3 gap-2 rounded-lg border border-white/10 bg-panel p-2 shadow-2xl shadow-black/20">
          <div class="px-3 py-2">
            <p class="text-xs text-zinc-500">
              Season
            </p>
            <p class="text-lg font-bold text-white">
              {{ seasonYear }}
            </p>
          </div>
          <div class="border-x border-white/10 px-3 py-2">
            <p class="text-xs text-zinc-500">
              Events
            </p>
            <p class="text-lg font-bold text-white">
              {{ events.length }}
            </p>
          </div>
          <div class="px-3 py-2">
            <p class="text-xs text-zinc-500">
              Sessions
            </p>
            <p class="text-lg font-bold text-white">
              {{ sessions.length }}
            </p>
          </div>
        </div>
      </header>

      <section class="grid gap-4 py-5 lg:grid-cols-[minmax(0,1fr)_360px]">
        <div class="rounded-lg border border-white/10 bg-panel shadow-2xl shadow-black/20">
          <div class="flex flex-col gap-3 border-b border-white/10 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h2 class="text-lg font-bold text-white">
                Sessions
              </h2>
              <p class="text-sm text-zinc-500">
                {{ timezoneLabel }}
              </p>
            </div>

            <div class="flex flex-wrap gap-2">
              <button
                v-for="option in filters"
                :key="option.value"
                type="button"
                class="rounded-md px-3 py-2 text-sm font-semibold transition"
                :class="selectedFilter === option.value ? 'bg-race-red text-white shadow-lg shadow-race-red/25' : 'bg-white/5 text-zinc-300 hover:bg-white/10'"
                @click="selectedFilter = option.value"
              >
                {{ option.label }}
              </button>
            </div>
          </div>

          <div
            v-if="pending"
            class="grid min-h-80 place-items-center p-6 text-zinc-400"
          >
            Loading schedule...
          </div>

          <div
            v-else-if="errorMessage"
            class="m-4 rounded-lg border border-race-red/40 bg-race-red/10 p-4 text-sm text-red-100"
          >
            {{ errorMessage }}
          </div>

          <div
            v-else
            class="divide-y divide-white/10"
          >
            <article
              v-for="session in filteredSessions"
              :key="session['@id']"
              class="grid gap-4 p-4 transition hover:bg-white/[0.03] md:grid-cols-[92px_minmax(0,1fr)_120px]"
            >
              <div class="flex items-center gap-3 md:block">
                <div class="text-sm font-black text-race-red">
                  R{{ getEvent(session)?.roundNumber ?? '-' }}
                </div>
                <div class="text-xs uppercase text-zinc-500">
                  {{ formatDate(session.startsAt) }}
                </div>
              </div>

              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="truncate text-base font-bold text-white">
                    {{ getEvent(session)?.name ?? 'Unknown event' }}
                  </h3>
                  <span class="rounded bg-white/5 px-2 py-1 text-xs font-medium text-zinc-400">{{ getEvent(session)?.location ?? 'TBD' }}</span>
                </div>
                <p class="mt-1 text-sm font-medium text-zinc-300">
                  {{ session.name }}
                </p>
                <a
                  :href="session.sourceUrl"
                  target="_blank"
                  rel="noreferrer"
                  class="mt-2 inline-flex text-xs font-semibold text-race-red hover:text-red-300"
                >
                  Source
                </a>
              </div>

              <div class="flex items-center justify-between gap-3 md:justify-end">
                <span class="text-2xl font-black tabular-nums text-white">{{ formatTime(session.startsAt) }}</span>
                <span class="text-sm text-zinc-500">{{ session.endsAt ? formatTime(session.endsAt) : '' }}</span>
              </div>
            </article>
          </div>
        </div>

        <aside class="space-y-4">
          <section class="rounded-lg border border-race-red/35 bg-race-red/10 p-5 shadow-2xl shadow-race-red/10">
            <p class="text-xs font-bold uppercase tracking-[0.22em] text-race-red">
              Next up
            </p>
            <h2 class="mt-4 text-2xl font-black text-white">
              {{ nextSessionEvent?.name ?? 'No upcoming session' }}
            </h2>
            <p class="mt-1 text-sm text-zinc-300">
              {{ nextSession?.name ?? 'Schedule unavailable' }}
            </p>
            <div class="mt-5 flex items-end justify-between gap-4">
              <div>
                <p class="text-sm text-zinc-400">
                  {{ nextSession ? formatDateLong(nextSession.startsAt) : '-' }}
                </p>
                <p class="mt-1 text-4xl font-black text-white">
                  {{ nextSession ? formatTime(nextSession.startsAt) : '--:--' }}
                </p>
              </div>
              <div class="rounded-md bg-black/30 px-3 py-2 text-right">
                <p class="text-xs text-zinc-500">
                  Round
                </p>
                <p class="text-xl font-black text-white">
                  {{ nextSessionEvent?.roundNumber ?? '-' }}
                </p>
              </div>
            </div>
          </section>

          <section class="rounded-lg border border-white/10 bg-panel p-5">
            <h2 class="text-lg font-bold text-white">
              Race weekends
            </h2>
            <div class="mt-4 space-y-2">
              <div
                v-for="event in events"
                :key="event['@id']"
                class="flex items-center gap-3 rounded-md bg-white/[0.03] px-3 py-2"
              >
                <span class="w-8 text-sm font-black text-race-red">R{{ event.roundNumber }}</span>
                <div class="min-w-0">
                  <p class="truncate text-sm font-semibold text-white">
                    {{ event.name }}
                  </p>
                  <p class="text-xs text-zinc-500">
                    {{ event.location }}
                  </p>
                </div>
              </div>
            </div>
          </section>
        </aside>
      </section>
    </main>
  </div>
</template>

<script setup lang="ts">
type ApiCollection<T> = {
  'member'?: T[]
  'hydra:member'?: T[]
}

type ApiEvent = {
  '@id': string
  'id': number
  'roundNumber': number
  'name': string
  'location': string
  'sourceUrl': string
}

type ApiSession = {
  '@id': string
  'id': number
  'event': string
  'name': string
  'startsAt': string
  'endsAt': string | null
  'sourceUrl': string
}

const seasonYear = 2026
const filters = [
  { label: 'All', value: 'all' },
  { label: 'Upcoming', value: 'upcoming' },
  { label: 'Race', value: 'race' },
] as const

type FilterValue = typeof filters[number]['value']

const selectedFilter = ref<FilterValue>('all')
const config = useRuntimeConfig()

const sessionQuery = {
  'event.season.series.code': 'F1',
  'event.season.year': seasonYear,
  'order[startsAt]': 'asc',
}

const eventQuery = {
  'season.series.code': 'F1',
  'season.year': seasonYear,
  'order[roundNumber]': 'asc',
}

const [{ data: sessionsData, pending: sessionsPending, error: sessionsError }, { data: eventsData, pending: eventsPending, error: eventsError }] = await Promise.all([
  useFetch<ApiCollection<ApiSession>>('/sessions', {
    baseURL: config.public.apiBase,
    query: sessionQuery,
    server: false,
  }),
  useFetch<ApiCollection<ApiEvent>>('/events', {
    baseURL: config.public.apiBase,
    query: eventQuery,
    server: false,
  }),
])

const sessions = computed(() => collectionMembers(sessionsData.value))
const events = computed(() => collectionMembers(eventsData.value).sort((a, b) => a.roundNumber - b.roundNumber))
const pending = computed(() => sessionsPending.value || eventsPending.value)
const errorMessage = computed(() => sessionsError.value?.message ?? eventsError.value?.message ?? '')
const eventByIri = computed(() => new Map(events.value.map((event) => [event['@id'], event])))
const timezoneLabel = computed(() => Intl.DateTimeFormat().resolvedOptions().timeZone)

const filteredSessions = computed(() => {
  const now = new Date()

  return sessions.value.filter((session) => {
    if (selectedFilter.value === 'upcoming') {
      return new Date(session.startsAt) >= now
    }

    if (selectedFilter.value === 'race') {
      return session.name.toLowerCase() === 'race'
    }

    return true
  })
})

const nextSession = computed(() => {
  const now = new Date()

  return sessions.value.find((session) => new Date(session.startsAt) >= now) ?? null
})

const nextSessionEvent = computed(() => nextSession.value ? getEvent(nextSession.value) : null)

function collectionMembers<T>(collection?: ApiCollection<T> | null): T[] {
  return collection?.member ?? collection?.['hydra:member'] ?? []
}

function getEvent(session: ApiSession): ApiEvent | undefined {
  return eventByIri.value.get(session.event)
}

function formatDate(value: string): string {
  return new Intl.DateTimeFormat(undefined, {
    month: 'short',
    day: '2-digit',
  }).format(new Date(value))
}

function formatDateLong(value: string): string {
  return new Intl.DateTimeFormat(undefined, {
    weekday: 'short',
    month: 'short',
    day: '2-digit',
  }).format(new Date(value))
}

function formatTime(value: string): string {
  return new Intl.DateTimeFormat(undefined, {
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value))
}
</script>
