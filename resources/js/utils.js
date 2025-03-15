
import {domData} from './domData.js'
import { useTolgee } from '@tolgee/vue'

const simpleUUID = () => {
  //this isn't cryptographically secure, but it's good enough for our purposes
  //our purposes being a simple unique string to track upload progress via SSE
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
      const r = Math.random() * 16 | 0;
      const v = c === 'x' ? r : (r & 0x3 | 0x8);
      return v.toString(16);
  });
}

const niceFileSize = size => {
  //return in most readable format
  if (size < 1024) return `${size} bytes`
  if (size < 1024 * 1024) return `${(size / 1024).toFixed(2)} KB`
  if (size < 1024 * 1024 * 1024) return `${(size / 1024 / 1024).toFixed(2)} MB`
  return `${(size / 1024 / 1024 / 1024).toFixed(2)} GB`
}

const niceFileType = type => {
  if (!type) return 'Unknown'
  if(type == 'unknown') return 'Unknown'
  let mimeType = type?.split('/')[1].split('+')[0]
  mimeType = mimeType.charAt(0).toUpperCase() + mimeType.slice(1)
  //split . and take the last part
  mimeType = mimeType.split('.').pop()
  return mimeType.toLowerCase()
}

const niceExpirationDate = date => {
  //take date and return human readable
  return new Date(date).toLocaleDateString()
}

const timeUntilExpiration = date => {
  //take date and return time until expiration in human readable format
  const now = new Date()
  const expiration = new Date(date)
  const diffTime = expiration.getTime() - now.getTime()
  const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24))
  const diffHours = Math.floor((diffTime % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))
  const diffMinutes = Math.floor((diffTime % (1000 * 60 * 60)) / (1000 * 60))
  return {
    days: diffDays,
    hours: diffHours,
    minutes: diffMinutes
  }
}

const getApiUrl = () => {
  return window.location.origin 
}

const niceFileName = name => {
  const nameWithoutExt = name.split('.').slice(0, -1).join('.')
  const ext = name.split('.').pop()
  return nameWithoutExt.length > 10 ? nameWithoutExt.slice(0, 15) + '...' + ext : name
}

const niceString = str => {
  return str.length > 20 ? str.slice(0, 20) + '...' : str
}

const niceDate = date => {
  return new Date(date).toLocaleDateString()
}

const niceNumber = number => {
  if (number < 1000) return number
  if (number < 1000000) return `${(number / 1000).toFixed(1)}k`
  if (number < 1000000000) return `${(number / 1000000).toFixed(1)}m`
  return `${(number / 1000000000).toFixed(1)}b`
}

const mapSettings = (settingsGroup) => {
  const settings = []
  const keys = Object.keys(settingsGroup)
  for (const key of keys) {
    let setting = settingsGroup[key]
    settings[setting.key] = convertToRealType(setting.value)
  }
  return settings
}

const convertToRealType = value => {
  //boolean true
  if (value === 'true') return true
  //boolean false
  if (value === 'false') return false
  //null
  if (value === 'null') return null
  //real null
  if (value === null) return null
  //number
  if (!isNaN(value)) return parseFloat(value)
  //array
  if (value.startsWith('[') && value.endsWith(']') && value.includes(',')) return JSON.parse(value)
  //object
  if (value.startsWith('{') && value.endsWith('}') && value.includes(':')) return JSON.parse(value)

  return value
}

export { niceFileSize, niceFileType, niceExpirationDate, timeUntilExpiration, getApiUrl, simpleUUID, niceFileName, niceDate, niceString, niceNumber, mapSettings }
