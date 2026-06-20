FROM oven/bun:1 AS base
WORKDIR /app

COPY frontend/package.json frontend/bun.lock* ./

RUN bun install --frozen-lockfile --ignore-scripts

COPY frontend/ ./

FROM base AS build

ARG NUXT_DEVTOOLS=false
ENV NUXT_DEVTOOLS=$NUXT_DEVTOOLS

RUN bun --bun run build

FROM base AS development
WORKDIR /app

ENV NUXT_DEVTOOLS=true

EXPOSE 3000/tcp
ENTRYPOINT ["bun", "--bun", "run", "dev", "--host", "0.0.0.0", "--port", "3000"]

FROM oven/bun:1 AS production
WORKDIR /app

COPY --from=build /app/.output /app

EXPOSE 3000/tcp
ENTRYPOINT ["bun", "/app/server/index.mjs"]
