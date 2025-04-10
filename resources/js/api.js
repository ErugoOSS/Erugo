import { uploadFilesInChunks } from './api/uploadFilesInChunks'

import { sendReverseShareInvite, acceptReverseShareInvite } from './api/reverseShares'

import {
  getUsers,
  createUser,
  updateUser,
  updateMyProfile,
  deleteUser,
  getMyProfile,
  createFirstUser
} from './api/users'
import {
  getSettingsByGroup,
  getSettingById,
  saveSettingsById,
  saveLogo,
  installCustomTheme,
  getBackgroundImages,
  saveBackgroundImage,
  deleteBackgroundImage
} from './api/settings'

import {
  createShare,
  getMyShares,
  expireShare,
  extendShare,
  setDownloadLimit,
  pruneExpiredShares,
  getShare
} from './api/shares'

import { getThemes, saveTheme, deleteTheme, setActiveTheme, getActiveTheme } from './api/themes'

import {
  getAvailableAuthProviders,
  getAuthProviders,
  getCallbackUrl,
  bulkUpdateAuthProviders,
  deleteAuthProvider,
  getAvailableProviderTypes,
  unlinkProvider
} from './api/authProviders'

import { getEmailTemplates, updateEmailTemplates } from './api/emailTemplates'

import { getHealth } from './api/misc'

import { resetPassword, forgotPassword, login, refresh, logout, debouncedPasswordChangeRequired } from './api/auth'

export {
  uploadFilesInChunks,
  resetPassword,
  forgotPassword,
  login,
  refresh,
  logout,
  sendReverseShareInvite,
  acceptReverseShareInvite,
  getUsers,
  createUser,
  updateUser,
  updateMyProfile,
  deleteUser,
  getSettingsByGroup,
  getSettingById,
  saveSettingsById,
  saveLogo,
  installCustomTheme,
  getBackgroundImages,
  saveBackgroundImage,
  deleteBackgroundImage,
  createShare,
  getMyShares,
  expireShare,
  extendShare,
  setDownloadLimit,
  pruneExpiredShares,
  getShare,
  getThemes,
  saveTheme,
  deleteTheme,
  setActiveTheme,
  getActiveTheme,
  getAvailableAuthProviders,
  getAuthProviders,
  getCallbackUrl,
  bulkUpdateAuthProviders,
  deleteAuthProvider,
  getAvailableProviderTypes,
  unlinkProvider,
  getHealth,
  getMyProfile,
  createFirstUser,
  getEmailTemplates,
  updateEmailTemplates,
  debouncedPasswordChangeRequired
}
