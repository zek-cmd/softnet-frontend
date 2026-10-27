<script setup lang="ts">
definePageMeta({ layout: 'default', title: 'Home' })
useSeoMeta({
  title: 'SOFTNET Information Technology Center, Inc. | Mariveles, Bataan',
  description: site.aboutText,
  ogTitle: 'SOFTNET Information Technology Center, Inc.',
  ogDescription: 'Senior High School and TESDA-based programs in Mariveles, Bataan.',
  ogImage: '/_sitc/images/cover.webp',
  ogType: 'website',
  twitterCard: 'summary_large_image',
})

const scrollRef = ref<HTMLElement | null>(null)

// The big cover logo shrinks, fades and drifts up as this page is scrolled off,
// handing over to the small logo in the navbar — see useCoverShrink.
const { coverScale, coverOpacity, coverLift } = useCoverShrink()

const scrollByCard = (direction: 1 | -1) => {
  const el = scrollRef.value
  if (!el) return
  const card = el.querySelector<HTMLElement>('img')
  const step = card ? card.offsetWidth + 16 : 300
  el.scrollBy({ left: step * direction, behavior: 'smooth' })
}

// Drag-to-scroll with momentum / inertia after release
let isDown = false
let startX = 0
let startScrollLeft = 0
let lastX = 0
let lastTime = 0
let velocity = 0 // px per ms
let inertiaFrame: number | null = null

const stopInertia = () => {
  if (inertiaFrame !== null) {
    cancelAnimationFrame(inertiaFrame)
    inertiaFrame = null
  }
}

const onPointerDown = (e: PointerEvent) => {
  const el = scrollRef.value
  if (!el) return
  stopInertia()
  isDown = true
  startX = e.clientX
  lastX = e.clientX
  lastTime = performance.now()
  velocity = 0
  startScrollLeft = el.scrollLeft
  el.setPointerCapture(e.pointerId)
  el.classList.add('dragging')
}

const onPointerMove = (e: PointerEvent) => {
  const el = scrollRef.value
  if (!el || !isDown) return
  const now = performance.now()
  const dx = e.clientX - lastX
  const dt = now - lastTime || 1
  // Track instantaneous velocity (px/ms), smoothed
  velocity = velocity * 0.6 + (dx / dt) * 0.4
  lastX = e.clientX
  lastTime = now

  const totalDx = e.clientX - startX
  el.scrollLeft = startScrollLeft - totalDx
}

const runInertia = () => {
  const el = scrollRef.value
  if (!el) return

  // velocity is in px/ms; convert to per-frame and apply friction
  let v = velocity * 16 // px per frame
  const friction = 0.94

  const tick = () => {
    if (Math.abs(v) < 0.3) {
      inertiaFrame = null
      return
    }
    el.scrollLeft -= v
    v *= friction
    inertiaFrame = requestAnimationFrame(tick)
  }

  tick()
}

const onPointerUp = (e: PointerEvent) => {
  const el = scrollRef.value
  if (!el) return
  const wasDown = isDown
  isDown = false
  try {
    el.releasePointerCapture(e.pointerId)
  } catch {}
  el.classList.remove('dragging')

  // Only apply inertia if the user was actually dragging
  if (wasDown && Math.abs(velocity) > 0.05) {
    runInertia()
  }
}
</script>

