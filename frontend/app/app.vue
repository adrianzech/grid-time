<template>
  <div class="min-h-screen bg-carbon text-zinc-100 antialiased">
    <NuxtRouteAnnouncer />

    <main class="mx-auto flex min-h-screen w-full max-w-7xl flex-col px-4 py-4 sm:px-6 lg:px-8">
      <section
        v-if="quickLookItems.length"
        class="mb-4"
        aria-labelledby="quick-look-heading"
      >
        <div class="mb-3">
          <div>
            <p class="text-xs font-bold uppercase tracking-[0.22em] text-race-red">
              This weekend
            </p>
          </div>
        </div>

        <div class="-mx-4 flex snap-x gap-3 overflow-x-auto px-4 pb-2 sm:mx-0 sm:px-0">
          <button
            v-for="item in quickLookItems"
            :key="`${item.series.code}-${item.event['@id']}`"
            type="button"
            class="w-72 shrink-0 snap-start rounded-lg border border-white/10 bg-panel p-4 text-left shadow-xl shadow-black/20 transition hover:border-race-red/50 hover:bg-panel-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-race-red"
            :class="item.session && isSessionLive(item.session, now) ? 'border-race-red/60 bg-race-red/10 hover:border-race-red hover:bg-race-red/15' : ''"
            @click="openQuickLookItem(item)"
          >
            <span class="flex items-start justify-between gap-3">
              <span>
                <span class="mt-1 block text-sm font-bold text-zinc-300">
                  {{ item.series.name }}
                </span>
              </span>
              <ChevronDown :size="18" class="mt-1 text-zinc-500" />
            </span>
            <span
              class="mt-5 block truncate text-lg font-black text-white"
              :title="formatEventTitle(item.event)"
            >
              {{ formatEventTitle(item.event) }}
            </span>
            <span
              v-if="item.session"
              class="mt-1 block text-xs font-semibold uppercase tracking-wide text-zinc-500"
            >
              {{ formatSessionDateLong(item.session) }}
            </span>
            <span class="mt-3 flex items-end justify-between gap-3">
              <span class="min-w-0">
                <span class="block truncate text-sm font-bold text-zinc-300">
                  {{ item.session ? item.session.name : 'Weekend complete' }}
                </span>
                <span
                  class="mt-1 block text-xs"
                  :class="item.session && isSessionLive(item.session, now) ? 'font-semibold text-race-red' : 'text-zinc-500'"
                >
                  {{ item.session && isSessionLive(item.session, now) ? 'Live now' : 'Next session' }}
                </span>
              </span>
              <span class="shrink-0 text-right text-lg font-black tabular-nums text-white">
                {{ item.session ? formatSessionTime(item.session) : '—' }}
              </span>
            </span>
          </button>
        </div>
      </section>

      <section
        v-else-if="isQuickLookReady"
        class="mb-4 rounded-lg border border-white/10 bg-panel p-4 text-sm text-zinc-500"
      >
        No race weekends are scheduled for this weekend.
      </section>

      <header class="flex flex-col gap-5 border-b border-white/10 py-5 md:flex-row md:items-end md:justify-between">
        <div class="space-y-3">
          <div class="flex items-center gap-3">
            <span class="text-xs font-semibold uppercase tracking-[0.26em] text-race-red">Grid Time</span>
          </div>

          <div class="max-w-5xl">
            <div class="inline-grid grid-cols-3 gap-1 rounded-lg border border-white/10 bg-panel p-2 shadow-xl shadow-black/20">
              <button
                v-for="category in seriesCategories"
                :key="category"
                type="button"
                class="rounded-md px-4 py-2 text-sm font-bold transition"
                :class="category === selectedCategory ? 'bg-race-red text-white shadow-lg shadow-race-red/20' : 'text-zinc-400 hover:bg-white/4'"
                :aria-pressed="category === selectedCategory"
                @click="selectCategory(category)"
              >
                {{ category }}
              </button>
            </div>

            <div class="-mx-4 mt-3 flex flex-nowrap gap-2 overflow-x-auto px-4 pb-2 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0 sm:pb-0">
              <button
                v-for="series in visibleSeries"
                :key="series.code"
                type="button"
                class="flex h-14 w-36 shrink-0 items-center rounded-lg border px-4 text-left transition sm:h-[77px] sm:w-auto sm:min-w-32 sm:flex-none"
                :class="series.code === selectedSeriesCode ? 'border-race-red bg-race-red text-white shadow-lg shadow-race-red/20' : 'border-white/10 bg-panel text-zinc-400 shadow-xl shadow-black/20 hover:border-white/25 hover:bg-panel-soft hover:text-white'"
                :aria-pressed="series.code === selectedSeriesCode"
                @click="selectSeries(series.code)"
              >
                <span class="block text-base font-black sm:text-lg">
                  {{ series.name }}
                </span>
              </button>
            </div>
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

      <section class="py-5">
        <section class="mb-4 rounded-lg border border-race-red/25 bg-race-red/10 p-4 shadow-2xl shadow-race-red/10 sm:p-5">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="min-w-0">
              <p class="text-xs font-bold uppercase tracking-[0.22em] text-race-red">
                Next up
              </p>
              <h2
                class="mt-3 max-w-3xl text-2xl font-black text-white sm:text-3xl"
                :title="nextSessionEvent?.name"
              >
                {{ nextSessionEvent ? formatEventTitle(nextSessionEvent) : 'No upcoming session' }}
              </h2>
              <p class="mt-3 text-xl font-black text-white sm:text-2xl">
                {{ nextSession?.name ?? 'Schedule unavailable' }}
              </p>
            </div>

            <div class="lg:min-w-64 lg:text-right">
              <p class="text-sm text-zinc-400">
                {{ nextSession ? formatSessionDateLong(nextSession) : '-' }}
              </p>
              <p class="mt-1 text-4xl font-black tabular-nums text-white">
                {{ nextSession ? formatSessionTime(nextSession) : '--:--' }}
              </p>
            </div>
          </div>
        </section>

        <div
          ref="raceWeekendsSection"
          class="overflow-hidden rounded-lg border border-white/10 bg-panel shadow-2xl shadow-black/20"
        >
          <div class="border-b border-white/10 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h2 class="text-lg font-bold text-white">
                  Race weekends
                </h2>
                <p class="text-sm text-zinc-500">
                  Current and upcoming {{ selectedSeries.name }} weekends
                  <span
                    v-if="isRefreshing"
                    class="ml-2 text-race-red"
                  >
                    Updating...
                  </span>
                </p>
              </div>

              <div class="grid grid-cols-2 gap-1 rounded-lg border border-white/10 bg-black/20 p-1">
                <button
                  v-for="mode in timeModes"
                  :key="mode.value"
                  type="button"
                  class="rounded-md px-3 py-2 text-sm font-bold transition"
                  :class="timeMode === mode.value ? 'bg-race-red text-white shadow-lg shadow-race-red/20' : 'text-zinc-400 hover:bg-white/4'"
                  @click="selectTimeMode(mode.value)"
                >
                  {{ mode.label }}
                </button>
              </div>
            </div>
          </div>

          <Transition
            mode="out-in"
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-1"
          >
            <div
              v-if="pending"
              :key="`${selectedSeriesCode}-loading`"
              class="grid min-h-80 place-items-center p-6 text-zinc-400"
            >
              Loading schedule...
            </div>

            <div
              v-else-if="errorMessage"
              :key="`${selectedSeriesCode}-error`"
              class="m-4 rounded-lg border border-race-red/40 bg-race-red/10 p-4 text-sm text-red-100"
            >
              {{ errorMessage }}
            </div>

            <div
              v-else-if="selectedEvent"
              :key="`${selectedSeriesCode}-events`"
              class="divide-y divide-white/10"
            >
              <div
                v-if="refreshErrorMessage"
                class="border-b border-race-red/30 bg-race-red/10 p-3 text-sm text-red-100"
              >
                {{ refreshErrorMessage }}
              </div>

              <section
                v-for="event in upcomingEvents"
                :key="event['@id']"
                class="bg-panel"
              >
                <button
                  type="button"
                  class="grid w-full gap-4 p-4 text-left transition hover:bg-white/3 sm:grid-cols-[132px_minmax(0,1fr)_140px]"
                  :class="isEventExpanded(event) ? 'bg-white/2' : ''"
                  @click="toggleEvent(event)"
                >
                  <span class="block">
                    <span class="block text-sm font-black uppercase tracking-wide text-race-red">
                      {{ formatEventDateRange(event) }}
                    </span>
                    <span class="mt-2 block text-xs text-zinc-500">
                      Round {{ event.roundNumber }}
                    </span>
                  </span>

                  <span class="block min-w-0">
                    <span class="flex min-w-0 flex-wrap items-center gap-2">
                      <span
                        class="truncate text-xl font-black text-white"
                        :title="formatEventTitle(event)"
                      >
                        {{ formatEventTitle(event) }}
                      </span>
                      <span
                        v-if="isEventActive(event, now)"
                        class="rounded bg-race-red px-2 py-1 text-xs font-bold uppercase text-white"
                      >
                        Live weekend
                      </span>
                    </span>
                    <span class="mt-2 block text-sm text-zinc-400">
                      {{ event.location }}
                      <span class="text-zinc-600">/</span>
                      {{ eventSessions(event).length }} sessions
                    </span>
                  </span>

                  <span class="flex items-center justify-between gap-3 sm:justify-end">
                    <span class="text-right">
                      <span class="block text-sm font-semibold text-zinc-300">
                        {{ eventNextSessionLabel(event) }}
                      </span>
                    </span>
                    <span
                      class="grid size-9 place-items-center rounded-md bg-white/5 text-lg text-white transition"
                      :class="isEventExpanded(event) ? 'rotate-180 bg-race-red/20 text-race-red' : ''"
                    >
                      <ChevronDown :size="18" />
                    </span>
                  </span>
                </button>

                <div
                  v-if="isEventExpanded(event)"
                  class="border-t border-white/10 bg-black/10"
                >
                  <div
                    v-if="eventSessions(event).length"
                    class="divide-y divide-white/10"
                  >
                    <article
                      v-for="session in eventSessions(event)"
                      :key="session['@id']"
                      class="grid gap-4 border-l-2 p-4 transition md:grid-cols-[112px_minmax(0,1fr)_150px]"
                      :class="sessionRowClass(session)"
                    >
                      <div class="flex items-center gap-3 md:items-start">
                        <div class="min-w-16">
                          <div
                            class="text-xs font-black uppercase tracking-wide"
                            :class="isSessionCompleted(session) ? 'text-zinc-600' : 'text-race-red'"
                          >
                            {{ formatSessionWeekday(session) }}
                          </div>
                          <div
                            class="mt-1 text-3xl font-black leading-none tabular-nums"
                            :class="isSessionCompleted(session) ? 'text-zinc-500' : 'text-white'"
                          >
                            {{ formatSessionDay(session) }}
                          </div>
                          <div class="mt-1 text-xs font-bold uppercase tracking-wide text-zinc-500">
                            {{ formatSessionMonth(session) }}
                          </div>
                        </div>
                        <div class="hidden h-12 w-px bg-white/10 md:block" />
                        <div class="text-xs font-semibold uppercase tracking-wide text-race-red md:hidden">
                          {{ formatSessionDateLong(session) }}
                        </div>
                      </div>

                      <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                          <p
                            class="text-base font-black"
                            :class="isSessionCompleted(session) ? 'text-zinc-500' : 'text-white'"
                          >
                            {{ session.name }}
                          </p>
                          <span
                            class="rounded px-2 py-1 text-xs font-bold uppercase"
                            :class="sessionStatusBadgeClass(session)"
                          >
                            {{ sessionStatusLabel(session) }}
                          </span>
                        </div>
                        <p
                          v-if="isNextSession(session)"
                          class="mt-2 text-sm font-semibold text-race-red"
                        >
                          Next session
                        </p>
                      </div>

                      <div class="flex items-center justify-between gap-3 md:flex-col md:items-end md:justify-center">
                        <div class="text-right">
                          <p
                            class="text-3xl font-black tabular-nums"
                            :class="isSessionCompleted(session) ? 'text-zinc-500' : 'text-white'"
                          >
                            {{ formatSessionTime(session) }}
                          </p>
                          <p class="mt-1 text-sm text-zinc-500">
                            {{ session.endsAt ? `until ${formatSessionTime(session, session.endsAt)}` : '' }}
                          </p>
                        </div>
                        <a
                          :href="session.sourceUrl"
                          target="_blank"
                          rel="noreferrer"
                          class="text-xs font-semibold text-zinc-500 transition hover:text-race-red"
                          @click.stop
                        >
                          Official source
                        </a>
                      </div>
                    </article>
                  </div>

                  <div
                    v-else
                    class="p-4 text-sm text-zinc-500"
                  >
                    No sessions match this filter.
                  </div>
                </div>
              </section>
            </div>

            <div
              v-else
              :key="`${selectedSeriesCode}-empty`"
              class="grid min-h-80 place-items-center p-6 text-zinc-400"
            >
              No upcoming race weekend found.
            </div>
          </Transition>
        </div>
      </section>
    </main>
  </div>
