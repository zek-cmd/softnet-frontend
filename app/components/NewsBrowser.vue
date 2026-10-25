<script setup lang="ts">
// The news list itself: the toolbar, the lead story, the grid and the paging.
//
// It is a component rather than a page because the list exists at more than one
// address — /news and /news/page/2 and so on — and all of them show the same
// thing with a different page number.
//
// Not one of those addresses is written to a file at build time. The page is
// rendered on the server when it is opened, so what a visitor sees is always
// what the news API holds right now, and a story posted a minute ago is on the
// list without anything being rebuilt.
//
// The page number and the tag therefore come from the ADDRESS BAR, which is the
// one thing this component cannot get from the API.

import { fetchNewsPage } from '~/utils/news-api'

const { api: apiBase, published } = useNewsApi()

const route = useRoute()

const props = withDefaults(
  defineProps<{
    initialPage?: number
    perPage?: number
  }>(),
  { initialPage: 1, perPage: 9 }
)

type NewsItem = {
  id: number
  title: string
  summary?: string
  category?: string
  tags?: string[]
  image?: string
  url?: string
  date_formatted?: string
  gallery_count?: number
}

type TagCount = { tag: string; count: number }

const rows = ref<NewsItem[]>([])
const tagCounts = ref<TagCount[]>([])
const totalNews = ref(0)
const totalPages = ref(1)
const search = ref('')
const loading = ref(false)

// Set when the backend could not be reached at all. It is kept apart from "there
// is no news": "Check back soon!" would tell the reader the school posted
// nothing, when the truth is that the news could not be loaded.
const loadFailed = ref(false)

/**
 * Which page of news this address shows.
 *
 * The page number travels in the address: /news/page/3 is page 3. The prop is
 * only a fallback for a caller with no address to read.
 */
function pageFromRoute(): number {
  const raw = Array.isArray(route.params.page) ? route.params.page[0] : route.params.page
  const number = Number(raw)
  if (Number.isFinite(number) && number > 1) return Math.floor(number)

  // /news?page=3 also works, so a link written by hand is not simply ignored.
  const query = Number(route.query.page)
  if (Number.isFinite(query) && query > 1) return Math.floor(query)

  return Math.max(1, props.initialPage)
}

const page = ref(pageFromRoute())

/**
 * The tag this address is filtering by, if any.
 *
 * This is what makes the tag chips on a story page work: they link to
 * /news?tag=sports, so the list opens already filtered instead of showing
 * everything.
 */
const activeTag = ref(String(route.query.tag ?? '').trim())

/**
 * Ask for one page of news.
 *
 * The address the reader is on decides which page and which tag — that is the
 * one thing the news feed cannot tell us.
 */
const load = (options: { search?: string } = {}) =>
  fetchNewsPage(apiBase, {
    limit: props.perPage,
    page: page.value,
    tag: activeTag.value,
    search: options.search ?? '',
  })

/**
 * The first page, read while this renders on the server.
 *
 * Doing it here rather than in the browser means the reader gets the finished
 * list in the first paint instead of an empty screen — and it means Google, and
 * a reader with JavaScript switched off, sees a real list rather than nothing.
 *
 * The cache key carries the tag as well as the page: with only the page in it,
 * a filtered page 2 would be handed the unfiltered page 2 from the cache.
 */
const { data: firstPage } = await useAsyncData(
  () => `news-list-${page.value}-${props.perPage}-${activeTag.value}`,
  async () => {
    try {
      return await load()
    } catch (error) {
      // A news backend that cannot be reached must not be hidden from the
      // reader. The reason is logged, and the empty state below says so plainly.
      const reason = error instanceof Error ? error.message : String(error)
      console.error('[news] could not load page ' + page.value + ' from ' + apiBase + ': ' + reason)
      return null
    }
  }
)

function apply(data: any) {
  if (!data) {
    loadFailed.value = true
    return
  }

  loadFailed.value = false

  const list = data?.news ?? data?.posts
  rows.value = Array.isArray(list) ? list : []
  totalNews.value = Number(data?.total ?? rows.value.length)
  totalPages.value = Number(data?.pages ?? 1)
  if (Array.isArray(data?.tags)) tagCounts.value = data.tags
}

