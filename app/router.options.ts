// Replaces src/router.js scrollBehavior
//
// Page changes must land instantly, so every navigation returns
// `behavior: 'instant'`.
//
// It is said explicitly, and not left to a bare `{ top: 0 }`, because `auto`
// (the browser default) means "whatever CSS says" — so a future
// `scroll-behavior: smooth` anywhere above <html> would quietly bring the
// gliding back. `instant` means instant, always. The page root in
// app/assets/css/main.css no longer sets `scroll-behavior` at all.
//
// A hash link (/#facilities) is a different gesture — it moves down the page
// the visitor is already on — so it keeps its eased scroll.
export default function (_to: any, _from: any, savedPosition: any) {
  if (savedPosition) return { ...savedPosition, behavior: 'instant' as const }
  if (_to.hash) return { el: _to.hash, behavior: 'smooth' as const, top: 80 }
  return { top: 0, behavior: 'instant' as const }
}
