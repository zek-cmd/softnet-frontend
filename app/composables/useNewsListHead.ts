/**
 * The <head> of the news list, shared by /news and /news/page/2.
 *
 * Both addresses show the same list with a different page number, so only the
 * title and the canonical address differ. Writing them here rather than in each
 * page keeps the two from drifting apart — the mistake that would let Google
 * treat page 3 as a second copy of page 1.
 *
 * WHY IT MATTERS HERE
 *
 * These pages used to be served by php-backend/news-view.php, which wrote exactly
 * these tags in PHP before handing the page to the app. The site is now served
 * by Node, so the same tags are written by the page itself, on every request —
 * and the number of pages the list has is not known until the news API is
 * asked, which is why the canonical is only claimed once the list is read.
 *
 * The address handed out is THIS site's, never the news backend's: a reader who
 * shares a list must land on the page, not on an API.
 */
export function useNewsListHead(page: () => number) {
  const { published: siteUrl } = useNewsApi()

  const current = computed(() => Math.max(1, Number(page()) || 1))

  const heading = computed(() =>
    current.value > 1 ? `News & Updates — page ${current.value}` : 'News & Updates'
  )

  const canonical = computed(() =>
    siteUrl + (current.value > 1 ? `/news/page/${current.value}` : '/news')
  )

  useSeoMeta({
    title: () => `${heading.value} | SOFTNET Information Technology Center, Inc.`,
    description:
      'News, announcements, and updates from SOFTNET Information Technology Center, Inc. — enrollment, events, and student achievements.',
    ogTitle: () => heading.value,
    ogDescription: () =>
      'What is happening at SOFTNET — enrollment, events, and student achievements.',
    // Facebook will not read a relative path, so the cover photo is named with
    // the site's full address.
    ogImage: () => siteUrl + '/_sitc/images/cover.webp',
    ogType: 'website',
  })

  useHead({
    // A function, not the value itself: the canonical is written out when the
    // page is rendered, and this keeps it reactive when the reader moves between
    // pages of the list without a reload.
    link: [{ rel: 'canonical', href: () => canonical.value }],
  })
}
