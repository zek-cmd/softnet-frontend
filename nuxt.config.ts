import tailwindcss from '@tailwindcss/vite'

// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-01-01',

  // Use the Nuxt 4 folder layout: srcDir becomes ./app, so pages/,
  // components/, layouts/ and assets/ live inside app/.
  future: { compatibilityVersion: 4 },

  devtools: { enabled: false },

  // main.css carries the design tokens and Tailwind. news.css holds the news
  // pages' own styles.
  css: ['~/assets/css/main.css', '~/assets/css/news.css'],

  // Tailwind v4 is NOT auto-detected by Nuxt 3 — the Vite plugin must be
  // registered by hand, otherwise no utility classes are generated.
  vite: {
    plugins: [tailwindcss()],
  },

  // Everything published with the site lives in static/.
  //
  // It used to be public/, because the site and the PHP backend shared one web
  // root: the PHP folder held the API, the admin and the site's own images, and
  // one upload put all of it on the host together. The site no longer needs any
  // of that. public/ still holds the retired PHP backend — kept for the live
  // x10hosting site while it is still on PHP — and it must never be published
  // with the site again: shipping it would put the API, the admin screens and
  // the database credentials' config file on the new host for nothing.
  //
  // So the site's own files (_sitc/images/...) were moved to static/, and this
  // line is what keeps the PHP folder out of the published site.
  dir: {
    public: 'static',
  },

  app: {
    head: {
      title: 'SOFTNET Information Technology Center, Inc.',
      htmlAttrs: { lang: 'en' },
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        {
          name: 'description',
          content:
            'SOFTNET Information Technology Center, Inc. — Senior High School and TESDA-based programs in Mariveles, Bataan. #WhereFunAndLearningConnect',
        },
        { name: 'theme-color', content: '#16a34a' },
        { property: 'og:title', content: 'SOFTNET Information Technology Center, Inc.' },
        {
          property: 'og:description',
          content: 'Senior High School and TESDA-based programs in Mariveles, Bataan.',
        },
        { property: 'og:type', content: 'website' },
        { property: 'og:image', content: '/_sitc/images/cover.webp' },
      ],
      link: [
        { rel: 'icon', type: 'image/webp', href: '/_sitc/images/favicon.webp' },
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400&display=swap',
        },
      ],
    },
  },

  nitro: {
    // The site is now served by Node, so a page is made when it is asked for.
    //
    // That single change is what retired the PHP piece. Facebook, Messenger and
    // Google do not run JavaScript: they read the HTML the server sends. While
    // this site was a fixed set of files, the only server that could write a
    // story's real title and cover photo into the <head> was PHP
    // (php-backend/news-view.php, now retired), because the story lives in a
    // database and a file
    // cannot look anything up. Now the page is built on the server on every
    // request, and the app writes those tags itself (see app/pages/news/
    // [slug].vue). One address, one design, always fresh — and no PHP.
    //
    // `node-server` is named explicitly (it is also the default for
    // `nuxt build`) so that a stray environment variable cannot quietly flip
    // the build back to a folder of static files — which would silently bring
    // back both faults: no share tags, and no /news/<slug> page at all.
    preset: 'node-server',

    // The two pages whose text never changes are still written to files at
    // build time, so they are served without work and cannot fail.
    //
    // The home page is NOT in this list on purpose: it shows the latest news.
    // Writing it at build time would either freeze that list or make the build
    // need the news API — the exact fault the old build had, where a slow host
    // produced a site full of "could not load" pages that still reported
    // success. It is rendered on request instead.
    prerender: {
      crawlLinks: false,
      routes: ['/about', '/contact'],
    },

    routeRules: {
      // The news pages are never baked into files. Each one is rendered when it
      // is opened, from whatever the news API holds at that moment, so a story
      // posted a minute ago already has its own page.
      '/news': { ssr: true },
      '/news/**': { ssr: true },
    },
  },

  runtimeConfig: {
    public: {
      // Where the news is read from, and where THIS site is published.
      //
      // These live here rather than in app/utils/site.js because they are
      // deployment settings, not site content — and because a build must be
      // able to point the whole app at another host (staging, or the copy of
      // the news backend running on this PC) without any file being edited:
      //
      //   $env:NUXT_NEWS_API = "http://localhost:1337"
      //   npm run build
      //
      // The pages read them through app/composables/useNewsApi.ts, so they can
      // never disagree about which host holds the news.
      //
      // TWO NAMES, ONE SETTING — and they are not two settings:
      //
      //   NUXT_PUBLIC_NEWS_API_URL   read when the site RUNS. Setting it on a
      //                              host takes effect on the next restart, with
      //                              no rebuild. This is the one to use for real.
      //   NUXT_NEWS_API              read when the site is BUILT (and by .env
      //                              during local work), and baked into the build
      //                              as the value above starts from.
      //
      // The run-time name is checked first, so a host that changes its address
      // does not also need a new build.
      //
      // No default: an empty value is honest, and the news pages say so. A
      // guess here would point a live site at somebody's laptop.
      newsApiUrl: process.env.NUXT_PUBLIC_NEWS_API_URL || process.env.NUXT_NEWS_API || '',
      siteUrl:
        process.env.NUXT_PUBLIC_SITE_URL ||
        process.env.NUXT_SITE_URL ||
        'https://sitci.x10.mx',
      web3formsKey: '',
    },
  },
})
