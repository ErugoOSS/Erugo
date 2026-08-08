// Captured at module load, before any component starts mutating document.title,
// so it always holds the server rendered application name.
const siteTitle = document.title

export const getSiteTitle = () => siteTitle

export const setPageTitle = (title) => {
  document.title = title ? `${title} - ${siteTitle}` : siteTitle
}
