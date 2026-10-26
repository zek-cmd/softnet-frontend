# SOFTNET Information Technology Center — Website

The public website of SOFTNET Information Technology Center, Inc., a Senior High School and
TESDA-based institution in Mariveles, Bataan.

## Overview

The site presents the school, its programs and facilities, and publishes the school's news.

- **Home** — welcome, programs, facilities and the latest news
- **About** — the school's history, mission, vision and hymn
- **Contact** — address, map and an enquiry form
- **News** — story list, tag filter, search, paging, and one page per story

The site is built with **Nuxt** (a Vue framework) and is server-rendered, so news pages show
real titles and photos to Google and to Facebook/Messenger link previews. The news content is
read from a separate **Strapi** backend, where staff write and publish stories.

## Dependencies

Runtime (installed with `npm install`):

| Package | Version | Purpose |
|---|---|---|
| `nuxt` | ^3.14.0 | The web framework (pages, server rendering, build) |
| `vue` | ^3.5.13 | The UI library Nuxt is built on |

Development (build-time only):

| Package | Version | Purpose |
|---|---|---|
| `tailwindcss` | ^4.0.0 | Utility-first CSS styling |
| `@tailwindcss/vite` | ^4.0.0 | Connects Tailwind to the Vite build |

Requirements: **Node.js 20 or newer**.

## Scripts

```powershell
npm install      # install dependencies
npm run dev      # run locally for development
npm run build    # build for production
npm run preview  # preview the production build
npm start        # run the built site (node .output/server/index.mjs)
```

## Configuration

Create a `.env` file with the news backend address and this site's address:

```
NUXT_PUBLIC_NEWS_API_URL=http://localhost:1337
NUXT_PUBLIC_SITE_URL=http://localhost:3000
```

## License

Released under the [MIT License](LICENSE).

Copyright (c) 2026 SOFTNET Information Technology Center, Inc.
