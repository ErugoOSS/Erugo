import { store } from '../../store'

export const addAuthHeader = () => ({
  Authorization: `Bearer ${store.jwt}`
})