</template>

<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next'
import type { ApiEvent, ApiSession, ScheduleCacheEntry } from '~/composables/useScheduleCache'

const seasonYear = 2026
const expandedEventIds = ref<Set<string>>(new Set())
const now = ref(new Date())
const raceWeekendsSection = ref<HTMLElement | null>(null)
const pendingExpandedEventId = ref<string | null>(null)
let clock: ReturnType<typeof window.setInterval> | null = null

const seriesCategories = ['Formula', 'Moto', 'Superbike'] as const

type SeriesCategory = typeof seriesCategories[number]

const availableSeries = [
  {
    code: 'F1',
    name: 'Formula 1',
    category: 'Formula',
  },
  {
    code: 'F2',
    name: 'Formula 2',
    category: 'Formula',
  },
  {
    code: 'F3',
    name: 'Formula 3',
    category: 'Formula',
  },
  {
    code: 'MGP',
    name: 'MotoGP',
    category: 'Moto',
  },
  {
    code: 'MT2',
    name: 'Moto2',
    category: 'Moto',
  },
  {
    code: 'MT3',
    name: 'Moto3',
    category: 'Moto',
  },
  {
    code: 'SBK',
    name: 'WorldSBK',
    category: 'Superbike',
  },
] as const

type SeriesCode = typeof availableSeries[number]['code']
type TimeMode = 'local' | 'track'
type AvailableSeries = typeof availableSeries[number]
type QuickLookItem = {
  series: AvailableSeries
  event: ApiEvent
  session: ApiSession | null
}

