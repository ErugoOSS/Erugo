import { getApiUrl } from '../utils'

const apiUrl = getApiUrl()

const getHealth = async () => {
  const response = await fetch(`${apiUrl}/api/health`)
  const data = await response.json()
  if (!response.ok) {
    throw new Error(data.message)
  }
  return data.data
}

export { getHealth }