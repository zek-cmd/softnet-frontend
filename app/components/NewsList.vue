<template>
  <!--
    The whole section is skipped while there is nothing to show: no heading, no
    empty card grid, and no leftover gap between the sections around it.
  -->
  <section
    v-if="hasNews"
    class="relative overflow-hidden bg-gradient-to-b from-green-50/80 via-white to-green-50/80 py-16 sm:py-20"
    aria-labelledby="latest-news-heading"
  >
    <div class="pointer-events-none absolute inset-0 opacity-40" style="background-image: radial-gradient(circle, rgb(34, 197, 94) 1px, transparent 1px); background-size: 24px 24px;"></div>
    <div class="relative mx-auto w-[90%] px-6">
      <!-- Section header: left-aligned with the link on the right, so this
           section does not repeat the centred heading used further up. -->
      <div class="flex flex-col gap-5 border-b border-gray-200 pb-6 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h2 id="latest-news-heading" class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
            Latest News
          </h2>
        </div>
        <a
          :href="newsIndexUrl"
          class="inline-flex shrink-0 items-center gap-2 text-sm font-bold text-green-700 transition-colors hover:text-green-800"
        >
          View all news
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M5 12h14M12 5l7 7-7 7" />
          </svg>
        </a>
      </div>

      <!-- Lead story: image beside the text on wide screens. -->
      <article
        v-if="lead"
        class="mt-8 grid overflow-hidden rounded-xl border border-gray-200 bg-white lg:grid-cols-[1.05fr_1fr]"
      >
        <a
          v-if="lead.image"
          :href="articleUrl(lead)"
          class="block bg-gray-50"
          tabindex="-1"
          aria-hidden="true"
        >
          <img
            :src="imageUrl(lead.image)"
            :alt="lead.title"
            class="h-full max-h-[420px] min-h-[220px] w-full object-contain"
            loading="eager"
            decoding="async"
          >
        </a>
        <div class="flex flex-col justify-center p-7 sm:p-9">
          <span class="inline-flex w-fit items-center rounded-full bg-green-50 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.12em] text-green-800">
            {{ lead.category || 'News' }}
          </span>
          <h3 class="mt-4 text-2xl font-extrabold leading-tight tracking-tight text-gray-900 sm:text-3xl">
            <a :href="articleUrl(lead)" class="transition-colors hover:text-green-700">{{ lead.title }}</a>
          </h3>
          <p v-if="lead.summary" class="mt-3 max-w-[44ch] text-[15px] leading-relaxed text-gray-600">
            {{ lead.summary }}
          </p>
          <TagChips :tags="lead.tags" :base="newsIndexUrl" class="mt-4" />
          <div class="mt-6 flex flex-wrap items-center gap-4">
            <a
              :href="articleUrl(lead)"
              class="inline-flex items-center gap-2 rounded-lg bg-green-700 px-5 py-3 text-sm font-bold text-white transition-colors hover:bg-green-800"
            >
              Read the story
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M5 12h14M12 5l7 7-7 7" />
              </svg>
            </a>
            <span class="text-[13px] font-semibold text-gray-500">{{ lead.date_formatted }}</span>
          </div>
        </div>
      </article>

      <!-- The next few stories. -->
      <div v-if="rest.length" class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <article
          v-for="item in rest"
          :key="item.id"
          class="group flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white transition-all duration-200 hover:-translate-y-0.5 hover:border-green-300 hover:shadow-lg hover:shadow-green-900/5"
        >
          <a v-if="item.image" :href="articleUrl(item)" class="block bg-gray-50" tabindex="-1" aria-hidden="true">
            <img
              :src="imageUrl(item.image)"
              :alt="item.title"
              class="h-48 w-full object-contain"
              loading="lazy"
              decoding="async"
            >
          </a>
          <div class="flex flex-1 flex-col p-6">
            <span class="inline-flex w-fit items-center rounded-full bg-green-50 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.12em] text-green-800">
              {{ item.category || 'News' }}
            </span>
            <h3 class="mt-3 text-lg font-extrabold leading-snug tracking-tight text-gray-900">
              <a :href="articleUrl(item)" class="transition-colors group-hover:text-green-700">{{ item.title }}</a>
            </h3>
            <p v-if="item.summary" class="mt-2 line-clamp-3 text-sm leading-relaxed text-gray-600">
              {{ item.summary }}
            </p>
            <TagChips :tags="item.tags" :base="newsIndexUrl" class="mt-3" />
            <div class="mt-auto flex items-center justify-between gap-3 pt-5">
              <span class="text-[13px] font-semibold text-gray-500">{{ item.date_formatted }}</span>
              <button
                type="button"
                class="share-quiet"
                :aria-label="shareLabel(item)"
                @click="shareOnFacebook(item)"
              >
                <svg class="h-[15px] w-[15px]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                  <path d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.77-3.89 1.1 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0 0 22 12Z" />
                </svg>
              </button>
            </div>
          </div>
        </article>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
