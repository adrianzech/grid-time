import { createError, getQuery, getRouterParam, setHeader } from 'h3'

const allowedQueryKeys: Record<string, readonly string[]> = {
  series: ['code', 'order[name]', 'page', 'itemsPerPage'],
  seasons: ['series.code', 'year', 'order[year]', 'page', 'itemsPerPage'],
  events: ['season.series.code', 'season.year', 'order[roundNumber]', 'page', 'itemsPerPage'],
  sessions: ['event.season.series.code', 'event.season.year', 'order[startsAt]', 'page', 'itemsPerPage'],
}

export default defineEventHandler(async (event) => {
  const resource = getRouterParam(event, 'resource')

  if (!resource || !(resource in allowedQueryKeys)) {
    throw createError({ statusCode: 404, statusMessage: 'Schedule resource not found.' })
  }

  const config = useRuntimeConfig(event)

  if (!config.internalApiBase || !config.frontendApiKey) {
    throw createError({ statusCode: 503, statusMessage: 'Schedule service is not configured.' })
  }

  const query = getQuery(event)
  const allowedQuery = Object.fromEntries(
    Object.entries(query).filter(([key, value]) => allowedQueryKeys[resource].includes(key) && typeof value === 'string'),
  )

  try {
    const backendBaseUrl = config.internalApiBase.replace(/\/api\/?$/, '').replace(/\/$/, '')
    const response = await $fetch(`${backendBaseUrl}/api/${resource}`, {
      headers: { 'X-API-Key': config.frontendApiKey },
      query: allowedQuery,
    })
    setHeader(event, 'Cache-Control', 'public, max-age=30, s-maxage=60, stale-while-revalidate=300')

    return response
  } catch (error) {
    console.error('Schedule backend request failed.', error)

    throw createError({ statusCode: 502, statusMessage: 'Schedule service is unavailable.' })
  }
})
