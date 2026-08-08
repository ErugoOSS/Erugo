<script setup>
import { ref } from 'vue'
import { addLiveshareMember, updateLiveshareMember, removeLiveshareMember } from '../../api'
import { Trash2, User, Crown, Loader2, Send } from 'lucide-vue-next'
import { useToast } from 'vue-toastification'
import { useConfirmDialog } from '../../composables/useConfirmDialog'
import InviteManager from './inviteManager.vue'

const inviteManagerRef = ref(null)

const props = defineProps({
  liveshareLongId: { type: String, required: true },
  members: { type: Array, default: () => [] },
  owner: { type: Object, default: null },
  canManage: { type: Boolean, default: false }
})

const emit = defineEmits(['membersChanged'])

const toast = useToast()
const confirmDialog = useConfirmDialog()
const showAddForm = ref(false)
const newEmail = ref('')
const newRole = ref('collaborator')
const adding = ref(false)

const handleAddMember = async () => {
  if (!newEmail.value.trim()) return

  adding.value = true
  try {
    await addLiveshareMember(props.liveshareLongId, newEmail.value.trim(), newRole.value)
    toast.success('Member added')
    newEmail.value = ''
    newRole.value = 'collaborator'
    showAddForm.value = false
    emit('membersChanged')
  } catch (error) {
    toast.error(error.message || 'Failed to add member')
  }
  adding.value = false
}

const handleRoleChange = async (member, newRole) => {
  try {
    await updateLiveshareMember(props.liveshareLongId, member.id, newRole)
    toast.success('Role updated')
    emit('membersChanged')
  } catch (error) {
    toast.error(error.message || 'Failed to update role')
  }
}

const handleRemoveMember = async (member) => {
  const confirmed = await confirmDialog.show({
    title: 'Remove Member',
    message: `Remove ${member.user?.name || 'this member'} from the liveshare?`,
    okText: 'Remove',
    cancelText: 'Cancel'
  })
  if (!confirmed) return

  try {
    await removeLiveshareMember(props.liveshareLongId, member.id)
    toast.success('Member removed')
    emit('membersChanged')
  } catch (error) {
    toast.error(error.message || 'Failed to remove member')
  }
}

const openCreateInvite = () => {
  inviteManagerRef.value?.openCreateOverlay()
}

defineExpose({ openCreateInvite })
</script>

<template>
  <div class="member-manager">
    <!-- Owner -->
    <!-- <div class="member-item owner-item" v-if="owner">
      <div class="member-info">
        <Crown class="member-icon owner-icon" />
        <div class="member-details">
          <span class="member-name">{{ owner.name }}</span>
          <span class="member-email">{{ owner.email }}</span>
        </div>
      </div>
      <span class="role-badge role-owner">Owner</span>
    </div> -->

    <!-- Members -->

    <table>
      <thead class="secondary">
        <tr>
          <th colspan="99" style="text-align: center;">Members</th>
        </tr>
      </thead>
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Role</th>
          <th v-if="canManage" width="1px">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="owner">
          <td>{{ owner.name }}</td>
          <td>{{ owner.email }}</td>
          <td colspan="4" style="text-align: center">
            <span class="role-badge role-owner">Owner</span>
          </td>
        </tr>

        <tr v-for="member in members" :key="member.id">
          <td>{{ member.user?.name || 'Unknown' }}</td>
          <td>{{ member.user?.email || '' }}</td>
          <td>
            <select
              :value="member.role"
              @change="handleRoleChange(member, $event.target.value)"
              v-if="canManage"
              class="role-select"
            >
              <option value="manager">Manager</option>
              <option value="collaborator">Collaborator</option>
              <option value="viewer">Viewer</option>
            </select>
            <span v-else class="role-badge" :class="'role-' + member.role">{{ member.role }}</span>
          </td>
          <td v-if="canManage">
            <button class="clear-button icon-only" @click="handleRemoveMember(member)" title="Remove member">
              <Trash2 />
            </button>
          </td>
        </tr>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="99" style="text-align: center;">
          &nbsp;
          </td>
        </tr>
      </tfoot>
    </table>

    <div class="no-members" v-if="members.length === 0">
      <p>No members yet</p>
    </div>

    <!-- Invite section -->
    <div v-if="canManage">
    
      <InviteManager
        ref="inviteManagerRef"
        :liveshareLongId="liveshareLongId"
        @inviteAccepted="emit('membersChanged')"
      />
    </div>
  </div>
</template>

<style lang="scss" scoped>
.member-manager {
  width: 100%;
}

.member-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 0;

  h4 {
    margin: 0;
    font-size: 1rem;
    color: var(--panel-section-text-color);
  }
}

.add-member-form {
  display: flex;
  gap: 8px;
  margin-bottom: 15px;

  input {
    flex: 1;
    min-width: 0;
  }

  select {
    width: auto;
    min-width: 120px;
  }

  button {
    white-space: nowrap;
  }
}

.member-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px;
  border-radius: 6px;
  margin-bottom: 4px;
  transition: background 0.15s ease;

  &:hover {
    background: var(--panel-section-background-color-alt);
  }

  &.owner-item {
    background: var(--panel-section-background-color-alt);
  }
}

.member-info {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;

  .member-icon {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    opacity: 0.5;

    &.owner-icon {
      opacity: 0.8;
      color: var(--link-color);
    }
  }

  .member-details {
    display: flex;
    flex-direction: column;
    min-width: 0;

    .member-name {
      font-size: 0.85rem;
      font-weight: 500;
      color: var(--panel-section-text-color);
    }

    .member-email {
      font-size: 0.7rem;
      color: var(--panel-section-text-color);
      opacity: 0.5;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
  }
}

.member-actions {
  display: flex;
  align-items: center;
  gap: 5px;
}

.role-select {
  padding: 4px 8px;
  font-size: 0.75rem;
  border-radius: 4px;
  border: 1px solid var(--input-border-color);
  background: var(--panel-section-background-color);
  color: var(--panel-section-text-color);
  cursor: pointer;
}

.role-badge {
  display: inline-block;
  padding: 2px 10px;
  border-radius: 12px;
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: capitalize;
}

.role-owner {
  background: var(--primary-button-background-color);
  color: var(--primary-button-text-color);
}

.role-manager,
.role-collaborator,
.role-viewer {
  background: var(--panel-section-background-color);
  color: var(--panel-section-text-color);
}

.no-members {
  padding: 20px;
  text-align: center;
  color: var(--panel-section-text-color);
  opacity: 0.5;
  font-size: 0.85rem;
}

.invite-divider {
  margin-top: 20px;
  padding-top: 0px;

  hr {
    border: none;
    border-top: 1px solid var(--input-border-color);
    margin-bottom: 15px;
  }

  h4 {
    font-size: 1rem;
    font-weight: 500;
    color: var(--panel-section-text-color);
    margin: 0 0 4px 0;
    display: flex;
    align-items: center;
    gap: 8px;

    svg {
      width: 16px;
      height: 16px;
      opacity: 0.6;
    }
  }
}

.spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}
</style>
