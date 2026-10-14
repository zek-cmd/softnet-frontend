<script setup>
// ref, watch, useRoute and the lifecycle hooks come from Nuxt's auto-imports;
// importing them here as well would shadow those.

const route = useRoute()
const mobileOpen = ref(false)

// The logo handoff with the home page's cover. On every other page — and for a
// visitor who asked for reduced motion — navReveal is 1 and navScale is 1, so
// the logo simply sits there at full size, which is how it has always looked.
const { navScale, navLift, navReveal } = useCoverShrink()

// "News" is a normal internal link now. The news pages used to be served by the
// PHP backend, which is why this link was once a plain anchor that caused a
// full page load. They are Nuxt pages now (/news, /news/<slug>, /news/page/2),
// so this is a NuxtLink like the rest and navigating to it is instant.
const links = [
  { to: '/', label: 'Home' },
  { to: '/about', label: 'About' },
  { to: '/news', label: 'News' },
  { to: '/contact', label: 'Contact' },
]

function isCurrent(link) {
  // A news page is "News", including /news/<slug> and /news/page/2 — so the
  // link stays filled in green while the reader is anywhere inside the section.
  if (link.to === '/news') return route.path === '/news' || route.path.startsWith('/news/')
  return route.path === link.to
}

function closeMobile() {
  mobileOpen.value = false
}

// Close the mobile drawer when the address changes, so it never sits open over
// the page the visitor just asked for.
watch(() => route.path, () => {
  mobileOpen.value = false
})
</script>

<template>
  <header
    class="fixed inset-x-0 top-0 z-50 bg-white shadow-sm shadow-gray-900/5 transition-all duration-300"
  >
    <div class="container-page flex min-h-20 items-center justify-between gap-4">
      <!--
        The logo group scales and fades as ONE piece, the same way the cover's
        logo group does — scaling each image on its own would make them close in
        on their own centres and read as two objects, not one mark.

        It is pinned to its LEFT edge and lifts slightly, so it grows up into
        the bar from where the big logo left it rather than swelling from the
        middle. On any page without a cover, navScale is 1 and navReveal is 1:
        the group simply sits there, exactly as it always has.
      -->
      <NuxtLink
        to="/"
        class="flex items-center gap-3"
        aria-label="SOFTNET home"
        style="transform-origin: left center"
        :style="{
          transform: `translateY(${navLift}px) scale(${navScale})`,
          opacity: navReveal,
        }"
        :class="navReveal < 0.5 ? 'pointer-events-none' : ''"
        @click="closeMobile"
      >
        <img
          src="/_sitc/images/logo.webp"
          alt="SOFTNET logo"
          class="h-11 w-11 rounded-full object-cover ring-2 ring-green-600/20"
          width="44"
          height="44"
        />
        <img
          src="/_sitc/images/softnet-wordmark.png"
          alt="SOFTNET Information Technology Center"
          class="h-8 w-auto sm:h-9 lg:h-10"
        />
      </NuxtLink>

      <nav class="hidden items-center gap-1 lg:flex" aria-label="Primary">
        <NuxtLink
          v-for="link in links"
          :key="link.to"
          :to="link.to"
          class="rounded-full px-4 py-2 text-sm font-semibold transition-colors"
          :class="
            isCurrent(link)
              ? 'bg-green-600 text-white'
              : 'text-gray-700 hover:bg-green-50 hover:text-green-700'
          "
        >
          {{ link.label }}
        </NuxtLink>
      </nav>

      <button
        type="button"
        class="relative flex h-11 w-11 items-center justify-center rounded-full text-gray-800 transition-colors hover:bg-green-50 lg:hidden"
        :aria-expanded="mobileOpen"
        aria-controls="mobile-menu"
        aria-label="Toggle navigation menu"
        @click="mobileOpen = !mobileOpen"
      >
        <span class="sr-only">Menu</span>
        <div class="relative flex h-4 w-5 flex-col justify-between">
          <span class="h-0.5 w-full rounded bg-current" />
          <span class="h-0.5 w-full rounded bg-current" />
          <span class="h-0.5 w-full rounded bg-current" />
        </div>
      </button>
    </div>

    <!-- Backdrop -->
    <Transition
      enter-active-class="transition-opacity duration-300 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-200 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="mobileOpen"
        class="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm lg:hidden"
        @click="closeMobile"
      />
    </Transition>

    <!-- Slide-in panel from right -->
    <Transition
      enter-active-class="transition-transform duration-300 ease-out"
      enter-from-class="translate-x-full"
      enter-to-class="translate-x-0"
      leave-active-class="transition-transform duration-200 ease-in"
      leave-from-class="translate-x-0"
      leave-to-class="translate-x-full"
    >
      <nav
        v-if="mobileOpen"
        id="mobile-menu"
        class="fixed right-0 top-0 z-50 flex h-full w-72 flex-col bg-white shadow-2xl lg:hidden"
        aria-label="Mobile"
      >
        <div class="flex flex-1 flex-col gap-1 overflow-y-auto p-4 pt-6">
          <NuxtLink
            v-for="link in links"
            :key="link.to"
            :to="link.to"
            class="rounded-xl px-4 py-3 text-sm font-semibold transition-colors"
            :class="
              isCurrent(link)
                ? 'bg-green-600 text-white'
                : 'text-gray-700 hover:bg-green-50 hover:text-green-700'
            "
            @click="closeMobile"
          >
            {{ link.label }}
          </NuxtLink>
        </div>
      </nav>
    </Transition>
  </header>
</template>