apply(firstPage.value)

// A news backend that cannot be reached is not an empty news page.
//
// Saying "200 — the school has published nothing" would let Google keep an
// address that says so, and would tell a reader something untrue. This is sent
// from the server, so a crawler that runs no JavaScript still sees it.
if (import.meta.server && loadFailed.value) {
  setResponseStatus(503)
}

// The page was rendered on the server, so the list is already here. The one
// case that still needs the browser is a tag that arrived in the address (a
// chip on a story page links to /news?tag=sports): it is already on, and the
// list is fetched here to match it.
if (!firstPage.value || activeTag.value) {
  loading.value = true
  onMounted(() => reload())
}

// Moving between /news and /news/page/3 without leaving the app: the address
// changed, so the page number and the tag follow it and the list is read again.
// Without this the address would say page 3 while the screen still showed page 1.
watch(
  () => route.fullPath,
  () => {
    if (!String(route.path).startsWith('/news')) return

    const nextPage = pageFromRoute()
    const nextTag = String(route.query.tag ?? '').trim()

    if (nextPage === page.value && nextTag === activeTag.value) return

    page.value = nextPage
    activeTag.value = nextTag
    reload()
  }
)

// The lead story is the newest item, and only on the plain first page — while
// someone is searching or filtering, a big hero card just pushes results down.
const isPlainFirstPage = computed(
  () => page.value === 1 && search.value === '' && activeTag.value === ''
)

const lead = computed(() =>
  isPlainFirstPage.value && rows.value.length ? rows.value[0] : null
)

const rest = computed(() => (lead.value ? rows.value.slice(1) : rows.value))

const isEmpty = computed(() => rows.value.length === 0 && !loading.value)

const hasFilter = computed(() => search.value !== '' || activeTag.value !== '')

/**
 * Reload the list in the browser.
 *
 * Used when the reader types a search, picks a tag, or turns to another page
 * without leaving the address they are on.
 */
let requestId = 0

async function reload() {
  const mine = ++requestId
  loading.value = true

  try {
    const data = await load({ search: search.value })
    // A slower earlier request must never overwrite a newer answer.
    if (mine !== requestId) return
    apply(data)
  } catch (error) {
    if (mine !== requestId) return
    console.warn('[news] could not load from ' + apiBase, error)
    rows.value = []
    loadFailed.value = true
  } finally {
    if (mine === requestId) loading.value = false
  }
}

// Typing is slowed down so a search does not fire on every letter.
let searchTimer: ReturnType<typeof setTimeout> | undefined

watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    reload()
  }, 300)
})

function pickTag(tag: string) {
  activeTag.value = activeTag.value === tag ? '' : tag
  page.value = 1
  reload()
}

function clearFilters() {
  search.value = ''
  activeTag.value = ''
  page.value = 1
  reload()
}

// A list address can be reached from a filtered view (a story page's tag chips,
// or a "see all news" link while a filter is on). The tag and the search have to
// be dropped on the way, or the new address arrives with the old filter still on
// and shows nothing.
function goToNewsIndex() {
  search.value = ''
  activeTag.value = ''
  page.value = 1
  navigateTo('/news')
}

/** The address of one page of the list. */
function pageHref(target: number) {
  return target <= 1 ? '/news' : `/news/page/${target}`
}

// Searching or filtering is a browser action, so the pager stops being real
// links while a filter is on — there is nothing to generate for it.
function goToPage(target: number) {
  if (target < 1 || target > totalPages.value || target === page.value) return
  page.value = target

  if (hasFilter.value) {
    reload()
    return
  }

  navigateTo(pageHref(target))
}

// The page numbers shown: two either side of the current one, with the first
// and last always reachable.
const pageNumbers = computed(() => {
  const total = totalPages.value
  const current = page.value
  const start = Math.max(1, current - 2)
  const end = Math.min(total, current + 2)

  const numbers: (number | 'gap')[] = []
  if (start > 1) {
    numbers.push(1)
    if (start > 2) numbers.push('gap')
  }
  for (let i = start; i <= end; i++) numbers.push(i)
  if (end < total) {
    if (end < total - 1) numbers.push('gap')
    numbers.push(total)
  }

  return numbers
})

