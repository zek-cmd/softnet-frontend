// Reading the news from the news API (Strapi), in the shape the news pages use.
//
// The pages were written when the news was answered by the PHP backend, which
// answered in one particular shape: a list feed with a "news" array, a "total",
// a "pages" count and a ready-made tag row, and one story with
// "date_formatted", "content" as HTML and a "gallery" of photos. Strapi speaks
// a different language — entries under "data", paging under "meta", the story
// body as a list of blocks, photos as media objects with a single "url".
//
// Rather than teach every page that second language, the translation lives
// here. This is the only file that knows Strapi exists: it asks Strapi the
// right question and answers in the old shape, so the pages did not have to
// change what they think news looks like.
//
// Which host holds the news is NUXT_NEWS_API (see nuxt.config.ts). Since the
// PHP backend was retired, that is the whole news backend — and when it is not
// set, or cannot be reached, nothing is guessed: the caller is told, and says
// so on the page.

import { fetchNewsJson } from '~/utils/news-fetch'

export type GalleryPhoto = { src: string; caption?: string; alt?: string }

export type NewsItem = {
  id: number
  title: string
  summary?: string
  category?: string
  content?: string
  tags?: string[]
  image?: string
  url?: string
  slug?: string
  created_at?: string
  date_formatted?: string
  date_iso?: string
  gallery?: GalleryPhoto[]
  gallery_count?: number
}

export type TagCount = { tag: string; count: number }

export type NewsPage = {
  posts: NewsItem[]
  news: NewsItem[]
  total: number
  pages: number
  page: number
  per_page: number
  tags: TagCount[]
}

const DEFAULT_PER_PAGE = 9
const MAX_PER_PAGE = 50
const TAG_ROWS_LIMIT = 14
const TAGS_PAGE_SIZE = 100
const TAGS_MAX_PAGES = 10

// Dates are written the way the PHP backend wrote them ("February 14, 2026"),
// in the school's own timezone rather than the reader's, so a story published
// late in the evening in Mariveles is not shown as the day before.
const DATE_FORMATTED = new Intl.DateTimeFormat('en-US', {
  timeZone: 'Asia/Manila',
  year: 'numeric',
  month: 'long',
  day: 'numeric',
})
const DATE_ISO = new Intl.DateTimeFormat('en-CA', {
  timeZone: 'Asia/Manila',
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
})

function clamp(value: number, lowest: number, highest: number): number {
  return Math.max(lowest, Math.min(Math.trunc(value), highest))
}

/** One written date, in both the readable and the machine form. */
function dates(value: unknown): { formatted: string; iso: string } {
  const raw = String(value ?? '').trim()
  if (!raw) return { formatted: '', iso: '' }

  const at = new Date(raw)
  if (Number.isNaN(at.getTime())) return { formatted: '', iso: '' }

  return { formatted: DATE_FORMATTED.format(at), iso: DATE_ISO.format(at) }
}

/**
 * A photo address, as a path the pages can join onto the API host.
 *
 * Strapi normally answers with "/uploads/photo.jpg". If a published address is
 * configured it may answer with a full link instead; only the path is kept,
 * because every caller adds the host itself and a host inside a host would
 * point at nothing.
 */
function mediaPath(url: unknown): string {
  const raw = String(url ?? '').trim()
  if (!raw) return ''

  const path = /^https?:\/\//i.test(raw) ? raw.replace(/^https?:\/\/[^/]+/i, '') : raw
  return '/' + path.replace(/^\/+/, '')
}

/**
 * The same photo address, made absolute against the news host.
 *
 * The picture fields the pages read (the cover photo, the gallery) are kept as
 * paths because the pages add the host themselves. A picture INSIDE a story's
 * body is different: the body is printed as ready-made HTML, so nothing can add
 * the host afterwards — a path there would be fetched from this site, where the
 * uploaded photos are not, and the picture would simply not appear.
 */
function mediaBaseUrl(base: string, url: unknown): string {
  const path = mediaPath(url)
  if (!path) return ''

  const host = String(base ?? '').replace(/\/+$/, '')
  return host ? host + path : path
}

