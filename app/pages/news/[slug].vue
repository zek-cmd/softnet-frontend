<script setup lang="ts">
// One news item: /news/softnet-sports-day, or the old /news/12.
//
// THE PAGE THAT RETIRED THE PHP SHARE-TAG FILE
//
// Facebook, Messenger, WhatsApp and Google do not run JavaScript: they read the
// HTML the server sends and build the preview card from the <head>. While this
// site was a fixed set of files, no file could do that — a story's real title
// and cover photo live in a database, and a file cannot look anything up. So
// public/news-view.php did it, and that one file is why the PHP backend had to
// stay alive.
//
// This site is now served by Node, so the page is built on the server every
// time it is asked for, and the tags below are written into the HTML that
// leaves the server. Facebook reads a real card, Google reads a real title, and
// the story shown is what the database holds at that moment. No PHP involved,
// and nothing has to be rebuilt when a story is posted.
//
// The same code runs twice: once on the server to make the HTML, and again in
// the browser when the reader moves between stories without a page load.

definePageMeta({ layout: 'default' })

import { fetchNewsItem, NewsApiError } from '~/utils/news-api'
import type { GalleryPhoto, NewsItem } from '~/utils/news-api'

// The school's name, as it appears in a browser tab and on a share card.
const SITE_NAME = 'SOFTNET Information Technology Center, Inc.'

// A plain number is the old address shape; anything else is a readable address.
const route = useRoute()
const { api: apiBase, published: siteUrl } = useNewsApi()

const address = computed(() => String(route.params.slug ?? '').trim())

// Read on the SERVER first (that is what writes the share card), then watched, so
// moving from one story to the next reads that story rather than keeping the
// first one on screen.
const { data, pending } = await useAsyncData(
  () => `news-item-${address.value}`,
  async () => {
    try {
      const answer = await fetchNewsItem(apiBase, address.value)

      // A story that is really not there is an ANSWER. It is kept apart from a
      // request that never got through, because the two read very differently
      // to a visitor — and because only one of them may be published as
      // "this page does not exist".
      return answer
    } catch (error) {
      const reason = error instanceof NewsApiError ? error.message : String(error)
      console.error('[news-item] could not load from ' + apiBase + ': ' + reason)
      return { failed: true }
    }
  },
  { watch: [address] }
)

const item = computed<NewsItem | null>(() => data.value?.news ?? data.value?.item ?? null)

// The news API could not be reached at all, so nothing can honestly be said
// about whether the story exists.
const loadFailed = computed(() => data.value?.failed === true)

// On the server the answer is already in. In the browser, after the first
// render, there is a moment before the new story arrives — and the page must
// not say "not found" while it is still fetching.
const finished = ref(import.meta.server ? true : !pending.value)
watch(pending, (busy) => {
  if (!busy) finished.value = true
})

const loading = computed(() => !finished.value && !item.value)

// The answer is in, there is no story, and the API answered rather than failed:
// either it reported 404 for this address, or it returned nothing usable.
const notFound = computed(() => finished.value && !item.value && !loadFailed.value)

// A page that does not describe a real story must not be indexed, or Google
// would keep a dead address alive. Published by the server, so a crawler that
// runs no JavaScript still sees it.
if (import.meta.server) {
  if (notFound.value) setResponseStatus(404)
  else if (loadFailed.value) setResponseStatus(503)
}

/** What the big heading says, one honest state at a time. */
const heading = computed(() => {
  if (item.value) return item.value.title
  if (loading.value) return 'Loading the story...'
  if (loadFailed.value) return 'The story could not be loaded'
  return 'That news item is not here'
})

const title = computed(() => item.value?.title ?? 'News')
const category = computed(() => item.value?.category || 'General')
const dateFormatted = computed(() => item.value?.date_formatted ?? '')
const dateIso = computed(() => item.value?.date_iso ?? '')
const gallery = computed<GalleryPhoto[]>(() => item.value?.gallery ?? [])

// Photos come from the news API's uploads folder, which lives on another host.
const imageUrl = (path?: string) =>
  path ? apiBase + '/' + String(path).replace(/^\/+/, '') : ''

// The site's own images (the cover photo used as a fallback) are served by this
// site, so they take the site's address, not the API's.
const siteImage = (path: string) => siteUrl + path

// The address of THIS story inside this site. The site's address — not the
// API's — is what is handed out for sharing; the news backend is not a page
// anyone should land on.
const articleUrl = computed(() => item.value?.url ?? `/news/${address.value}`)
const absoluteUrl = computed(() => siteUrl + articleUrl.value)

// ---------------------------------------------------------------------------
// The share card and the browser tab
// ---------------------------------------------------------------------------

