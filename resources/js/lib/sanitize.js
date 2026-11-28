import DOMPurify from 'dompurify'

// Auth provider icons arrive from the API as SVG markup and are rendered with
// v-html. Strip scripts, event handlers and anything that isn't SVG.
export const sanitizeSvg = (svg) => {
  if (!svg) return ''
  return DOMPurify.sanitize(svg, { USE_PROFILES: { svg: true, svgFilters: true } })
}
