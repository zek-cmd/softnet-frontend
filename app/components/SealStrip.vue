<script setup>
const seals = [
  { img: '/_sitc/images/deped-seal.webp', alt: 'Department of Education seal' },
  { img: '/_sitc/images/deped-bataan.webp', alt: 'DepEd Bataan' },
  { img: '/_sitc/images/bagongpilipinas.webp', alt: 'Bagong Pilipinas' },
  { img: '/_sitc/images/tesda.webp', alt: 'TESDA logo' },
  { img: '/_sitc/images/tesda2.webp', alt: 'TESDA certification' },
  
  
]
</script>

<template>
  <div class="relative overflow-hidden border-y border-gray-100 bg-gradient-to-b from-green-50/80 via-white to-green-50/80">
    <div class="pointer-events-none absolute inset-0 opacity-40" style="background-image: radial-gradient(circle, #22c55e 1px, transparent 1px); background-size: 24px 24px;"></div>
    <div
      class="container-page relative z-10 flex flex-wrap items-center justify-center gap-x-10 gap-y-5 py-6"
      aria-label="Accreditations"
    >
      <img
        v-for="seal in seals"
        :key="seal.img"
        :src="seal.img"
        :alt="seal.alt"
        class="h-16 w-auto object-contain opacity-100 sm:h-20 lg:h-24"
        loading="lazy"
        decoding="async"
      />
    </div>
  </div>
</template>
