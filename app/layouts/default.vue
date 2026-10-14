<script setup lang="ts">
// The surface under the page.
//
// The news pages get their own .news-page-bg rule; every other page keeps the
// light geometric surface. The rule paints the same faint green wash and dot
// grid the Home, About and Contact pages build inside each of their sections
// (see news.css). The choice is made here, on <main>, rather than inside each
// news page, for two reasons: the surface then covers the whole screen even
// when a page is short (a page's own markup is only as tall as its content),
// and there is one place to look when the news pages should change.
//
// The address test is the same one NewsBrowser.vue uses.
const route = useRoute()

const isNewsPage = computed(() => String(route.path).startsWith('/news'))
</script>

<template>
  <div class="flex min-h-screen flex-col">
    <AppHeader />
    <main class="min-w-0 flex-1" :class="isNewsPage ? 'news-page-bg' : 'texture-bg'">
      <slot />
    </main>
    <AppFooter />
  </div>
</template>