const imageUrl = (path?: string) =>
  path ? apiBase + '/' + String(path).replace(/^\/+/, '') : ''

// The address of a story inside THIS site now, not the PHP host.
const articleUrl = (item: NewsItem) => (item?.url ? item.url : '/news/' + item.slug)

const absoluteArticleUrl = (item: NewsItem) => published + articleUrl(item)

function shareOnFacebook(item: NewsItem) {
  const dialog =
    'https://www.facebook.com/sharer/sharer.php?u=' +
    encodeURIComponent(absoluteArticleUrl(item))
  window.open(dialog, 'softnet-share', 'width=640,height=640,noopener,noreferrer')
}
</script>

<template>
  <div class="news-browser">
    <!-- Tag filter row -->
    <nav v-if="tagCounts.length" class="news-tag-filter" aria-label="Filter news by tag">
      <button
        type="button"
        class="news-tag-chip"
        :class="{ 'is-active': !hasFilter }"
        @click="clearFilters"
      >
        All news
      </button>
      <button
        v-for="entry in tagCounts"
        :key="entry.tag"
        type="button"
        class="news-tag-chip"
        :class="{ 'is-active': activeTag === entry.tag }"
        @click="pickTag(entry.tag)"
      >
        #{{ entry.tag }}<span class="news-tag-count">{{ entry.count }}</span>
      </button>
    </nav>

    <!-- Search -->
    <div class="news-toolbar">
      <div class="news-search-wrap">
        <svg class="news-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8" /><line x1="21" y1="21" x2="16.65" y2="16.65" /></svg>
        <label class="sr-only" for="news-search">Search news</label>
        <input
          id="news-search"
          v-model="search"
          type="text"
          class="news-search-input"
          placeholder="Search news..."
          autocomplete="off"
        >
      </div>
      <button v-if="hasFilter" type="button" class="news-clear" @click="clearFilters">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12" /></svg>
        Clear
      </button>
    </div>

    <!-- Empty state -->
    <div v-if="isEmpty" class="news-empty">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2" /><path d="M18 14h-8M15 18h-5M10 6h8v4h-8V6Z" /></svg>
      <p v-if="loadFailed">The news could not be loaded just now. Please try again in a moment.</p>
      <p v-else>{{ hasFilter ? 'No news matches what you are looking for.' : 'No news yet. Check back soon!' }}</p>
      <button v-if="hasFilter" type="button" class="news-empty-link" @click="clearFilters">Show all news</button>
      <button v-else-if="loadFailed" type="button" class="news-empty-link" @click="reload">Try again</button>
    </div>

    <template v-else>
      <!-- Lead story -->
      <article v-if="lead" class="lead-card">
        <NuxtLink v-if="lead.image" :to="articleUrl(lead)" class="lead-media" tabindex="-1" aria-hidden="true">
          <img :src="imageUrl(lead.image)" alt="" loading="eager" decoding="async">
        </NuxtLink>
        <div class="lead-body">
          <span class="eyebrow">Latest story</span>
          <h2 class="lead-title">
            <NuxtLink :to="articleUrl(lead)">{{ lead.title }}</NuxtLink>
          </h2>
          <p v-if="lead.summary" class="lead-summary">{{ lead.summary }}</p>
          <div class="card-foot">
            <span class="meta">{{ lead.date_formatted }}</span>
            <span v-if="lead.category" class="badge badge-green">{{ lead.category }}</span>
          </div>
          <TagChips :tags="lead.tags" base="/news" />
          <div class="lead-actions">
            <NuxtLink class="btn-read" :to="articleUrl(lead)">
              Read the story
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7" /></svg>
            </NuxtLink>
            <span v-if="lead.gallery_count" class="photo-count">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="8.5" cy="8.5" r="1.5" /><path d="m21 15-5-5L5 21" /></svg>
              {{ lead.gallery_count }} {{ lead.gallery_count === 1 ? 'photo' : 'photos' }}
            </span>
            <button
              type="button"
              class="share-btn share-facebook share-btn-quiet"
              :aria-label="`Share ${lead.title} on Facebook`"
              @click="shareOnFacebook(lead)"
            >
              <svg class="share-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.77-3.89 1.1 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0 0 22 12Z" /></svg>
              Share
            </button>
          </div>
        </div>
      </article>

      <!-- The rest of the page -->
      <div v-if="rest.length" class="news-grid">
        <article v-for="item in rest" :key="item.id" class="news-card">
          <NuxtLink v-if="item.image" :to="articleUrl(item)" class="news-card-media" tabindex="-1" aria-hidden="true">
            <img :src="imageUrl(item.image)" alt="" loading="lazy" decoding="async">
          </NuxtLink>
          <div class="news-card-body">
            <span v-if="item.category" class="badge badge-green">{{ item.category }}</span>
            <h3 class="news-card-title">
              <NuxtLink :to="articleUrl(item)">{{ item.title }}</NuxtLink>
            </h3>
            <p v-if="item.summary" class="news-card-summary">{{ item.summary }}</p>
            <TagChips :tags="item.tags" base="/news" />
            <div class="news-card-foot">
              <span class="meta">{{ item.date_formatted }}</span>
              <div class="news-card-foot-actions">
                <span v-if="item.gallery_count" class="photo-count photo-count-quiet">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="8.5" cy="8.5" r="1.5" /><path d="m21 15-5-5L5 21" /></svg>
                  {{ item.gallery_count }}
                </span>
                <button
                  type="button"
                  class="share-btn share-btn-quiet"
                  :aria-label="`Share ${item.title} on Facebook`"
                  @click="shareOnFacebook(item)"
                >
                  <svg class="share-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.77-3.89 1.1 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0 0 22 12Z" /></svg>
                </button>
              </div>
            </div>
          </div>
        </article>
      </div>

      <!-- Pagination -->
      <nav v-if="totalPages > 1" class="pagination" aria-label="News pages">
        <span class="pagination-info">
          Page {{ page }} of {{ totalPages }} &middot; {{ totalNews }}
          {{ totalNews === 1 ? 'story' : 'stories' }}
        </span>
        <div class="pagination-links">
          <!-- With no filter on, the pager is real links, so a crawler can walk
               the whole list and each page is a real address. -->
          <template v-if="!hasFilter">
            <NuxtLink v-if="page > 1" class="page-btn" :to="pageHref(page - 1)">&larr; Prev</NuxtLink>
            <span v-else class="page-btn" aria-hidden="true">&larr; Prev</span>

            <template v-for="(n, i) in pageNumbers" :key="`${n}-${i}`">
              <span v-if="n === 'gap'" class="page-ellipsis">&hellip;</span>
              <span v-else-if="n === page" class="page-btn active" aria-current="page">{{ n }}</span>
              <NuxtLink v-else class="page-btn" :to="pageHref(n)">{{ n }}</NuxtLink>
            </template>

            <NuxtLink v-if="page < totalPages" class="page-btn" :to="pageHref(page + 1)">Next &rarr;</NuxtLink>
            <span v-else class="page-btn" aria-hidden="true">Next &rarr;</span>
          </template>

          <!-- Filtering is done in the browser, so the pager becomes buttons. -->
          <template v-else>
            <button type="button" class="page-btn" :disabled="page <= 1" @click="goToPage(page - 1)">
              &larr; Prev
            </button>
            <template v-for="(n, i) in pageNumbers" :key="`f-${n}-${i}`">
              <span v-if="n === 'gap'" class="page-ellipsis">&hellip;</span>
              <span v-else-if="n === page" class="page-btn active" aria-current="page">{{ n }}</span>
              <button v-else type="button" class="page-btn" @click="goToPage(n)">{{ n }}</button>
            </template>
            <button type="button" class="page-btn" :disabled="page >= totalPages" @click="goToPage(page + 1)">
              Next &rarr;
            </button>
          </template>
        </div>
      </nav>
    </template>
  </div>
</template>