<template>
  <div>
    <!-- Hero -->
    <section class="band relative flex h-screen min-h-screen items-center overflow-hidden bg-gray-50" aria-label="Welcome to SOFTNET">
      <img
        src="/_sitc/images/outside.webp"
        alt=""
        aria-hidden="true"
        class="absolute inset-0 h-full w-full object-cover opacity-10"
        loading="lazy"
        decoding="async"
      />
      <div class="container-page relative z-10 py-28">
        <div class="flex flex-col items-center justify-center text-center">
          <div
            class="mt-10 flex flex-wrap items-center justify-center gap-5"
            :style="{
              transform: `scale(${coverScale}) translateY(${coverLift}px)`,
              opacity: coverOpacity,
            }"
          >
            <img src="/_sitc/images/logo.webp" alt="SOFTNET logo" class="h-32 w-auto shrink-0 sm:h-40 lg:h-48" />
            <img src="/_sitc/images/softnet-wordmark.png" alt="SOFTNET wordmark" class="h-16 w-auto sm:h-20 lg:h-24" />
          </div>
          <p class="rainbow-text mt-10 text-lg font-extrabold tracking-tight sm:text-xl md:text-2xl">
            #WhereFunAndLearningConnect!
          </p>
        </div>
      </div>
    </section>
    <!-- Accreditation seals -->
    <SealStrip />
    <!-- About preview -->
    <section class="section relative overflow-hidden bg-gradient-to-b from-green-50/80 via-white to-green-50/80" aria-label="About SOFTNET">
      <div class="pointer-events-none absolute inset-0 opacity-40" style="background-image: radial-gradient(circle, #22c55e 1px, transparent 1px); background-size: 24px 24px;"></div>
      <div class="container-page relative z-10 grid items-center gap-12 lg:grid-cols-2">
        <div>
          <h2 class="mt-3 text-2xl font-extrabold tracking-tight text-gray-900 sm:text-3xl md:text-4xl">
            A Center of Excellence in <span class="heading-underline">Technical Education</span>
          </h2>
          <p class="mt-6 text-sm leading-relaxed text-gray-600 sm:text-base">
            Since 1997, SOFTNET Information Technology Center has been shaping skilled and
            values-driven professionals. From a single lecture room with two computers, we have
            grown into a full technical-vocational institution in Mariveles, Bataan.
          </p>
          <p class="mt-4 text-sm leading-relaxed text-gray-600 sm:text-base">
            We offer Senior High School and TESDA-based programs designed to make every learner
            industry-ready and globally competitive.
          </p>
          <NuxtLink to="/about" class="btn-primary mt-8">Learn More About Us</NuxtLink>
        </div>
        <div class="mx-auto max-w-md">
          <div class="rounded-2xl bg-gray-900 p-6 shadow-lg ring-1 ring-gray-800">
            <div class="relative flex flex-col gap-4">
              <!-- Graduation cap — plain green icon, tilted perpendicular on the top-left corner -->
              <svg
                class="absolute -left-10 -top-10 z-10 h-14 w-14 -rotate-45 text-green-500 sm:-left-12 sm:-top-12 sm:h-16 sm:w-16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
              >
                <path d="M22 10 12 5 2 10l10 5 10-5Z" />
                <path d="M6 12v5c0 1.5 2.7 3 6 3s6-1.5 6-3v-5" />
                <path d="M22 10v6" />
              </svg>

              <img src="/_sitc/images/homeofinnovators.png" alt="Home of Innovators" class="w-auto object-contain" loading="lazy" decoding="async" />
            </div>
          </div>
        </div>
      </div>
    </section>
    <!-- Programs preview -->
    <section class="section relative overflow-hidden bg-gradient-to-b from-green-50/80 via-white to-green-50/80" aria-label="Featured programs">
      <div class="pointer-events-none absolute inset-0 opacity-40" style="background-image: radial-gradient(circle, #22c55e 1px, transparent 1px); background-size: 24px 24px;"></div>
      <div class="container-page relative z-10">
        <div class="mx-auto max-w-2xl text-center">
          <h2 class="text-2xl font-extrabold tracking-tight text-gray-900 sm:text-3xl md:text-4xl">Academic Programs</h2>
        </div>
        <div class="mt-12 grid gap-8 lg:grid-cols-2">
          <div class="relative">
            <img
              src="/_sitc/images/tesda.webp"
              alt=""
              aria-hidden="true"
              class="pointer-events-none absolute inset-0 h-full w-full object-contain opacity-[0.05]"
              loading="lazy"
              decoding="async"
            />
            <div class="relative z-10">
              <h3 class="text-lg font-extrabold tracking-tight text-gray-900">TESDA</h3>
              <p class="mt-1 text-sm text-gray-500">Three-Year TESDA Diploma Programs</p>
              <ul class="mt-6 space-y-4">
                <li v-for="program in homePrograms()" :key="program.title" class="card flex items-center gap-4 bg-white/40 p-5">
                  <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-green-600 text-white">
                    <svg v-if="program.icon === 'computer'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2" /><path d="M8 21h8M12 17v4" /></svg>
                    <svg v-else-if="program.icon === 'chef'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 13.87A4 4 0 0 1 7.41 6a5.11 5.11 0 0 1 1.05-1.54 5 5 0 0 1 7.08 0A5.11 5.11 0 0 1 16.59 6 4 4 0 0 1 18 13.87V21H6Z" /><path d="M6 17h12" /></svg>
                    <svg v-else-if="program.icon === 'microchip'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="2" /><rect x="9" y="9" width="6" height="6" /><path d="M9 1v3M15 1v3M9 20v3M15 20v3M1 9h3M1 15h3M20 9h3M20 15h3" /></svg>
                    <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z" /></svg>
                  </div>
                  <div><h4 class="text-base font-bold text-gray-900">{{ program.title }}</h4></div>
                </li>
              </ul>
            </div>
          </div>
          <div class="relative">
            <img
              src="/_sitc/images/deped-seal.webp"
              alt=""
              aria-hidden="true"
              class="pointer-events-none absolute inset-0 h-full w-full object-contain opacity-[0.05]"
              loading="lazy"
              decoding="async"
            />
            <div class="relative z-10">
              <h3 class="text-lg font-extrabold tracking-tight text-gray-900">Senior High School</h3>
              <p class="mt-1 text-sm text-gray-500">Academic &amp; Technical-Professional Tracks</p>
              <div class="mt-6 space-y-6">
                <div v-for="track in [site.academicTrack, site.techProTrack]" :key="track.title" class="card bg-white/40 p-5">
                  <h4 class="text-base font-bold text-gray-900">{{ track.title }}</h4>
                  <p class="mt-3 text-xs font-bold uppercase tracking-wider text-green-600">Elective Clusters</p>
                  <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li v-for="elective in track.electives" :key="elective" class="text-sm text-gray-600">
                      {{ elective }}
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
    <!-- Facilities with left/right buttons + touch/drag scroll -->
    <section class="section relative overflow-hidden bg-green-50/60" aria-label="School facilities">
      <div class="pointer-events-none absolute inset-0 opacity-40" style="background-image: radial-gradient(circle, #22c55e 1px, transparent 1px); background-size: 24px 24px;"></div>
      <div class="container-page relative z-10">
        <div class="mx-auto max-w-2xl text-center">
          <h2 class="mt-3 text-2xl font-extrabold tracking-tight text-gray-900 sm:text-3xl md:text-4xl">Rooms and Laboratories</h2>
        </div>

        <div class="relative mt-12">
          <!-- Left button -->
          <button
            type="button"
            class="absolute -left-4 top-1/2 z-20 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white text-green-700 shadow-lg ring-1 ring-gray-200 transition-all duration-300 hover:bg-green-50 active:scale-95 sm:-left-6 sm:h-12 sm:w-12"
            aria-label="Scroll facilities left"
            @click="scrollByCard(-1)"
          >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6" /></svg>
          </button>

          <!-- Scrollable row — fixed-size cards, never squeezes -->
          <div class="w-full overflow-hidden">
            <div
              ref="scrollRef"
              class="facility-scroll flex gap-4"
              @pointerdown="onPointerDown"
              @pointermove="onPointerMove"
              @pointerup="onPointerUp"
              @pointercancel="onPointerUp"
              @pointerleave="onPointerUp"
            >
              <img
                v-for="facility in site.facilities"
                :key="facility.img"
                :src="facility.img"
                :alt="facility.alt"
                class="facility-card shrink-0 rounded-2xl object-cover shadow-sm ring-1 ring-gray-200"
                loading="lazy"
                decoding="async"
              />
            </div>
          </div>

          <!-- Right button -->
          <button
            type="button"
            class="absolute -right-4 top-1/2 z-20 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white text-green-700 shadow-lg ring-1 ring-gray-200 transition-all duration-300 hover:bg-green-50 active:scale-95 sm:-right-6 sm:h-12 sm:w-12"
            aria-label="Scroll facilities right"
            @click="scrollByCard(1)"
          >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6" /></svg>
          </button>
        </div>
      </div>
    </section>
    <!-- Latest News -->
    <NewsList />
    <!-- CTA -->
    <section class="section relative overflow-hidden bg-green-600" aria-label="Enrollment call to action">
      <img src="/_sitc/images/facilities/11.webp" alt="" aria-hidden="true" class="absolute inset-0 h-full w-full object-cover opacity-[0.60] mix-blend-overlay" loading="lazy" decoding="async" />
      <div class="absolute inset-0 bg-gradient-to-b from-green-600/60 to-green-700/80"></div>
      <div class="container-page relative z-10 text-center">
        <h2 class="text-2xl font-extrabold tracking-tight text-white sm:text-3xl md:text-4xl">Ready to Start Your Future?</h2>
        <p class="mx-auto mt-4 max-w-xl text-sm leading-relaxed text-green-50 sm:text-base">
          Visit us at {{ site.address }} or send us a message. Our team is ready to help you enroll.
        </p>
        <div class="mt-8 flex flex-wrap justify-center gap-4">
          <NuxtLink to="/contact" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-white px-7 py-3 text-sm font-bold text-green-700 shadow-lg transition-all duration-300 hover:bg-green-50 active:scale-[0.98]">Inquire Now</NuxtLink>
          <a :href="site.facebookUrl" target="_blank" rel="noopener noreferrer" class="btn-outline">Visit our Facebook</a>
        </div>
      </div>
    </section>
    <BackToTop />
  </div>
</template>