const selectedSeriesCode = ref<SeriesCode>('F1')
const selectedCategory = ref<SeriesCategory>('Formula')
const selectedSeriesByCategory = reactive<Record<SeriesCategory, SeriesCode>>({
  Formula: 'F1',
  Moto: 'MGP',
  Superbike: 'SBK',
})
const visibleSeries = computed(() => availableSeries.filter((series) => series.category === selectedCategory.value))
const selectedSeries = computed(() => availableSeries.find((series) => series.code === selectedSeriesCode.value) ?? availableSeries[0])
const timeMode = ref<TimeMode>('local')
const scheduleCache = useScheduleCache(availableSeries.map((series) => series.code), 'F1', seasonYear)
const emptySchedule: ScheduleCacheEntry = {
  events: [],
  sessions: [],
  scheduleUpdatedAt: null,
  status: 'idle',
  error: '',
}

const timeModes = [
  {
    value: 'local',
    label: 'My Time',
  },
  {
    value: 'track',
    label: 'Track Time',
  },
] as const

const activeSchedule = computed<ScheduleCacheEntry>(() => scheduleCache.cache.value[selectedSeriesCode.value] ?? emptySchedule)
const hasScheduleData = computed(() => activeSchedule.value.events.length > 0 || activeSchedule.value.sessions.length > 0)
const sessions = computed(() => activeSchedule.value.sessions)
const events = computed(() => activeSchedule.value.events)
const pending = computed(() => activeSchedule.value.status === 'loading' && !hasScheduleData.value)
const isRefreshing = computed(() => activeSchedule.value.status === 'refreshing')
const errorMessage = computed(() => hasScheduleData.value ? '' : activeSchedule.value.error)
const refreshErrorMessage = computed(() => hasScheduleData.value ? activeSchedule.value.error : '')
const eventByIri = computed(() => new Map(events.value.map((event) => [event['@id'], event])))
const sessionsByEvent = computed(() => {
  const grouped = new Map<string, ApiSession[]>()

  for (const session of sessions.value) {
    grouped.set(session.event, [...(grouped.get(session.event) ?? []), session])
  }

  return grouped
})
const weekendWindow = computed(() => getWeekendWindow(now.value))
const quickLookItems = computed<QuickLookItem[]>(() => availableSeries.flatMap((series) => {
  const schedule = scheduleCache.cache.value[series.code] ?? emptySchedule
  const event = schedule.events.find((candidate) => eventOverlapsWindow(candidate, schedule.sessions, weekendWindow.value))

  if (!event) {
    return []
  }

  const eventSessions = schedule.sessions.filter((session) => session.event === event['@id'])
  const session = eventSessions.find((candidate) => isSessionLive(candidate, now.value))
    ?? eventSessions.find((candidate) => new Date(candidate.startsAt) > now.value)
    ?? null

  return [{ series, event, session }]
}).sort((left, right) => {
  if (left.session && right.session) {
    return new Date(left.session.startsAt).getTime() - new Date(right.session.startsAt).getTime()
      || left.series.code.localeCompare(right.series.code)
  }

  if (left.session) {
    return -1
  }

  if (right.session) {
    return 1
  }

  return left.series.code.localeCompare(right.series.code)
}))
const isQuickLookReady = computed(() => availableSeries.every((series) => {
  const status = scheduleCache.cache.value[series.code]?.status

  return status === 'ready' || status === 'error'
}))

