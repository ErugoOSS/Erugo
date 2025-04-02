<script setup>
import { ref, onMounted } from 'vue'
import { getUsers, createUser, deleteUser, updateUser } from '../../api'
import { UserPen, Trash, UserPlus, CircleX, UserRoundCheck } from 'lucide-vue-next'
import { store } from '../../store'
import { useToast } from 'vue-toastification'
import { niceDate } from '../../utils'

import { useTranslate } from '@tolgee/vue'

const { t } = useTranslate()

const toast = useToast()
const users = ref([])
const errors = ref({})

const newUser = ref({})
const editUser = ref({})

onMounted(async () => {
  loadUsers()
  newUser.value = getEmptyUser()
})

const loadUsers = () => {
  getUsers().then((data) => {
    users.value = data.users
  })
}

const handleDeleteUserClick = (id) => {
  if (id === store.userId) {
    toast.error(t.value('settings.users.cannot_delete_yourself'))
    return
  }


  if (confirm(t.value('confirm_delete_user'))) {
    deleteUser(id).then(
      (data) => {
        loadUsers()
        toast.success(t.value('settings.users.user_deleted_successfully'))
      },
      (error) => {
        toast.error(t.value('settings.users.failed_to_delete_user'))
      }
    )
  }
}

const handleEditUserClick = (user) => {
  editUser.value = user
  editUser.value.admin = user.admin == 1
  editUser.value.must_change_password = user.must_change_password == 1
  editUserFormActive.value = true
}

const addUser = () => {
  newUserFormActive.value = true
}

//expose addUser to parent
defineExpose({
  addUser
})

const newUserFormActive = ref(false)

const newUserFormClickOutside = (event) => {
  if (!event.target.closest('.user-form')) {
    newUserFormActive.value = false
  }
}

const editUserFormActive = ref(false)

const editUserFormClickOutside = (event) => {
  if (!event.target.closest('.user-form')) {
    editUserFormActive.value = false
  }
}

const saveUser = () => {
  errors.value = {}

  if (newUserFormActive.value) {
    createUser(newUser.value).then(
      (data) => {
        loadUsers()
        newUserFormActive.value = false
        newUser.value = getEmptyUser()
        toast.success(t.value('settings.users.user_created_successfully'))
      },
      (error) => {
        errors.value = error.data.errors
        toast.error(t.value('settings.users.failed_to_create_user'))
      }
    )
  } else if (editUserFormActive.value) {
    updateUser(editUser.value).then(
      (data) => {
        loadUsers()
        editUserFormActive.value = false
        editUser.value = getEmptyUser()
        toast.success(t.value('settings.users.user_updated_successfully'))
      },
      (error) => {
        errors.value = error.data.errors
        toast.error(t.value('settings.users.failed_to_update_user'))
      }
    )
  }
}

const getEmptyUser = () => {
  return {
    email: '',
    password: '',
    password_confirmation: '',
    name: '',
    admin: false,
    must_change_password: false
  }
}
</script>

