export default function (_to: any, _from: any, savedPosition: any) {
  if (savedPosition) return { ...savedPosition, behavior: 'instant' as const }
  if (_to.hash) return { el: _to.hash, behavior: 'smooth' as const, top: 80 }
  return { top: 0, behavior: 'instant' as const }
}
