<script setup>
const quickLinks = [
  { to: '/', label: 'Home' },
  { to: '/about', label: 'About Us' },
  { to: '/news', label: 'News', external: true },
  { to: '/contact', label: 'Contact' },
]

const year = new Date().getFullYear()
</script>

<template>
  <footer class="bg-gray-900 text-gray-300" aria-label="Footer">
    <div class="container-page grid gap-10 py-14 sm:grid-cols-2 lg:grid-cols-4">
      <div>
        <div class="flex items-center gap-3">
          <img
            src="/_sitc/images/logo.webp"
            alt="SOFTNET logo"
            class="h-12 w-12 rounded-full object-cover ring-2 ring-green-500/40"
            width="48"
            height="48"
          />
          <div class="leading-tight">
            <p class="text-base font-extrabold tracking-tight text-white">SOFTNET</p>
            <p class="text-[10px] font-semibold uppercase tracking-widest text-green-400">
              Information Technology Center
            </p>
          </div>
        </div>
        <p class="mt-4 text-sm leading-relaxed text-gray-400">
          {{ site.aboutText }}
        </p>
      </div>

      <nav aria-label="Footer quick links">
        <h3 class="text-sm font-bold uppercase tracking-wider text-white">Quick Links</h3>
        <ul class="mt-4 list-none space-y-2.5">
          <li v-for="link in quickLinks" :key="link.to">
            <a
              :href="link.to"
              class="text-sm text-gray-400 transition-colors hover:text-green-400"
            >
              {{ link.label }}
            </a>
          </li>
        </ul>
      </nav>

      <div>
        <h3 class="text-sm font-bold uppercase tracking-wider text-white">Contact</h3>
        <ul class="mt-4 list-none space-y-3 text-sm text-gray-400">
          <li class="flex gap-2.5">
            <svg
              class="mt-0.5 h-4 w-4 shrink-0 text-green-400"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z" />
              <circle cx="12" cy="10" r="3" />
            </svg>
            <span>{{ site.address }}</span>
          </li>
          <li class="flex gap-2.5">
            <svg
              class="mt-0.5 h-4 w-4 shrink-0 text-green-400"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z" />
            </svg>
            <span>{{ site.phones.join(' · ') }}</span>
          </li>
          <li class="flex gap-2.5">
            <svg
              class="mt-0.5 h-4 w-4 shrink-0 text-green-400"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <rect width="20" height="16" x="2" y="4" rx="2" />
              <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
            </svg>
            <a :href="`mailto:${site.email}`" class="transition-colors hover:text-green-400">
              {{ site.email }}
            </a>
          </li>
          <li class="flex gap-2.5">
            <svg
              class="mt-0.5 h-4 w-4 shrink-0 text-green-400"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <circle cx="12" cy="12" r="10" />
              <path d="M12 6v6l4 2" />
            </svg>
            <span>{{ site.officeHours }}</span>
          </li>
        </ul>
      </div>
    </div>

    <div class="border-t border-white/10">
      <div
        class="container-page flex flex-col items-center justify-between gap-3 py-5 text-xs text-gray-500 sm:flex-row"
      >
        <p>© {{ year }} {{ site.name }}. All rights reserved.</p>
        <p class="font-semibold text-green-400">#WhereFunAndLearningConnect</p>
      </div>
    </div>
  </footer>
</template>