<template>
  <div>
    <table>
      <thead>
        <tr>
          <th>{{ $t('settings.users.id') }}</th>
          <th>{{ $t('settings.users.email') }}</th>
          <th>{{ $t('settings.users.name') }}</th>
          <th>{{ $t('settings.users.account') }}</th>
          <th>{{ $t('settings.users.created') }}</th>
          <th>{{ $t('settings.users.actions') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="user in users" :key="user.id">
          <td width="1" style="white-space: nowrap">{{ user.id }}</td>
          <td>
            {{ user.email }}
            <div class="tags" v-if="user.id === store.userId || user.admin || user.must_change_password">
              <span v-if="user.id === store.userId" class="you-tag">{{ $t('settings.users.you') }}</span>
              <span v-if="user.admin" class="admin-tag">{{ $t('settings.users.admin') }}</span>
              <span v-if="user.must_change_password" class="must-change-password-tag">
                {{ $t('settings.users.must_change_password') }}
              </span>
            </div>
          </td>
          <td>{{ user.name }}</td>
          <td>
            <template v-if="user.admin">
              {{ $t('settings.users.admin') }}
            </template>
            <template v-else>
              {{ $t('settings.users.user') }}
            </template>
          </td>
          <td>{{ niceDate(user.created_at) }}</td>
          <td width="1" style="white-space: nowrap">
            <button :disabled="user.id === store.userId" @click="handleEditUserClick(user)">
              <UserPen />
              {{ $t('settings.users.edit') }}
            </button>
            <button class="secondary" :disabled="user.id === store.userId" @click="handleDeleteUserClick(user.id)">
              <Trash />
              {{ $t('settings.users.delete') }}
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="user-form-overlay" :class="{ active: newUserFormActive }" @click="newUserFormClickOutside">
    <div class="user-form">
      <h2>
        <UserPlus />
        {{ $t('settings.users.add') }}
      </h2>
      <p v-html="$t('settings.users.add_user_description')"></p>
      <div class="input-container">
        <label for="new_user_email">{{ $t('settings.users.email') }}</label>
        <input
          type="email"
          v-model="newUser.email"
          id="new_user_email"
          :placeholder="$t('settings.users.email')"
          required
          :class="{ error: errors.email }"
        />
        <div class="error-message" v-if="errors.email">
          {{ errors.email[0] }}
        </div>
      </div>
      <div class="input-container">
        <label for="new_user_name">{{ $t('settings.users.name') }}</label>
        <input
          type="text"
          v-model="newUser.name"
          id="new_user_name"
          :placeholder="$t('settings.users.name')"
          required
          :class="{ error: errors.name }"
        />
        <div class="error-message" v-if="errors.name">
          {{ errors.name[0] }}
        </div>
      </div>

      <div class="checkbox-container">
        <input type="checkbox" v-model="newUser.admin" id="admin" />
        <label for="admin">{{ $t('settings.users.admin') }}</label>
        <p class="help-text">
          {{ $t('settings.users.admin_help_text') }}
          <br />
          {{ $t('settings.users.admin_help_text_2') }}
        </p>
      </div>

      <div class="button-bar">
        <button @click="saveUser">
          <UserPlus />
          {{ $t('settings.users.add') }}
        </button>
        <button class="secondary close-button" @click="newUserFormActive = false">
          <CircleX />
          {{ $t('settings.close') }}
        </button>
      </div>
    </div>
  </div>

  <div class="user-form-overlay" :class="{ active: editUserFormActive }" @click="editUserFormClickOutside">
    <div class="user-form">
      <h2>
        <UserPlus />
        {{ $t('settings.title.users.edit', { name: editUser.name }) }}
      </h2>
      <div class="input-container">
        <label for="edit_user_email">{{ $t('settings.users.email') }}</label>
        <input
          type="email"
          v-model="editUser.email"
          id="edit_user_email"
          :placeholder="$t('settings.users.email')"
          required
          :class="{ error: errors.email }"
        />
        <div class="error-message" v-if="errors.email">
          {{ errors.email }}
        </div>
      </div>
      <div class="input-container">
        <label for="edit_user_name">{{ $t('settings.users.name') }}</label>
        <input
          type="text"
          v-model="editUser.name"
          id="edit_user_name"
          :placeholder="$t('settings.users.name')"
          required
          :class="{ error: errors.name }"
        />
        <div class="error-message" v-if="errors.name">
          {{ errors.name }}
        </div>
      </div>

      <div class="checkbox-container">
        <input type="checkbox" v-model="editUser.admin" id="edit_user_admin" />
        <label for="edit_user_admin">{{ $t('settings.users.admin') }}</label>
        <p class="help-text">
          {{ $t('settings.users.admin_help_text') }}
          <br />
          {{ $t('settings.users.admin_help_text_2') }}
        </p>
      </div>

      <div class="button-bar">
        <button @click="saveUser">
          <UserRoundCheck />
          {{ $t('settings.users.save_changes') }}
        </button>
        <button class="secondary close-button" @click="editUserFormActive = false">
          <CircleX />
          {{ $t('settings.close') }}
        </button>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.tags {
  display: flex;
  gap: 5px;
  margin-top: 5px;
}
.you-tag {
  display: inline-block;
  background: var(--primary-button-background-color);
  color: var(--primary-button-text-color);
  font-size: 12px;
  padding: 2px 5px;
  border-radius: 5px;
  transform: translateY(-1px);
}

.admin-tag {
  display: inline-block;
  background: var(--secondary-button-background-color);
  color: var(--secondary-button-text-color);
  font-size: 12px;
  padding: 2px 5px;
  border-radius: 5px;
  transform: translateY(-1px);
}

.user-form-overlay {
  border-radius: 10px 10px 0 0;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: var(--overlay-background-color);
  backdrop-filter: blur(10px);
  z-index: 230;
  opacity: 0;
  pointer-events: none;
  transition: all 0.3s ease;

  h2 {
    margin-bottom: 10px;
    font-size: 24px;
    color: var(--panel-text-color);
    display: flex;
    align-items: center;
    justify-content: center;

    svg {
      width: 24px;
      height: 24px;
      margin-right: 10px;
    }
  }
  .user-form {
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translate(-50%, 100%);
    width: min(500px, 100vw);
    background: var(--panel-background-color);
    color: var(--panel-text-color);
    padding: 20px;
    border-radius: 10px 10px 0 0;
    box-shadow: 0 0 100px 0 rgba(0, 0, 0, 0.5);
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: flex-start;
    gap: 10px;
    transition: all 0.3s ease;
    padding-bottom: 20px;
    button {
      display: block;
      width: 100%;
    }
  }

  &.active {
    opacity: 1;
    pointer-events: auto;
    .user-form {
      transform: translate(-50%, 0%);
    }
  }
}
</style>
