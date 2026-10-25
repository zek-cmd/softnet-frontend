# SOFTNET Information Technology Center — website

One website, one process, one place for the news.

The public site of SOFTNET Information Technology Center, Inc. — a Senior High School and
TESDA-based institution in Mariveles, Bataan. It presents the school, its programs and its
facilities, and it publishes the school's news.

| Folder | What it is |
|---|---|
| `app/` + `nuxt.config.ts` | **Everything a visitor sees** — home, about, contact and news |
| `static/` | The site's own images (`_sitc/images/…`) |
| `frontend.md` | A reference for how the site **looks** — read it before changing any styling |

The news and the admin live in a **Strapi** backend, in its own folder outside this project
(`Documents\strapi`).

### Who owns what

```mermaid
flowchart TD
  A("The site - every page") -->|"reads news"| B("Strapi backend")
  C("Staff admin - Strapi") -->|"writes stories"| B
  B --> D[("MySQL")]
  B --> E("Uploaded photos")
  A -->|"its own images"| F("static/")
```

- **The site is one program.** Nuxt answers every address, including the news pages, and it is
  the only thing a visitor ever talks to.
- **The news is data, not pages.** Strapi answers `/api/news`; the site reads it. A story posted
  a minute ago is on the site immediately — nothing is rebuilt or uploaded.
- **The admin is Strapi's own.** There are no admin screens inside this project.
- **Nothing here is PHP.** The old PHP backend has been deleted.

---

## 1. How a news page works

This is the part worth understanding, because it is why the site needs Node at all.

**Facebook, Messenger, WhatsApp and Google do not run JavaScript.** They read the HTML the
server sends and build the preview card from the tags in `<head>`. A story's real title and
cover photo live in a database, so they have to be written into the page *before* it leaves the
server.

That used to be done by one PHP file, and it was the only reason PHP was still on the site. Now
the page is built by the server on every request, so **`app/pages/news/[slug].vue` writes those
tags itself** — and nothing else is needed.

- **the visitor gets a real story, in the site's own design**, drawn from the news API;
- **a crawler or a chat app gets the real title, summary and photo** in the HTML itself;
- **nothing is baked into a file**, so a story corrected in the admin shows the correction on
  the next visit.

The list works the same way: `/news` and `/news/page/2` are read from the news API **on the
server** (`app/components/NewsBrowser.vue`), so Google and a reader with JavaScript switched off
see a real list of stories rather than an empty shell.

`/about` and `/contact` are the only pages written to files ahead of time (`nitro.prerender` in
`nuxt.config.ts`) — their text never changes. The home page is deliberately **not** in that list:
it shows the latest news.

### Addresses

| Address | What it is |
|---|---|
| `/` | Home: welcome, programs, facilities, the latest news |
| `/about` | The school's history, mission, vision and hymn |
| `/contact` | Address, map and an enquiry form |
| `/news` | The list: lead story, grid, tag filter, search, paging |
| `/news/page/2` | Page 2 — a real address of its own, with its own canonical link |
| `/news/sports-day` | One story: cover photo, tags, body, photo gallery, share row |
| `/news/12` | The **old** numeric address — still opens the right story |

The site is one process, not a folder of files, so there is no `index.html` and none is needed:
the server draws every page, and `/`, `/news` and `/news/<slug>` all work from the running
process.

---

## 2. The news data (`app/utils/news-api.ts`)

The pages were written when the old PHP backend answered them in one particular shape: a list
with `news`, `total`, `pages` and a ready-made tag row; a story with `date_formatted`, `content`
as HTML and a `gallery`. Strapi speaks a different language — `data`, `meta.pagination`, blocks,
media objects.

**That translation lives in one file.** Every page still reads the old shape, so no page had to
learn a second one — and this file is the only place that knows Strapi exists.

It also decides what a failure *is*:

- **no such story** → the page answers **404**, says so, and is marked `noindex`;
- **the news API is unreachable or unset** → the page answers **503** and says so;
- **there is no news yet** → a normal empty page.

The difference matters: a broken backend must never be published to Google as “this page does
not exist”, and it must never make a visitor think the school has posted nothing.

### The story body

Strapi's editor can store a story as Markdown or as blocks, and `app/utils/news-api.ts` turns
either into HTML — and **escapes every piece of text as it does**, so a story can never bring a
script into the page. That escaping is what makes `v-html` safe in `[slug].vue`.

Pictures inside a story body are made **absolute against the news host**, because that HTML is
printed exactly as it is — nothing is left to add the host afterwards, and the uploads live on
the news host, not here.

### Slow-answering backend: retries

`app/utils/news-fetch.ts` retries every news request, and after that asks the same file over
plain `http` (measurements on the old host showed https hanging on one try in six while http
answered every time). **On the server the patience is shorter** — two tries at five seconds —
because a page is now rendered when it is asked for, and a reader should not be held at a blank
tab by a backend that is really down. In the browser the longer patience is kept.

If every try fails, the failure is reported. Nothing is ever invented to fill a gap.

---

## 3. Two settings, one place

```powershell
# local, in .env
NUXT_PUBLIC_NEWS_API_URL=http://localhost:1337   # where the news is read from
NUXT_PUBLIC_SITE_URL=http://localhost:3000       # where THIS site answers
```

Both are read through `app/composables/useNewsApi.ts`, so the home page section, the news list
and the story page can never disagree about which host holds the news.

They are **two different things and must not be confused**:

| Setting | What it is | What breaks if it is wrong |
|---|---|---|
| `NUXT_PUBLIC_NEWS_API_URL` | the Strapi backend | no news at all; every news page answers 503 |
| `NUXT_PUBLIC_SITE_URL` | where the site answers | every shared story and every canonical link points at the wrong address |

`NUXT_SITE_URL` / `NUXT_NEWS_API` (without `NUXT_PUBLIC_`) also work, and are read once when the
site is built. Prefer the `NUXT_PUBLIC_` pair: those are read when it **runs**, so changing an
address needs a restart, not a new build.

`app/utils/site.js` holds a fallback for both, and the news one is **deliberately empty** — an
empty value makes the news pages say they are not configured, while a wrong one would quietly
show nothing, or point a live site at somebody's laptop.

---

## 4. Security notes (for whoever maintains this next)

- **The story body is escaped where it is turned into HTML** (`blocksToHtml` and
  `markdownToHtml` in `app/utils/news-api.ts`). A story's text is never trusted: the block types
  are a fixed list, every piece of text is escaped, and only real `http(s)` addresses become
  links. At the moment the ready-made HTML reaches `v-html`, that escaping is the only thing
  standing between a story and a script running for every visitor.
- **The news API is read, never trusted.** A reply that is not the shape expected produces an
  empty list or a stated failure, never HTML of its own.
- **Failures are stated, never faked.** 404 for a story that is not there, 503 when the news
  backend cannot be answered, and never a page that quietly shows nothing.
- **No secrets in this project.** Strapi's database password lives in the Strapi folder's own
  `.env`, and the site holds no credentials at all. `.env` and the build output are in
  `.gitignore`, as are the deploy bundles, which carry an `.env`.
- **Strapi's public role must stay read-only** — find and findOne on News, nothing more.

---

## License

Released under the [MIT License](LICENSE).

Copyright (c) 2026 SOFTNET Information Technology Center, Inc.