const selectedEvent = computed(() => {
  return events.value.find((event) => isEventActive(event, now.value))
    ?? events.value.find((event) => isEventUpcoming(event, now.value))
    ?? null
})

const upcomingEvents = computed(() => {
  return events.value.filter((event) => isEventActive(event, now.value) || isEventUpcoming(event, now.value))
})

const liveSession = computed(() => {
  return sessions.value.find((session) => isSessionLive(session, now.value)) ?? null
})

const nextFutureSession = computed(() => {
  return sessions.value.find((session) => new Date(session.startsAt) > now.value) ?? null
})

const highlightedSession = computed(() => liveSession.value ?? nextFutureSession.value)
const nextSession = computed(() => highlightedSession.value)

const nextSessionEvent = computed(() => nextSession.value ? getEvent(nextSession.value) : null)

watch(upcomingEvents, expandFirstUpcomingEvent, { immediate: true })

watch(selectedSeriesCode, () => {
  expandedEventIds.value = new Set()
  void scheduleCache.loadSeries(selectedSeriesCode.value)
  void nextTick(() => {
    const eventId = pendingExpandedEventId.value

    if (eventId) {
      expandedEventIds.value = new Set([eventId])
      pendingExpandedEventId.value = null
      raceWeekendsSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })

      return
    }

    expandFirstUpcomingEvent()
  })
})