/** The stored tag line ("sports, tesda") as a list, the way the PHP side read it. */
function tagList(stored: unknown): string[] {
  return String(stored ?? '')
    .split(',')
    .map((tag) => tag.trim())
    .filter((tag) => tag !== '')
}

// ---------------------------------------------------------------------------
// The story body
// ---------------------------------------------------------------------------
//
// Strapi can store a story body in two ways, and this file understands both.
//
// The editor in the admin — the one this project uses — writes Markdown: plain
// text with # for a heading, - for a list, **bold** for emphasis, and it is
// stored exactly as it was typed. The other kind stores a list of blocks, each
// with its own nested pieces. Either way the news page prints the body with
// v-html, so the body is turned into HTML here.
//
// Nothing in this file is trusted raw: every piece of text is ESCAPED FIRST,
// and only then are the marks read. So a story can never bring a tag of its own
// into the page — a typed <script> survives as &lt;script&gt;, and only the
// handful of tags built below ever reach the page. Strapi checks a story as it
// is saved; this is the second lock on the same door.

function escapeHtml(value: unknown): string {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}

/** One run of text with its styling, as HTML. */
function inlineText(node: any): string {
  // A soft line break inside a paragraph (shift and enter) is kept as one.
  let html = escapeHtml(node?.text).replace(/\n/g, '<br>')

  if (node?.code) html = `<code>${html}</code>`
  if (node?.bold) html = `<strong>${html}</strong>`
  if (node?.italic) html = `<em>${html}</em>`
  if (node?.underline) html = `<u>${html}</u>`
  if (node?.strikethrough) html = `<s>${html}</s>`

  return html
}