// The host holding the news comes from useNewsApi(), so this section, the news
// list and the news item page can never disagree about where the news is.
import { fetchNewsPage } from '~/utils/news-api'

const { api: apiBase, published } = useNewsApi()

// "View all news" and the tag chips belong to THIS site. They used to be
// absolute links to whatever host held the news — which was the same domain
// while the PHP backend served the news pages, but would now send a reader to
// the news backend, where there is no page to land on.
const newsIndexUrl = '/news'

// The stories are read ON THE SERVER, so the finished section arrives with the
// page: Google and a reader without JavaScript see the stories themselves
// rather than a space that fills in later.
const { data } = await useAsyncData('home-news', async () => {
  // The news lives on its own host. If it is unset or unreachable the section
  // stays hidden — that is intended, but the reason is logged so the news never
  // disappears without a trace.
  if (!apiBase) {
    console.warn('[NewsList] no news host is set — news section hidden.')
    return []
  }

  try {
    const page = await fetchNewsPage(apiBase, { limit: 10 })
    return Array.isArray(page.news) ? page.news : []
  } catch (error) {
    const reason = error instanceof Error ? error.message : String(error)
    console.warn('[NewsList] could not load news from ' + apiBase + ': ' + reason)
    return []
  }
})

const items = computed<any[]>(() => (Array.isArray(data.value) ? data.value : []))

// A row only counts as content when it has a title to show. Anything blank —
// a broken row, or a news item saved without fields — is left out, so a card
// is never printed empty.
const visibleItems = computed(() =>
  items.value.filter((item) => String(item?.title ?? '').trim() !== '')
)

// The newest story is shown large; the section header stays left-aligned.
const lead = computed(() => visibleItems.value[0] ?? null)
const rest = computed(() => visibleItems.value.slice(1, 4))

// Nothing at all is rendered while this is false.
const hasNews = computed(() => visibleItems.value.length > 0)

const imageUrl = (path: string) => apiBase + '/' + String(path).replace(/^\/+/, '')

const articleUrl = (item: any) =>
  item?.url ? item.url : '/news/' + item.slug

const shareLabel = (item: any) => `Share "${item?.title ?? ''}" on Facebook`

const shareOnFacebook = (item: any) => {
  // Sharing must hand out THIS site's address, not the backend's — /news now
  // belongs to this site.
  const url = published + articleUrl(item)
  const dialog =
    'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url)

  // A popup keeps the reader on our page; if the browser blocks it, the plain
  // share page still opens.
  window.open(dialog, 'softnet-share', 'width=640,height=640,noopener,noreferrer')
}

// The section hides itself while there is nothing to show. Nothing else is
// needed here: the stories were read above, before this rendered.
</script>

<style scoped>
.share-quiet {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 6px 9px;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: #6b7280;
  cursor: pointer;
  transition: background 0.18s, color 0.18s;
}

.share-quiet:hover {
  background: #e8f5ee;
  color: #0e7a4f;
}

.share-quiet:focus-visible {
  outline: 2px solid #0e7a4f;
  outline-offset: 2px;
}
</style>
