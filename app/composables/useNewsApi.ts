import { site } from '~/utils/site'

/**
 * Where the news comes from, and where this site is published.
 *
 * Both come from runtimeConfig, which is filled from NUXT_NEWS_API and
 * NUXT_SITE_URL when the site runs. app/utils/site.js holds the same two values
 * and is used as the fallback, so the project keeps working if those variables
 * are never set.
 *
 * This exists as one place because the value is needed in several components —
 * the home page news section, the news list, the news item page — and they must
 * all agree. If one of them read a different host, half the site would show one
 * school's news and half another's.
 *
 * They are two DIFFERENT things and must not be confused:
 *
 *   api       where the news is read from (the Strapi backend). Photos shown in
 *             a story live here too.
 *   published where THIS site is published. It is what Facebook and Google are
 *             told a story's address is, so it must never be the API's address —
 *             a reader who follows a shared link must land on the page.
 *
 * Trailing slashes are trimmed here, so every caller can join with '/'.
 */
export function useNewsApi() {
  const config = useRuntimeConfig()

  const api = String(config.public.newsApiUrl || site.newsApiUrl || '').replace(/\/+$/, '')

  const published = String(config.public.siteUrl || site.siteUrl || '').replace(/\/+$/, '')

  return { api, published }
}
