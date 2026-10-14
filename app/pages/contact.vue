<script setup lang="ts">
import { ref } from 'vue'

definePageMeta({ layout: 'default', title: 'Contact Us' })

const config = useRuntimeConfig()

useSeoMeta({
  title: 'Contact Us | SOFTNET Information Technology Center, Inc.',
  description:
    'Get in touch with SOFTNET Information Technology Center in Mariveles, Bataan. Visit us, call us, or send a message about enrollment.',
  ogTitle: 'Contact Us | SOFTNET Information Technology Center, Inc.',
  ogDescription:
    'Questions about enrollment? Contact SOFTNET Information Technology Center in Bataan today.',
  ogImage: '/_sitc/images/cover.webp',
  ogType: 'website',
})

const form = ref({ name: '', email: '', phone: '', message: '' })
const status = ref('idle')

async function submitForm() {
  if (status.value === 'sending') return
  status.value = 'sending'
  try {
    const response = await fetch('https://api.web3forms.com/submit', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        access_key: config.public.web3formsKey,
        subject: `Inquiry from ${form.value.name || 'a visitor'}`,
        from_name: form.value.name,
        ...form.value,
      }),
    })
    const data = await response.json()
    if (data.success) {
      status.value = 'success'
      form.value = { name: '', email: '', phone: '', message: '' }
    } else {
      status.value = 'error'
    }
  } catch {
    status.value = 'error'
  }
}

const contactCards = [
  { label: 'Visit Us', value: site.address, icon: 'M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z M12 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z' },
  { label: 'Call Us', value: site.phones.join('  ·  '), icon: 'M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z' },
  { label: 'Email Us', value: site.email, icon: 'M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z M22 7l-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7' },
  { label: 'Office Hours', value: site.officeHours, icon: 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z M12 6v6l4 2' },
]
</script>

<template>
  <div>
    <!-- Page hero -->
    <section class="band relative overflow-hidden bg-gray-900 pt-32 pb-16" aria-label="Contact SOFTNET">
      <div class="absolute inset-0 opacity-[0.06]" style="background-image: radial-gradient(circle, white 1px, transparent 1px); background-size: 24px 24px;"></div>
      <div class="absolute -right-4 -top-4 h-48 w-48 rotate-12 rounded-full bg-white/5"></div>
      <div class="absolute -bottom-8 -left-8 h-64 w-64 -rotate-12 rounded-full bg-white/5"></div>
      <div class="absolute right-8 top-1/2 -translate-y-1/2 select-none text-[10rem] font-black leading-none text-white/[0.04] lg:text-[14rem]">CONTACT</div>
      <div class="container-page relative z-10">
        <p class="text-xs font-bold uppercase tracking-widest text-green-300">Contact</p>
        <h1 class="mt-3 max-w-2xl text-3xl font-extrabold tracking-tight text-white sm:text-4xl lg:text-5xl">Get In Touch</h1>
        <p class="mt-4 max-w-2xl text-sm leading-relaxed text-green-100 sm:text-base">Have questions about enrollment, programs, or schedules? We'd love to hear from you.</p>
      </div>
    </section>
    <!-- Contact cards -->
    <section class="relative overflow-hidden bg-gradient-to-b from-green-50/80 via-white to-green-50/80 py-20 sm:py-28" aria-label="Contact information">
      <div class="pointer-events-none absolute inset-0 opacity-40" style="background-image: radial-gradient(circle, #22c55e 1px, transparent 1px); background-size: 24px 24px;"></div>
      <div class="container-page relative z-10">
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
          <div v-for="card in contactCards" :key="card.label" class="card p-6">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-green-600 text-white">
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="card.icon" /></svg>
            </div>
            <h2 class="mt-4 text-sm font-bold uppercase tracking-wider text-gray-500">{{ card.label }}</h2>
            <p class="mt-1.5 text-sm font-semibold leading-relaxed text-gray-900">{{ card.value }}</p>
          </div>
        </div>
      </div>
    </section>
    <!-- Form + map -->
    <section class="relative overflow-hidden bg-green-50/60 py-20 sm:py-28" aria-label="Send us a message">
      <div class="pointer-events-none absolute inset-0 opacity-40" style="background-image: radial-gradient(circle, #22c55e 1px, transparent 1px); background-size: 24px 24px;"></div>
      <div class="container-page relative z-10 grid gap-8 lg:grid-cols-2">
        <div class="card p-8">
          <h2 class="text-xl font-extrabold tracking-tight text-gray-900">Send a Message</h2>
          <p class="mt-2 text-sm text-gray-600">Fill out the form and we'll get back to you as soon as possible.</p>
          <form class="mt-6 space-y-5" autocomplete="off" @submit.prevent="submitForm">
            <div>
              <label for="name" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Full Name</label>
              <input id="name" v-model="form.name" type="text" required placeholder="Juan Dela Cruz" autocomplete="off" class="min-h-12 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 text-sm text-gray-900 transition-colors placeholder:text-gray-400 focus:border-green-500 focus:bg-white focus:outline-none" />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
              <div>
                <label for="email" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Email</label>
                <input id="email" v-model="form.email" type="email" required placeholder="you@example.com" autocomplete="off" class="min-h-12 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 text-sm text-gray-900 transition-colors placeholder:text-gray-400 focus:border-green-500 focus:bg-white focus:outline-none" />
              </div>
              <div>
                <label for="phone" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Phone</label>
                <input id="phone" v-model="form.phone" type="tel" placeholder="09xx xxx xxxx" autocomplete="off" class="min-h-12 w-full rounded-xl border border-gray-200 bg-gray-50 px-4 text-sm text-gray-900 transition-colors placeholder:text-gray-400 focus:border-green-500 focus:bg-white focus:outline-none" />
              </div>
            </div>
            <div>
              <label for="message" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-gray-500">Message</label>
              <textarea id="message" v-model="form.message" rows="4" required placeholder="I'd like to ask about..." autocomplete="off" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-900 transition-colors placeholder:text-gray-400 focus:border-green-500 focus:bg-white focus:outline-none" />
            </div>
            <button type="submit" class="btn-primary w-full" :disabled="status === 'sending'">
              <span v-if="status === 'sending'">Sending...</span>
              <span v-else>Send Message</span>
            </button>
            <p v-if="status === 'success'" class="rounded-xl bg-green-50 px-4 py-3 text-sm font-semibold text-green-700 ring-1 ring-green-200">Thank you! Your message has been sent. We'll reply soon.</p>
            <p v-if="status === 'error'" class="rounded-xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 ring-1 ring-red-200">Something went wrong. Please try again or message us on Facebook.</p>
          </form>
        </div>
        <div class="flex flex-col gap-6">
          <div class="card flex-1 overflow-hidden p-0">
            <iframe title="SOFTNET location map" :src="site.mapEmbedUrl" class="h-full min-h-[320px] w-full border-0" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade" />
          </div>
          <div class="card flex items-center gap-4 p-6">
            <img src="/_sitc/images/logo.webp" alt="SOFTNET logo" class="h-14 w-14 rounded-full object-cover ring-2 ring-green-600/20" loading="lazy" decoding="async" />
            <div>
              <p class="text-sm font-extrabold text-gray-900">{{ site.name }}</p>
              <p class="mt-0.5 text-xs text-gray-500">{{ site.address }}</p>
              <a :href="site.facebookUrl" target="_blank" rel="noopener noreferrer" class="mt-1 inline-block text-xs font-bold text-green-600 hover:text-green-700">Follow us on Facebook →</a>
            </div>
          </div>
        </div>
      </div>
    </section>
    <BackToTop />
  </div>
</template>