onMounted(() => {
  void scheduleCache.initialize()
  window.addEventListener('focus', refreshSelectedSeries)
  clock = window.setInterval(() => {
    now.value = new Date()
  }, 60_000)
})

onBeforeUnmount(() => {
  window.removeEventListener('focus', refreshSelectedSeries)

  if (clock !== null) {
    window.clearInterval(clock)
  }
})

function refreshSelectedSeries(): void {
  void scheduleCache.loadSeries(selectedSeriesCode.value)
}

function getEvent(session: ApiSession): ApiEvent | undefined {
  return eventByIri.value.get(session.event)
}

function eventSessions(event: ApiEvent): ApiSession[] {
  return sessionsByEvent.value.get(event['@id']) ?? []
}

function expandFirstUpcomingEvent(): void {
  const event = upcomingEvents.value[0]

  if (event && !expandedEventIds.value.size) {
    expandedEventIds.value = new Set([event['@id']])
  }
}

function isEventExpanded(event: ApiEvent): boolean {
  return expandedEventIds.value.has(event['@id'])
}

function toggleEvent(event: ApiEvent): void {
  const nextExpandedEventIds = new Set(expandedEventIds.value)

  if (nextExpandedEventIds.has(event['@id'])) {
    nextExpandedEventIds.delete(event['@id'])
  } else {
    nextExpandedEventIds.add(event['@id'])
  }

  expandedEventIds.value = nextExpandedEventIds
}

function openQuickLookItem(item: QuickLookItem): void {
  const changesSeries = selectedSeriesCode.value !== item.series.code

  pendingExpandedEventId.value = item.event['@id']
  selectSeries(item.series.code)

  if (!changesSeries) {
    expandedEventIds.value = new Set([item.event['@id']])
    pendingExpandedEventId.value = null

    void nextTick(() => {
      raceWeekendsSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
    })
  }
}

function selectSeries(code: SeriesCode): void {
  selectedSeriesCode.value = code

  const series = availableSeries.find((candidate) => candidate.code === code)

  if (series) {
    selectedCategory.value = series.category
    selectedSeriesByCategory[series.category] = code
  }
}

