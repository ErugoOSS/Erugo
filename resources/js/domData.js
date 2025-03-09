export const domData = () => {
  const data = {
    version: document.body.getAttribute('data-version'),
    ...getSettings()
  }
  return data
}

export const domError = () => {
  const error = document.body.getAttribute('data-error')
  return error || ''
}

export const domSuccess = () => {
  const success = document.body.getAttribute('data-success')
  return success || ''
}

const getSettings = () => {
  const body = document.body
  if(!body) {
    return {}
  }
  const settings = JSON.parse(body.getAttribute('data-settings'))
  return settings
}