/** The pieces inside a block: text runs, or a link holding them. */
function inlineChildren(children: any[] | undefined, title: string): string {
  if (!Array.isArray(children)) return ''

  return children
    .map((child) => {
      if (child?.type === 'link') {
        const href = String(child?.url ?? '').trim()
        // Only a real web address becomes a link; anything else keeps its text
        // and loses the link, so a "javascript:" address can never ride along.
        if (!/^https?:\/\//i.test(href)) return inlineChildren(child.children, title)
        return `<a href="${escapeHtml(href)}" target="_blank" rel="noopener noreferrer">${inlineChildren(child.children, title)}</a>`
      }

      if (child?.type === 'text') return inlineText(child)

      return inlineChildren(child?.children, title)
    })
    .join('')
}

/** A whole stored story as HTML.*/
export function blocksToHtml(blocks: unknown, title = '', mediaBase = ''): string {
  if (!Array.isArray(blocks)) return ''

  return blocks
    .map((block) => {
      switch (block?.type) {
        case 'paragraph':
          return `<p>${inlineChildren(block.children, title)}</p>`

        case 'heading': {
          // A story never prints its own first-level heading: the page already
          // has one, and a second one would confuse Google.
          const level = clamp(Number(block.level) || 2, 2, 6)
          return `<h${level}>${inlineChildren(block.children, title)}</h${level}>`
        }

        case 'list': {
          const tag = block.format === 'ordered' ? 'ol' : 'ul'
          const items = (Array.isArray(block.children) ? block.children : [])
            .map((item: any) => `<li>${inlineChildren(item?.children, title)}</li>`)
            .join('')
          return items ? `<${tag}>${items}</${tag}>` : ''
        }

        case 'quote':
          return `<blockquote>${inlineChildren(block.children, title)}</blockquote>`

        case 'code': {
          const code = (Array.isArray(block.children) ? block.children : [])
            .map((child: any) => escapeHtml(child?.text))
            .join('')
          return `<pre><code>${code}</code></pre>`
        }

        case 'image': {
          const src = mediaBaseUrl(mediaBase, block?.image?.url)
          if (!src) return ''
          const alt = escapeHtml(block?.image?.alternativeText || title)
          const caption = String(block?.image?.caption ?? '').trim()
          const figure = `<img src="${escapeHtml(src)}" alt="${alt}" loading="lazy" decoding="async">`
          return caption ? `<figure>${figure}<figcaption>${escapeHtml(caption)}</figcaption></figure>` : `<figure>${figure}</figure>`
        }

        default:
          return ''
      }
    })
    .join('\n')
}

// ---------------------------------------------------------------------------
// The story body, as written in the admin's Markdown editor
// ---------------------------------------------------------------------------

/**
 * An address that is safe to put in a link or a picture, or '' to drop it.
 *
 * Only two things are allowed through: a full http/https address, and a path
 * starting with a slash. A path is what Strapi stores for a picture uploaded to
 * its media library, and it is made absolute against the news host — the body
 * is printed as it is, and the uploaded photos do not live on this site.
 *
 * Everything else — "javascript:", "data:", anything unrecognised — is dropped,
 * so a story can never turn a link into a way of running code.
 */
function safeUrl(value: unknown, mediaBase = ''): string {
  const raw = String(value ?? '').trim()
  if (!raw) return ''

  if (/^\//.test(raw)) {
    const host = String(mediaBase ?? '').replace(/\/+$/, '')
    return host ? host + raw : raw
  }

  return /^https?:\/\//i.test(raw) ? raw : ''
}

/**
 * The styling marks inside one run of text: bold, italics, links, pictures.
 *
 * The text handed in has already been escaped, so the marks are the only thing
 * left to read here, and the addresses are the only thing to check.
 */
function inlineMarkdown(escaped: string, title: string, mediaBase: string): string {
  // Code comes out first and is put back at the end, because everything inside
  // backticks is meant to be shown as it was typed — marks and all.
  const kept: string[] = []
  let html = escaped.replace(/`([^`]+)`/g, (_whole, code: string) => {
    kept.push(`<code>${code}</code>`)
    return `\u0000${kept.length - 1}\u0000`
  })

  // A picture: ![what it shows](where it lives)
  html = html.replace(/!\[([^\]]*)\]\(([^)\s]+)[^)]*\)/g, (_whole, alt: string, src: string) => {
    const url = safeUrl(src, mediaBase)
    if (!url) return ''
    return `<img src="${escapeHtml(url)}" alt="${alt.trim() || escapeHtml(title)}" loading="lazy" decoding="async">`
  })

  // A link: [what it says](where it goes). A link with no safe address keeps its
  // words and simply stops being a link.
  html = html.replace(/\[([^\]]+)\]\(([^)\s]+)[^)]*\)/g, (_whole, label: string, href: string) => {
    const url = safeUrl(href)
    return url
      ? `<a href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer">${label}</a>`
      : label
  })

  // Bold before italics: ** is read first, so what is left of it is a single *.
  html = html
    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
    .replace(/__([^_]+)__/g, '<strong>$1</strong>')
    .replace(/\*([^*]+)\*/g, '<em>$1</em>')
    .replace(/(^|[\s(])_([^_]+)_(?=[\s).,!?;:]|$)/g, '$1<em>$2</em>')
    .replace(/~~([^~]+)~~/g, '<s>$1</s>')

  // The code put back, and a mark that never found its pair left as it was.
  return html.replace(/\u0000(\d+)\u0000/g, (_whole, index: string) => kept[Number(index)] ?? '')
}

/**
 * A whole story written in Markdown, as HTML.
 *
 * A small, fixed reading of Markdown, which is all the admin's editor writes:
 * headings, paragraphs, bullet and numbered lists, quotes, fenced code, rules,
 * and the styling marks handled above. Anything else is left as plain text
 * rather than guessed at.
 */