/** A long text cut down to something that fits a preview card. */
function clip(text: unknown, max = 200): string {
  const clean = String(text ?? '')
    .replace(/<[^>]*>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()

  if (clean.length <= max) return clean

  // Cut on a word boundary, the way the old PHP head did, so a card never ends
  // mid-word.
  const cut = clean.slice(0, max)
  const space = cut.lastIndexOf(' ')
  return (space > max * 0.6 ? cut.slice(0, space) : cut).trimEnd() + '...'
}

const description = computed(() => {
  if (item.value?.summary) {
    const written = clip(item.value.summary)
    if (written) return written
  }

  // No summary: the beginning of the story itself, so the card is never blank.
  return clip(item.value?.content) || `News and updates from ${SITE_NAME}.`
})

// The cover photo, as an address Facebook can fetch: the story's own picture
// when it has one, otherwise the site cover, which is always there.
const shareImage = computed(() =>
  item.value?.image ? imageUrl(item.value.image) : siteImage('/_sitc/images/cover.webp')
)

useSeoMeta({
  title: () => (item.value ? `${title.value} | ${SITE_NAME}` : `News | ${SITE_NAME}`),
  description,
  ogTitle: () => (item.value ? title.value : `News | ${SITE_NAME}`),
  ogDescription: description,
  ogImage: shareImage,
  ogImageAlt: () => title.value,
  ogType: 'article',
  articlePublishedTime: () => (item.value ? dateIso.value || undefined : undefined),
  twitterCard: 'summary_large_image',
  // A page whose story could not be loaded, or is not there, must not be
  // indexed as a real story page.
  robots: () => (item.value ? 'index, follow' : 'noindex, follow'),
})

// The numeric and readable addresses are both real pages, so each story exists
// at two URLs. This tells Google which one is the real home of the story. It is
// only claimed for a story that is actually here.
useHead({
  link: [
    {
      rel: 'canonical',
      href: () => (item.value ? siteUrl + articleUrl.value : undefined),
    },
  ],
})

function shareOnFacebook() {
  const dialog =
    'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(absoluteUrl.value)
  window.open(dialog, 'softnet-share', 'width=640,height=640,noopener,noreferrer')
}

// ---------------------------------------------------------------------------
// The photo viewer
// ---------------------------------------------------------------------------

const openIndex = ref(-1)
const isOpen = computed(() => openIndex.value >= 0)
const current = computed(() => (isOpen.value ? gallery.value[openIndex.value] : null))

async function copyLink(button: EventTarget | null) {
  if (!(button instanceof HTMLElement)) return
  try {
    await navigator.clipboard.writeText(absoluteUrl.value)
    button.classList.add('is-done')
    const text = button.querySelector('.share-copy-text')
    const previous = text?.textContent
    if (text) text.textContent = 'Copied'
    setTimeout(() => {
      button.classList.remove('is-done')
      if (text && previous) text.textContent = previous
    }, 1800)
  } catch (error) {
    console.warn('[news-item] could not copy the link', error)
  }
}

let lastFocused: HTMLElement | null = null

function openViewer(index: number) {
  lastFocused = document.activeElement as HTMLElement | null
  openIndex.value = index
  document.body.classList.add('lightbox-open')
}

function closeViewer() {
  openIndex.value = -1
  document.body.classList.remove('lightbox-open')
  lastFocused?.focus?.()
}

function step(delta: number) {
  if (!gallery.value.length) return
  const next = openIndex.value + delta
  if (next < 0) openIndex.value = gallery.value.length - 1
  else if (next >= gallery.value.length) openIndex.value = 0
  else openIndex.value = next
}

// Escape closes, the arrows move between photos. Only listened for while the
// viewer is actually open.
function onKeydown(event: KeyboardEvent) {
  if (!isOpen.value) return
  if (event.key === 'Escape') closeViewer()
  else if (event.key === 'ArrowRight') step(1)
  else if (event.key === 'ArrowLeft') step(-1)
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onUnmounted(() => {
  window.removeEventListener('keydown', onKeydown)
  document.body.classList.remove('lightbox-open')
})

// A tap on a phone must not open the viewer: the swipe strip IS the viewer
// there. This checks the same breakpoint the CSS uses (640px), so the two can
// never disagree and leave the photo tappable on a screen showing the strip.
function openFromClick(index: number) {
  if (typeof window !== 'undefined' && window.matchMedia('(min-width: 640px)').matches) {
    openViewer(index)
  }
}
</script>

<template>
  <div>
    <!-- Hero band, copied class for class from the about and contact pages. -->
    <section class="band relative overflow-hidden bg-gray-900 pt-32 pb-16" aria-label="News item">
      <div class="pointer-events-none absolute inset-0 opacity-[0.08]" aria-hidden="true">
        <div class="absolute inset-0" style="background-image: radial-gradient(circle, #ffffff 1px, transparent 1px); background-size: 28px 28px;"></div>
      </div>
      <div class="pointer-events-none absolute -right-20 -top-20 h-64 w-64 rounded-full bg-white/5" aria-hidden="true" />
      <div class="pointer-events-none absolute -bottom-16 -left-16 h-48 w-48 rounded-full bg-white/5" aria-hidden="true" />
      <div class="pointer-events-none absolute -right-8 bottom-4 select-none text-[10vw] font-black leading-none tracking-tighter text-white/5" aria-hidden="true">
        {{ notFound ? '404' : 'NEWS' }}
      </div>
      <div class="container-page relative z-10">
        <p class="text-xs font-bold uppercase tracking-widest text-green-300">
          {{ notFound ? 'Not found' : loading && !item ? 'News' : category }}
        </p>
        <h1 class="mt-3 max-w-2xl text-3xl font-extrabold tracking-tight text-white sm:text-4xl lg:text-5xl">
          {{ heading }}
        </h1>
        <time
          v-if="item && dateFormatted"
          class="mt-4 block text-sm font-semibold text-green-100"
          :datetime="dateIso"
        >{{ dateFormatted }}</time>
        <p v-else-if="notFound" class="mt-4 max-w-2xl text-sm leading-relaxed text-green-100 sm:text-base">
          It may have been removed, or the link may be incomplete.
        </p>
        <p v-else-if="loadFailed" class="mt-4 max-w-2xl text-sm leading-relaxed text-green-100 sm:text-base">
          The news service could not be reached just now. Please try again in a moment.
        </p>
      </div>
    </section>

    <div class="article-shell">
      <div class="article-column">
        <NuxtLink to="/news" class="back-link">&larr; Back to all news</NuxtLink>

        <template v-if="item">
          <img
            v-if="item.image"
            :src="imageUrl(item.image)"
            alt=""
            class="post-image"
          >

          <TagChips v-if="item.tags && item.tags.length" :tags="item.tags" base="/news" class="tag-row-article" />

          <!-- The story. app/utils/news-api.ts turns the editor's blocks into
               HTML and escapes every piece of text as it does, so a story can
               never bring a script into this page. That check is what makes
               v-html safe here. -->
          <article class="post-content" v-html="item.content" />

          <!-- The sub images. -->
          <section v-if="gallery.length" class="gallery" aria-label="Photo gallery">
            <div class="gallery-grid">
              <figure v-for="(photo, i) in gallery" :key="photo.src" class="gallery-item">
                <a
                  class="gallery-link"
                  :href="imageUrl(photo.src)"
                  :aria-label="`Open photo ${i + 1} of ${gallery.length}`"
                  @click.prevent="openFromClick(i)"
                >
                  <img :src="imageUrl(photo.src)" :alt="photo.alt || title" loading="lazy" decoding="async">
                </a>
                <figcaption v-if="photo.caption" class="gallery-caption">{{ photo.caption }}</figcaption>
              </figure>
            </div>
          </section>

          <!-- Share row -->
          <div class="share-row">
            <span class="share-label">Share this</span>
            <button type="button" class="share-btn share-facebook" @click="shareOnFacebook">
              <svg class="share-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.77-3.89 1.1 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0 0 22 12Z" /></svg>
              Share on Facebook
            </button>
            <button type="button" class="share-btn share-copy" @click="copyLink($event.currentTarget)">
              <svg class="share-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="12" height="12" rx="2" /><path d="M5 15V5a2 2 0 0 1 2-2h10" /></svg>
              <span class="share-copy-text">Copy link</span>
            </button>
          </div>
        </template>

        <div class="article-foot">
          <NuxtLink to="/news" class="back-link">&larr; Back to all news</NuxtLink>
        </div>
      </div>
    </div>

    <!-- The photo viewer -->
    <Teleport to="body">
      <div v-if="isOpen" class="lightbox" role="dialog" aria-modal="true" :aria-label="`Photo ${openIndex + 1} of ${gallery.length}`">
        <div class="lightbox-backdrop" @click="closeViewer" />
        <div class="lightbox-panel">
          <div class="lightbox-stage">
            <button type="button" class="lightbox-arrow lightbox-prev" aria-label="Previous photo" @click="step(-1)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
            </button>
            <img v-if="current" :src="imageUrl(current.src)" :alt="current.alt || title" class="lightbox-image">
            <button type="button" class="lightbox-arrow lightbox-next" aria-label="Next photo" @click="step(1)">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
            </button>
          </div>
          <p v-if="current?.caption" class="lightbox-caption">{{ current.caption }}</p>
        </div>
      </div>
    </Teleport>

    <!-- The same floating button the other pages carry. It sits after the photo
         viewer, so a long story is one tap from the top. -->
    <BackToTop />
  </div>
</template>
