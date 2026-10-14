/**
 * The handoff between the home page's big cover logo and the small one in the
 * navbar, as a set of numbers both of them use.
 *
 * The home page opens on a large SOFTNET logo. As the visitor scrolls off that
 * cover, the big logo shrinks, fades and drifts up while the small navbar logo
 * grows in — so the two read as one mark moving up into the bar, instead of one
 * logo vanishing and a different one appearing somewhere else.
 *
 * The home page and the navbar both need these numbers, and they have to agree.
 * If the navbar waited for the cover to be fully gone, there would be a stretch
 * of scrolling showing no logo at all; if it arrived too early, two logos would
 * sit on screen at once. The formulas below are what make the two halves
 * overlap correctly, so they live here in one place rather than being written
 * out twice where they could drift apart.
 *
 * A page with no cover (anything but '/') reports the cover as untouched and
 * the navbar logo as fully shown, which is exactly how those pages look today.
 */
export function useCoverShrink() {
  const route = useRoute()

  // 0 = the cover is untouched at the very top of the page.
  // 1 = it has been scrolled most of the way off.
  const progress = ref(0)
  const reduced = ref(false)

  const isHome = computed(() => route.path === '/')

  // How much scrolling takes `progress` from 0 to 1, as a share of the screen
  // height. The cover is 92vh tall and its logo sits around the middle, so a
  // bit over half a screen puts that logo right at the top edge — which is the
  // moment it should be gone.
  const COVER_SPAN = 0.55

  // Where the navbar logo starts arriving, and how long it takes. It begins
  // once the cover logo is already clearly on its way out, and is complete
  // before the cover logo has finished fading (at 0.85), so the eye always has
  // one logo to follow.
  const NAV_START = 0.3
  const NAV_SPAN = 0.55
  const COVER_FADE_DONE = 0.85

  const clamp01 = (n: number) => Math.min(1, Math.max(0, n))

  // Slow at the end, quick at the start — the arrival feels deliberate rather
  // than mechanical.
  const easeOut = (t: number) => 1 - Math.pow(1 - t, 3)

  function measure() {
    if (!import.meta.client) return
    if (!isHome.value) {
      progress.value = 0
      return
    }
    const viewport = window.innerHeight || 1
    progress.value = clamp01(window.scrollY / (viewport * COVER_SPAN))
  }

  // --- the big logo on the cover -------------------------------------------
  // A reduced-motion visitor keeps the logo at full size: nothing moves, which
  // is the whole point of that setting.
  const coverScale = computed(() => (reduced.value ? 1 : 1 - 0.6 * progress.value))
  const coverOpacity = computed(() =>
    reduced.value ? 1 : clamp01(1 - progress.value / COVER_FADE_DONE),
  )
  const coverLift = computed(() => (reduced.value ? 0 : -28 * progress.value))

  // --- the small logo in the navbar ----------------------------------------
  const navReveal = computed(() => {
    if (reduced.value || !isHome.value) return 1
    return easeOut(clamp01((progress.value - NAV_START) / NAV_SPAN))
  })
  const navScale = computed(() => 0.3 + 0.7 * navReveal.value)
  const navLift = computed(() => 12 * (1 - navReveal.value))

  let query: MediaQueryList | null = null

  const onPreferenceChange = () => {
    reduced.value = query ? query.matches : false
  }
  const onViewportChange = () => measure()

  onMounted(() => {
    query = window.matchMedia('(prefers-reduced-motion: reduce)')
    reduced.value = query.matches
    query.addEventListener('change', onPreferenceChange)

    window.addEventListener('scroll', onViewportChange, { passive: true })
    window.addEventListener('resize', onViewportChange, { passive: true })

    // Measured at once so a page opened part-way down (a refresh, or a link
    // with a hash) shows the right state straight away instead of the logo
    // sitting in the wrong place until the first scroll.
    measure()
  })

  onUnmounted(() => {
    window.removeEventListener('scroll', onViewportChange)
    window.removeEventListener('resize', onViewportChange)
    query?.removeEventListener('change', onPreferenceChange)
  })

  // A different page means a different cover, or none at all.
  watch(() => route.path, () => measure())

  return { coverScale, coverOpacity, coverLift, navScale, navLift, navReveal }
}