export function markdownToHtml(markdown: unknown, title = '', mediaBase = ''): string {
  const text = String(markdown ?? '').replace(/\r\n?/g, '\n')
  if (!text.trim()) return ''

  const lines = text.split('\n')
  const out: string[] = []

  const bullet = /^\s{0,3}[-*+]\s+(.*)$/
  const numbered = /^\s{0,3}\d+[.)]\s+(.*)$/
  const heading = /^\s{0,3}(#{1,6})\s+(.*?)\s*#*\s*$/
  const quote = /^\s{0,3}>/
  const fence = /^\s{0,3}```/
  const rule = /^\s{0,3}([-*_])(\s*\1){2,}\s*$/

  // One line of a paragraph, list item or quote, with its marks read. A single
  // newline inside a paragraph is kept as one, so a story reads the way it was
  // typed even when the author pressed enter without leaving a blank line.
  const inline = (value: string) => inlineMarkdown(escapeHtml(value.trim()), title, mediaBase)

  /** Does this line start a block other than a paragraph? */
  const startsBlock = (line: string) =>
    !line.trim() || fence.test(line) || heading.test(line) || quote.test(line) ||
    rule.test(line) || bullet.test(line) || numbered.test(line)

  let i = 0
  while (i < lines.length) {
    const line = lines[i] ?? ''

    if (!line.trim()) {
      i++
      continue
    }

    if (fence.test(line)) {
      const body: string[] = []
      i++
      while (i < lines.length && !fence.test(lines[i] ?? '')) {
        body.push(lines[i] ?? '')
        i++
      }
      i++
      out.push(`<pre><code>${escapeHtml(body.join('\n'))}</code></pre>`)
      continue
    }

    const head = line.match(heading)
    if (head) {
      // A story never prints its own first-level heading: the page already has
      // one, and a second one would confuse Google.
      const level = clamp(head[1].length, 2, 6)
      out.push(`<h${level}>${inline(head[2])}</h${level}>`)
      i++
      continue
    }

    if (rule.test(line)) {
      i++
      continue
    }

    if (quote.test(line)) {
      const body: string[] = []
      while (i < lines.length && quote.test(lines[i] ?? '')) {
        body.push((lines[i] ?? '').replace(quote, ''))
        i++
      }
      out.push(`<blockquote>${body.map(inline).join('<br>')}</blockquote>`)
      continue
    }

    if (bullet.test(line) || numbered.test(line)) {
      const ordered = numbered.test(line)
      const pattern = ordered ? numbered : bullet
      const items: string[] = []
      while (i < lines.length) {
        const found = (lines[i] ?? '').match(pattern)
        if (!found) break
        items.push(`<li>${inline(found[1])}</li>`)
        i++
      }
      out.push(ordered ? `<ol>${items.join('')}</ol>` : `<ul>${items.join('')}</ul>`)
      continue
    }

    // A paragraph: everything up to the next blank line or the next block.
    const body: string[] = []
    while (i < lines.length && !startsBlock(lines[i] ?? '')) {
      body.push(lines[i] ?? '')
      i++
    }
    out.push(`<p>${body.map(inline).join('<br>')}</p>`)
  }

  return out.join('\n')
}

/**
 * A story body as HTML, however the admin happened to store it.
 *
 * Strapi's Markdown editor keeps the text as it was typed; the block editor
 * keeps a list of blocks. Both are read here, so nothing about a story depends
 * on which editor the field uses.
 */
export function storyHtml(content: unknown, title = '', mediaBase = ''): string {
  if (typeof content === 'string') return markdownToHtml(content, title, mediaBase)
  return blocksToHtml(content, title, mediaBase)
}

// ---------------------------------------------------------------------------
// Asking Strapi
// ---------------------------------------------------------------------------

type StrapiAnswer = { status: number; ok: boolean; body: any }

/**
 * The news API could not be reached, or is not configured.
 *
 * This is kept apart from "there is no such story" and from "there is no news
 * yet", because the three must read differently to a visitor and must answer
 * with different status codes: a broken backend must never be published to
 * Facebook or Google as "this story does not exist".
 */
export class NewsApiError extends Error {
  constructor(message: string) {
    super(message)
    this.name = 'NewsApiError'
  }
}

/**
 * One request to Strapi.
 *
 * fetchNewsJson is used rather than a plain fetch so this keeps the retry and
 * second-address behaviour the news reader already had: a host that accepts a
 * connection and then never answers is still survivable.
 */
async function strapiRequest(apiBase: string, params: URLSearchParams): Promise<StrapiAnswer> {
  // With no host set there is nothing to ask. Without this the request would go
  // to this site's own /api/news — a real address on the new host, and one that
  // answers HTML, not news.
  if (!String(apiBase ?? '').trim()) {
    throw new NewsApiError('the news API address is not set (NUXT_NEWS_API)')
  }

  const res = await fetchNewsJson(`${apiBase}/api/news?${params}`)

  let body: any = null
  try {
    body = await res.json()
  } catch {
    body = null
  }

  return { status: res.status, ok: res.ok, body }
}

/** The filters a search word and a tag turn into. */
function filtersFor(search: string, tag: string): Array<[string, string]> {
  const pairs: Array<[string, string]> = []

  if (search) {
    // The same four places the PHP backend searched: title, summary,
    // category and tags.
    pairs.push(['filters[$or][0][title][$containsi]', search])
    pairs.push(['filters[$or][1][summary][$containsi]', search])
    pairs.push(['filters[$or][2][category][$containsi]', search])
    pairs.push(['filters[$or][3][tags][$containsi]', search])
  }

  if (tag) {
    // Looks inside the stored tag line, so "Hotel and Restaurant" is found
    // however it was spaced. Unlike the PHP backend this is a piece match, so
    // "Art" also finds "Culinary Arts".
    pairs.push(['filters[tags][$containsi]', tag])
  }

  return pairs
}

/** One Strapi entry, as the shape the news pages already expect. */
function toNewsItem(doc: any, options: { withBody?: boolean; mediaBase?: string } = {}): NewsItem {
  const legacyId = Number(doc?.legacy_id ?? doc?.id ?? 0)
  const slug = String(doc?.slug ?? '').trim()
  const written = dates(doc?.publishedAt ?? doc?.createdAt)

  const gallery: GalleryPhoto[] = (Array.isArray(doc?.gallery) ? doc.gallery : [])
    .map((photo: any) => {
      const src = mediaPath(photo?.image?.url)
      const caption = String(photo?.caption ?? '').trim()
      return { src, caption, alt: caption || String(doc?.title ?? '') }
    })
    .filter((photo: GalleryPhoto) => photo.src !== '')

  const item: NewsItem = {
    id: Number(doc?.id ?? 0),
    title: String(doc?.title ?? ''),
    summary: String(doc?.summary ?? ''),
    category: String(doc?.category ?? ''),
    tags: tagList(doc?.tags),
    image: mediaPath(doc?.image?.url),
    date_formatted: written.formatted,
    date_iso: written.iso,
    created_at: String(doc?.createdAt ?? ''),
    slug,
    // The readable address when the story has one. While it has none the number
    // is used, and it is the number from the old PHP database when that was
    // carried over — so a link shared before the move still opens the right
    // story, and the story page knows to look for both shapes.
    url: slug ? `/news/${slug}` : `/news/${legacyId}`,
    gallery_count: gallery.length,
  }

  if (options.withBody) {
    // The host is handed in so pictures inside the body are absolute: that
    // HTML is printed as it is, with nothing left to add the host afterwards.
    item.content = storyHtml(doc?.content, item.title, options.mediaBase)
    item.gallery = gallery
  }

  return item
}

/** Every tag in use, busiest first — the row of chips above the list. */
async function fetchTagCounts(apiBase: string): Promise<TagCount[]> {
  const counts = new Map<string, TagCount>()

  for (let page = 1; page <= TAGS_MAX_PAGES; page++) {
    const params = new URLSearchParams()
    params.set('fields[0]', 'tags')
    params.set('pagination[page]', String(page))
    params.set('pagination[pageSize]', String(TAGS_PAGE_SIZE))

    const { ok, body } = await strapiRequest(apiBase, params)
    if (!ok) break

    for (const doc of Array.isArray(body?.data) ? body.data : []) {
      for (const tag of tagList(doc?.tags)) {
        const key = tag.toLowerCase()
        const found = counts.get(key)
        if (found) found.count += 1
        else counts.set(key, { tag, count: 1 })
      }
    }

    const pageCount = Math.max(1, Number(body?.meta?.pagination?.pageCount ?? 1))
    if (page >= pageCount) break
  }

  return [...counts.values()]
    .sort((a, b) => b.count - a.count || a.tag.localeCompare(b.tag))
    .slice(0, TAG_ROWS_LIMIT)
}

/**
 * One page of news.
 *
 * Answers in the shape the PHP backend used, so NewsBrowser and the home page
 * section read it without knowing anything changed.
 */
export async function fetchNewsPage(
  apiBase: string,
  options: { limit?: number; page?: number; tag?: string; search?: string } = {}
): Promise<NewsPage> {
  const limit = clamp(options.limit ?? DEFAULT_PER_PAGE, 1, MAX_PER_PAGE)
  const wanted = clamp(options.page ?? 1, 1, 100000)
  const tag = String(options.tag ?? '').trim()
  const search = String(options.search ?? '').trim()

  const read = async (page: number) => {
    const params = new URLSearchParams()
    params.set('populate[image]', 'true')
    // Only the gallery's own fields are read for the list: the small "3 photos"
    // marker needs the count, not the photos themselves.
    params.set('populate[gallery]', 'true')
    params.set('sort[0]', 'createdAt:desc')
    params.set('sort[1]', 'id:desc')
    params.set('pagination[page]', String(page))
    params.set('pagination[pageSize]', String(limit))
    for (const [key, value] of filtersFor(search, tag)) params.set(key, value)

    return strapiRequest(apiBase, params)
  }

  let answer = await read(wanted)
  if (!answer.ok) throw new NewsApiError(`the news API answered HTTP ${answer.status}`)

  let total = Number(answer.body?.meta?.pagination?.total ?? 0)
  let pages = Math.max(1, Number(answer.body?.meta?.pagination?.pageCount ?? 1))
  let page = Math.min(wanted, pages)

  // The number of pages is not known until the count comes back, so a request
  // for a page past the end is asked again with the real last number — the same
  // thing the PHP backend did, so /news/page/999 opens the last page rather
  // than an empty one.
  if (page !== wanted) {
    answer = await read(page)
    if (!answer.ok) throw new NewsApiError(`the news API answered HTTP ${answer.status}`)
    total = Number(answer.body?.meta?.pagination?.total ?? total)
    pages = Math.max(1, Number(answer.body?.meta?.pagination?.pageCount ?? pages))
  }

  const rows = (Array.isArray(answer.body?.data) ? answer.body.data : []).map((doc: any) => toNewsItem(doc))

  const tags = await fetchTagCounts(apiBase)

  return {
    posts: rows,
    news: rows,
    total,
    pages,
    page,
    per_page: limit,
    tags,
  }
}

/**
 * One story, from the piece in the address bar.
 *
 * A plain number is the original address shape (/news/12); anything else is a
 * readable address (/news/softnet-scholarship-2026). Both keep working, so
 * links already posted on Facebook still open the right story.
 *
 * A story that is really not there answers { missing: true } — an answer, not a
 * failure. A request that never got through throws instead, because the two
 * read very differently to a visitor.
 */
export async function fetchNewsItem(
  apiBase: string,
  address: string
): Promise<{ news: NewsItem } | { missing: true }> {
  const wanted = String(address ?? '').trim()
  if (wanted === '') return { missing: true }

  const params = new URLSearchParams()
  params.set('populate[image]', 'true')
  params.set('populate[gallery][populate][image]', 'true')

  if (/^\d+$/.test(wanted)) {
    // An old numeric link. The number is matched against the id kept from the
    // PHP database AND against Strapi's own number, so /news/12 opens the right
    // story whether or not the old ids were carried over.
    const number = String(Number(wanted))
    params.set('filters[$or][0][legacy_id][$eq]', number)
    params.set('filters[$or][1][id][$eq]', number)
  } else {
    params.set('filters[slug][$eq]', wanted.toLowerCase())
  }

  const { status, ok, body } = await strapiRequest(apiBase, params)
  if (status === 404) return { missing: true }
  if (!ok) throw new NewsApiError(`the news API answered HTTP ${status}`)

  const doc = Array.isArray(body?.data) ? body.data[0] : null
  if (!doc) return { missing: true }

  return { news: toNewsItem(doc, { withBody: true, mediaBase: apiBase }) }
}