function selectCategory(category: SeriesCategory): void {
  selectedCategory.value = category
  selectedSeriesCode.value = selectedSeriesByCategory[category]
}

function selectTimeMode(mode: TimeMode): void {
  timeMode.value = mode
}

function isEventActive(event: ApiEvent, date: Date): boolean {
  const window = eventWindow(event)

  return window ? date >= window.start && date <= window.end : false
}

function isEventUpcoming(event: ApiEvent, date: Date): boolean {
  const window = eventWindow(event)

  return window ? window.end >= date : false
}

function eventWindow(event: ApiEvent): { start: Date, end: Date } | null {
  const eventDates = eventSessions(event)
    .flatMap((session) => [new Date(session.startsAt), session.endsAt ? new Date(session.endsAt) : new Date(session.startsAt)])
    .filter((date) => !Number.isNaN(date.getTime()))

  if (!eventDates.length) {
    return null
  }

  return {
    start: new Date(Math.min(...eventDates.map((date) => startOfLocalDay(date).getTime()))),
    end: new Date(Math.max(...eventDates.map((date) => endOfLocalDay(date).getTime()))),
  }
}

function startOfLocalDay(date: Date): Date {
  const start = new Date(date)
  start.setHours(0, 0, 0, 0)

  return start
}

function endOfLocalDay(date: Date): Date {
  const end = new Date(date)
  end.setHours(23, 59, 59, 999)

  return end
}

function getWeekendWindow(date: Date): { start: Date, end: Date } {
  const start = startOfLocalDay(date)
  const day = start.getDay()
  const daysToFriday = day === 0 ? -2 : 5 - day

  start.setDate(start.getDate() + daysToFriday)

  const end = endOfLocalDay(start)
  end.setDate(end.getDate() + 2)

  return { start, end }
}

function eventOverlapsWindow(event: ApiEvent, sessions: ApiSession[], window: { start: Date, end: Date }): boolean {
  return sessions.some((session) => {
    if (session.event !== event['@id']) {
      return false
    }

    const sessionStart = new Date(session.startsAt)
    const sessionEnd = sessionEndDate(session)

    return sessionStart <= window.end && sessionEnd >= window.start
  })
}

function formatSessionDay(session: ApiSession): string {
  return formatSessionDatePart(session.startsAt, session, { day: '2-digit' })
}

function formatSessionWeekday(session: ApiSession): string {
  return formatSessionDatePart(session.startsAt, session, { weekday: 'short' })
}

function formatSessionMonth(session: ApiSession): string {
  return formatSessionDatePart(session.startsAt, session, { month: 'short' })
}

function formatSessionDateLong(session: ApiSession): string {
  return formatSessionDatePart(session.startsAt, session, {
    weekday: 'short',
    month: 'short',
    day: '2-digit',
  })
}

function formatEventTitle(event: ApiEvent): string {
  const title = event.countryName || event.name || event.location

  return title === title.toUpperCase()
    ? title.toLocaleLowerCase().replace(/\b\w/g, (character) => character.toLocaleUpperCase())
    : title
}

function eventNextSessionLabel(event: ApiEvent): string {
  const eventSession = eventSessions(event).find((session) => isSessionLive(session, now.value))
    ?? eventSessions(event).find((session) => new Date(session.startsAt) > now.value)

  return eventSession ? `${eventSession.name} ${formatSessionTime(eventSession)}` : 'Weekend complete'
}

function isNextSession(session: ApiSession): boolean {
  return nextFutureSession.value?.['@id'] === session['@id'] && !liveSession.value
}

function sessionEndDate(session: ApiSession): Date {
  return session.endsAt ? new Date(session.endsAt) : new Date(session.startsAt)
}

function isSessionCompleted(session: ApiSession, date = new Date()): boolean {
  return sessionEndDate(session) <= date
}

function isSessionLive(session: ApiSession, date = new Date()): boolean {
  if (!session.endsAt) {
    return false
  }

  const sessionStart = new Date(session.startsAt)
  const sessionEnd = new Date(session.endsAt)

  return sessionStart <= date && sessionEnd > date
}

