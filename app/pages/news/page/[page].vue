<script setup lang="ts">
// /news/page/2, /news/page/3, ...
//
// The same list as /news, one page further on. It is a real address of its own
// rather than a ?page= query because an address is what can be linked to, shared
// and indexed — and because the component reads the page number from the address
// bar.
//
// Rendered on the server when it is opened, like /news.

definePageMeta({ layout: 'default' })

const route = useRoute()

// The number in the address bar, used for the hero text, the tab title and the
// canonical address. /news/page/1 is shown as page 2, exactly as the component
// treats it, so the heading and the list can never announce different pages.
const pageNumber = computed(() => {
  const raw = Array.isArray(route.params.page) ? route.params.page[0] : route.params.page
  const number = Number(raw)
  return Number.isFinite(number) && number > 1 ? Math.floor(number) : 2
})

useNewsListHead(() => pageNumber.value)
</script>

<template>
  <div>
    <section class="band relative overflow-hidden bg-gray-900 pt-32 pb-16" aria-label="News and updates">
      <div class="pointer-events-none absolute inset-0 opacity-[0.08]" aria-hidden="true">
        <div class="absolute inset-0" style="background-image: radial-gradient(circle, #ffffff 1px, transparent 1px); background-size: 28px 28px;"></div>
      </div>
      <div class="pointer-events-none absolute -right-20 -top-20 h-64 w-64 rounded-full bg-white/5" aria-hidden="true" />
      <div class="pointer-events-none absolute -bottom-16 -left-16 h-48 w-48 rounded-full bg-white/5" aria-hidden="true" />
      <div class="pointer-events-none absolute -right-8 bottom-4 select-none text-[10vw] font-black leading-none tracking-tighter text-white/5" aria-hidden="true">NEWS</div>
      <div class="container-page relative z-10">
        <p class="text-xs font-bold uppercase tracking-widest text-green-300">News</p>
        <h1 class="mt-3 max-w-2xl text-3xl font-extrabold tracking-tight text-white sm:text-4xl lg:text-5xl">News &amp; Updates</h1>
        <p class="mt-4 max-w-2xl text-sm leading-relaxed text-green-100 sm:text-base">Page {{ pageNumber }} of the news archive.</p>
      </div>
    </section>

    <div class="container-page py-10 sm:py-14">
      <NuxtLink to="/" class="back-link">&larr; Back to Home</NuxtLink>
      <NewsBrowser />
    </div>

    <!-- The same floating button the other pages carry. -->
    <BackToTop />
  </div>
</template>
