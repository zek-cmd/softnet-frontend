<script setup lang="ts">
// A row of tag chips. Each chip links to the filtered news list INSIDE this
// site, so tapping a tag shows every story that carries it.
//
// The list reads the tag from the address (/news?tag=sports) and opens already
// filtered (see app/components/NewsBrowser.vue).
//
// Two shapes, two elements, on purpose. A local address (/news) becomes a
// NuxtLink, so the chip moves to the list instantly instead of reloading the
// whole site. A full web address passed by a caller stays a plain anchor, which
// is the only kind that can leave this site.
//
// The two are written out rather than picked with a dynamic component: naming
// the component in a variable renders the NAME into the page ("<NuxtLink …>")
// instead of a link, because the name is a string to the renderer, not the
// component itself.
const props = defineProps<{
  tags?: string[] | string
  base: string
}>()

const CHIP =
  'rounded-full border border-gray-200 bg-white px-2.5 py-1 text-[11px] font-bold text-gray-500 transition-colors hover:border-green-600 hover:bg-green-50 hover:text-green-700'

const tags = computed<string[]>(() => {
  const raw = props.tags

  if (Array.isArray(raw)) {
    return raw.map((tag) => String(tag).trim()).filter(Boolean)
  }

  return String(raw ?? '')
    .split(',')
    .map((tag) => tag.trim())
    .filter(Boolean)
})

const href = (tag: string) => `${props.base}?tag=${encodeURIComponent(tag)}`

const isLocal = computed(() => String(props.base ?? '').startsWith('/'))
</script>

<template>
  <div v-if="tags.length" class="flex flex-wrap gap-1.5">
    <template v-if="isLocal">
      <NuxtLink v-for="tag in tags" :key="tag" :to="href(tag)" :class="CHIP">#{{ tag }}</NuxtLink>
    </template>
    <template v-else>
      <a v-for="tag in tags" :key="tag" :href="href(tag)" :class="CHIP">#{{ tag }}</a>
    </template>
  </div>
</template>