function sessionStatusLabel(session: ApiSession): string {
  if (isSessionLive(session)) {
    return 'Live'
  }

  if (isSessionCompleted(session)) {
    return 'Completed'
  }

  if (isNextSession(session)) {
    return 'Next'
  }

  return 'Upcoming'
}

function sessionStatusBadgeClass(session: ApiSession): string {
  if (isSessionLive(session)) {
    return 'bg-race-red text-white shadow-lg shadow-race-red/20'
  }

  if (isSessionCompleted(session)) {
    return 'bg-white/5 text-zinc-500'
  }

  if (isNextSession(session)) {
    return 'bg-race-red text-white shadow-lg shadow-race-red/20'
  }

  return 'bg-white/10 text-zinc-300'
}

function sessionRowClass(session: ApiSession): string {
  if (isSessionLive(session)) {
    return 'border-l-race-red bg-race-red/5 hover:bg-race-red/10'
  }

  if (isSessionCompleted(session)) {
    return 'border-l-transparent bg-black/5 opacity-70 hover:bg-white/2'
  }

  if (isNextSession(session)) {
    return 'border-l-race-red bg-race-red/5 hover:bg-race-red/10'
  }

  return 'border-l-transparent hover:bg-white/3'
}

function formatSessionTime(session: ApiSession, value = session.startsAt): string {
  return formatSessionDatePart(value, session, {
    hour: '2-digit',
    minute: '2-digit',
  })
}

function formatSessionDatePart(value: string, session: ApiSession, options: Intl.DateTimeFormatOptions): string {
  const trackOffsetMinutes = parseTimezoneOffset(session.trackTimezoneOffset)
  const useTrackTime = timeMode.value === 'track' && trackOffsetMinutes !== null

  return new Intl.DateTimeFormat(undefined, {
    ...options,
    ...(useTrackTime ? { timeZone: 'UTC' } : {}),
  }).format(useTrackTime ? new Date(new Date(value).getTime() + trackOffsetMinutes * 60_000) : new Date(value))
}

function formatEventDateRange(event: ApiEvent): string {
  const sessions = eventSessions(event)
  const firstSession = sessions[0]
  const lastSession = sessions.at(-1)

  if (!firstSession || !lastSession) {
    return `R${event.roundNumber}`
  }

  const lastSessionEnd = lastSession.endsAt ?? lastSession.startsAt
  const startDay = formatSessionDatePart(firstSession.startsAt, firstSession, { day: '2-digit' })
  const endDay = formatSessionDatePart(lastSessionEnd, lastSession, { day: '2-digit' })
  const month = formatSessionDatePart(lastSessionEnd, lastSession, { month: 'short' })

  return sameDisplayDay(firstSession.startsAt, firstSession, lastSessionEnd, lastSession) ? `${endDay} ${month}` : `${startDay}-${endDay} ${month}`
}

function sameDisplayDay(firstValue: string, firstSession: ApiSession, secondValue: string, secondSession: ApiSession): boolean {
  return displayDateKey(firstValue, firstSession) === displayDateKey(secondValue, secondSession)
}

function displayDateKey(value: string, session: ApiSession): string {
  const useTrackTime = timeMode.value === 'track' && parseTimezoneOffset(session.trackTimezoneOffset) !== null
  const parts = new Intl.DateTimeFormat('en', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    ...(useTrackTime ? { timeZone: 'UTC' } : {}),
  }).formatToParts(displayDate(value, session))
  const year = parts.find((part) => part.type === 'year')?.value ?? ''
  const month = parts.find((part) => part.type === 'month')?.value ?? ''
  const day = parts.find((part) => part.type === 'day')?.value ?? ''

  return `${year}-${month}-${day}`
}

function displayDate(value: string, session: ApiSession): Date {
  const trackOffsetMinutes = parseTimezoneOffset(session.trackTimezoneOffset)

  if (timeMode.value !== 'track' || trackOffsetMinutes === null) {
    return new Date(value)
  }

  return new Date(new Date(value).getTime() + trackOffsetMinutes * 60_000)
}

function parseTimezoneOffset(value: string | null): number | null {
  if (!value) {
    return null
  }

  const match = value.match(/^(?<sign>[+-])(?<hours>\d{2}):(?<minutes>\d{2})$/)

  if (!match?.groups) {
    return null
  }

  const multiplier = match.groups.sign === '-' ? -1 : 1

  return multiplier * (Number(match.groups.hours) * 60 + Number(match.groups.minutes))
}
</script>
