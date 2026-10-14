<script setup lang="ts">
// /news — the first page of the news list.
//
// The list itself lives in app/components/NewsBrowser.vue, because it is shown
// at more than one address (/news, /news/page/2, ...). This page only supplies
// the hero band and the <head>.
//
// Nothing is written to a file at build time: the page is rendered on the
// server when it is opened, and the list is read from the news API at that
// moment, so a story posted a minute ago is already on it.

definePageMeta({ layout: 'default' })

useNewsListHead(() => 1)
</script>

<template>
  <div>
    <!-- Hero band, copied class for class from the about and contact pages. -->
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
        <p class="mt-4 max-w-2xl text-sm leading-relaxed text-green-100 sm:text-base">What is happening at SOFTNET — enrollment, events, and student achievements.</p>
      </div>
    </section>

    <div class="container-page py-10 sm:py-14">
      <NuxtLink to="/" class="back-link">&larr; Back to Home</NuxtLink>
      <NewsBrowser />
    </div>

    <!-- The floating button, the same one the home, about and contact pages
         carry. The news list is long, so it is the page that needs it most. -->
    <BackToTop />
  </div>
</template>
