<script setup lang="ts">
definePageMeta({ layout: 'default', title: 'About Us' })

useSeoMeta({
  title: 'About Us | SOFTNET Information Technology Center, Inc.',
  description:
    "Learn about SOFTNET's history since 1997, our mission and vision, and our Senior High School and TESDA-based programs in Mariveles, Bataan.",
  ogTitle: 'About Us | SOFTNET Information Technology Center, Inc.',
  ogDescription:
    'Since 1997, SOFTNET has shaped skilled professionals through Senior High School and TESDA-based programs in Bataan.',
  ogImage: '/_sitc/images/cover.webp',
  ogType: 'website',
})

</script>

<template>
  <div>
    <!-- Page hero -->
    <section class="band relative overflow-hidden bg-gray-900 pt-32 pb-16" aria-label="About SOFTNET">
      <div class="pointer-events-none absolute inset-0 opacity-[0.08]" aria-hidden="true">
        <div class="absolute inset-0" style="background-image: radial-gradient(circle, #ffffff 1px, transparent 1px); background-size: 28px 28px;"></div>
      </div>
      <div class="pointer-events-none absolute -right-20 -top-20 h-64 w-64 rounded-full bg-white/5" aria-hidden="true" />
      <div class="pointer-events-none absolute -bottom-16 -left-16 h-48 w-48 rounded-full bg-white/5" aria-hidden="true" />
      <div class="pointer-events-none absolute -right-8 bottom-4 select-none text-[10vw] font-black leading-none tracking-tighter text-white/5" aria-hidden="true">ABOUT</div>
      <div class="container-page relative z-10">
        <p class="text-xs font-bold uppercase tracking-widest text-green-300">About Us</p>
        <h1 class="mt-3 max-w-2xl text-3xl font-extrabold tracking-tight text-white sm:text-4xl lg:text-5xl">{{ site.name }}</h1>
        <p class="mt-4 max-w-2xl text-sm leading-relaxed text-green-100 sm:text-base">{{ site.aboutText }}</p>
      </div>
    </section>
    <!-- History timeline -->
    <section class="relative overflow-hidden bg-gradient-to-b from-green-50/80 via-white to-green-50/80 py-20 sm:py-28" aria-label="School history">
      <div class="pointer-events-none absolute inset-0 opacity-40" style="background-image: radial-gradient(circle, #22c55e 1px, transparent 1px); background-size: 24px 24px;"></div>
      <div class="pointer-events-none absolute -right-16 top-20 select-none text-[12vw] font-black leading-none tracking-tighter text-green-100/60" aria-hidden="true">1997</div>
      <div class="container-page relative z-10">
        <div class="mx-auto max-w-2xl text-center">
          <div class="flex items-center justify-center gap-3">
            <span class="h-px w-10 bg-green-300" aria-hidden="true" />
            <svg class="h-5 w-5 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10" /><polyline points="12 6 12 12 16 14" /></svg>
            <span class="h-px w-10 bg-green-300" aria-hidden="true" />
          </div>
          <h2 class="mt-4 text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl md:text-5xl">History of SOFTNET</h2>
        </div>
        <div class="relative mx-auto mt-16 max-w-4xl">
          <div class="absolute left-6 top-0 h-full w-1 rounded-full bg-gradient-to-b from-green-300 via-green-400 to-green-300 sm:left-1/2 sm:-translate-x-1/2" aria-hidden="true" />
          <div class="space-y-12">
            <div v-for="(entry, i) in site.history" :key="i" class="relative flex gap-8 pl-16 sm:w-1/2 sm:pl-0" :class="i % 2 === 0 ? 'sm:pr-16 sm:text-right' : 'sm:ml-auto sm:pl-16'">
              <span class="absolute left-6 top-2 flex h-8 w-8 -translate-x-1/2 items-center justify-center rounded-full bg-gradient-to-br from-green-500 to-green-600 text-xs font-bold text-white shadow-lg shadow-green-500/30 ring-4 ring-white" :class="i % 2 === 0 ? 'sm:left-full' : 'sm:left-0'" aria-hidden="true">{{ i + 1 }}</span>
              <div class="w-full rounded-2xl border border-gray-200 bg-white p-6 shadow-sm transition-all duration-300 hover:border-green-300 hover:shadow-md sm:p-7">
                <p class="text-sm leading-relaxed text-gray-600">{{ entry }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
    <!-- Mission & Vision -->
    <section class="relative overflow-hidden bg-green-50/60 py-20 sm:py-28" aria-label="Mission and vision">
      <div class="pointer-events-none absolute inset-0 opacity-40" style="background-image: radial-gradient(circle, #22c55e 1px, transparent 1px); background-size: 24px 24px;"></div>
      <div class="container-page relative z-10">
        <div class="mx-auto max-w-2xl text-center">
          <div class="flex items-center justify-center gap-3">
            <span class="h-px w-10 bg-green-300" aria-hidden="true" />
            <svg class="h-5 w-5 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10" /><circle cx="12" cy="12" r="6" /><circle cx="12" cy="12" r="2" /></svg>
            <span class="h-px w-10 bg-green-300" aria-hidden="true" />
          </div>
          <h2 class="mt-4 text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl md:text-5xl">Mission &amp; Vision</h2>
        </div>
        <div class="mt-16 grid gap-8 md:grid-cols-2">
          <div class="group relative rounded-2xl border border-gray-200 bg-white p-8 shadow-sm transition-all duration-300 hover:border-green-300 hover:bg-green-50/50 hover:shadow-md sm:p-10">
            <div class="absolute right-0 top-0 h-24 w-24 rounded-bl-full bg-gradient-to-bl from-green-100 to-transparent transition-opacity duration-300 group-hover:from-green-200" aria-hidden="true" />
            <div class="relative">
              <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-green-500 to-green-600 text-white shadow-lg shadow-green-500/20">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10" /><circle cx="12" cy="12" r="6" /><circle cx="12" cy="12" r="2" /></svg>
              </div>
              <h3 class="mt-6 text-2xl font-extrabold tracking-tight text-gray-900">Our Mission</h3>
              <p class="mt-4 text-sm leading-relaxed text-gray-600 sm:text-base">{{ site.mission }}</p>
            </div>
          </div>
          <div class="group relative rounded-2xl border border-gray-200 bg-white p-8 shadow-sm transition-all duration-300 hover:border-green-300 hover:bg-green-50/50 hover:shadow-md sm:p-10">
            <div class="absolute right-0 top-0 h-24 w-24 rounded-bl-full bg-gradient-to-bl from-green-100 to-transparent transition-opacity duration-300 group-hover:from-green-200" aria-hidden="true" />
            <div class="relative">
              <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-green-500 to-green-600 text-white shadow-lg shadow-green-500/20">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" /><circle cx="12" cy="12" r="3" /></svg>
              </div>
              <h3 class="mt-6 text-2xl font-extrabold tracking-tight text-gray-900">Our Vision</h3>
              <p class="mt-4 text-sm leading-relaxed text-gray-600 sm:text-base">{{ site.vision }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>
    <!-- SOFTNET Hymn -->
    <section class="relative overflow-hidden bg-green-50/60 py-20 sm:py-28" aria-label="SOFTNET Hymn">
      <div class="pointer-events-none absolute inset-0 opacity-40" style="background-image: radial-gradient(circle, #22c55e 1px, transparent 1px); background-size: 24px 24px;"></div>
      <img src="/_sitc/images/logo.webp" alt="" aria-hidden="true" class="pointer-events-none absolute left-1/2 top-1/2 h-[60vh] w-auto -translate-x-1/2 -translate-y-1/2 select-none opacity-[0.04]" />
      <div class="container-page relative z-10">
        <div class="mx-auto max-w-2xl text-center">
          <div class="flex items-center justify-center gap-3">
            <span class="h-px w-10 bg-green-300" aria-hidden="true" />
            <svg class="h-5 w-5 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18V5l12-2v13" /><circle cx="6" cy="18" r="3" /><circle cx="18" cy="16" r="3" /></svg>
            <span class="h-px w-10 bg-green-300" aria-hidden="true" />
          </div>
          <h2 class="mt-4 text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl md:text-5xl">SOFTNET Hymn</h2>
        </div>
        <div class="mx-auto mt-16 max-w-2xl">
          <div class="space-y-10">
            <div v-for="(verse, i) in site.hymn" :key="i">
              <div class="flex items-center justify-center gap-3">
                <span v-if="verse.chorus" class="inline-flex items-center gap-1.5 rounded-full border border-green-200 bg-green-100 px-3 py-1 text-[11px] font-bold uppercase tracking-widest text-green-700">
                  <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 3v10.55A4 4 0 1 0 14 17V7h4V3h-6Z" /></svg>
                  Chorus
                </span>
                <span v-else class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-gray-100 px-3 py-1 text-[11px] font-bold uppercase tracking-widest text-gray-500">Verse {{ i + 1 }}</span>
              </div>
              <div class="mt-4 space-y-1.5 text-center">
                <p v-for="(line, j) in verse.lines" :key="j" class="text-base leading-relaxed text-gray-700 sm:text-lg" :class="verse.chorus ? 'font-semibold text-gray-900' : 'italic text-gray-600'">{{ line }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
    <BackToTop />
  </div>
</template>