<script setup>
import { ref, defineProps, onMounted, nextTick } from 'vue'
import { CircleMinus } from 'lucide-vue-next'
const props = defineProps({
  recipient: {
    type: Object,
    required: true
  }
})

const isPopoverOpen = ref(false)
const recipientRef = ref(null)
const recipientPopoverRef = ref(null)

onMounted(() => {
  setTimeout(() => {
    if (props.recipient.showPopover) {
      togglePopover()
    }
    //register click outside listener
    document.addEventListener('click', (event) => {
      if (!recipientPopoverRef.value) return
      if (!recipientPopoverRef.value.contains(event.target) && !recipientRef.value.contains(event.target)) {
        isPopoverOpen.value = false
        //is recipient email or name input empty?
        if (props.recipient.email === null || props.recipient.name === null || props.recipient.email === '' || props.recipient.name === '') {
          emit('remove', props.recipient)
        }
      }
    })
  }, 10)
})

const togglePopover = () => {
  //position the popover
  const recipient = recipientRef.value
  const recipientPopover = recipientPopoverRef.value
  const recipientRect = recipient.getBoundingClientRect()
  const recipientPopoverRect = recipientPopover.getBoundingClientRect()
  const offset = {
    top: recipientRect.top - recipientPopoverRect.height,
    left: recipientRect.left + recipientRect.width / 2
  }
  recipientPopover.style.top = `${offset.top}px`
  recipientPopover.style.left = `${offset.left}px`
  setTimeout(() => {
    isPopoverOpen.value = !isPopoverOpen.value
  }, 10)

  //find the first input and focus it
  const firstInput = recipientPopover.querySelector('input')
  if (firstInput) {
    firstInput.focus()
  }
}

const emailInput = ref(null)
const moveFocusToEmail = () => {
  if (emailInput.value) {
    emailInput.value.focus()
  }
}
const removeRecipient = () => {
  const confirm = window.confirm('Are you sure you want to remove this recipient?')
  if (confirm) {
    emit('remove', props.recipient)
  }
}

const emit = defineEmits(['remove'])
defineExpose({
  togglePopover
})
</script>
<template>
  <div class="recipient" @click="togglePopover" ref="recipientRef">
    {{ recipient.name }}
  </div>
  <teleport to="body">
    <div class="recipient-popover" :class="{ active: isPopoverOpen }" ref="recipientPopoverRef">
      <div class="recipient-popover-content">
        <div>
          <!-- Hidden decoy fields to prevent browser password save prompts -->
          <input type="text" name="prevent_autofill_1" style="display:none" tabindex="-1" autocomplete="off" />
          <input type="password" name="prevent_autofill_2" style="display:none" tabindex="-1" autocomplete="off" />
          <input type="text" v-model="recipient.name" :placeholder="$t('full_name')" @keyup.enter="moveFocusToEmail" autocomplete="off" />
          <input type="text" v-model="recipient.email" :placeholder="$t('email')" ref="emailInput" @keyup.enter="togglePopover" autocomplete="off" />
        </div>
        <div class="button-container">
          <div class="button-outside-label">
            <button class="icon-only round" @click="removeRecipient">
              <CircleMinus />
            </button>
          </div>
        </div>
      </div>
    </div>
  </teleport>
</template>

<style scoped lang="scss">
.recipient {
  position: relative;
  display: block;
  background: var(--primary-button-background-color);
  font-size: 12px;
  color: var(--primary-button-text-color);
  font-weight: 400;
  padding: 5px;
  border-radius: 5px;
  cursor: pointer;
  &:hover {
    background: var(--primary-button-background-color-hover);
  }
  .email {
    display: block;
    color: var(--primary-button-text-color);
    font-weight: 600;
  }
  .name {
    display: block;
    font-size: 0.5rem;
    color: var(--primary-button-text-color);
  }
}
.recipient-popover {
  position: absolute;

  background: var(--panel-background-color);
  border-radius: 5px;
  padding: 5px 5px;
  z-index: 1000;
  opacity: 0;
  transition: all 0.5s ease-in-out;
  pointer-events: none;
  filter: blur(10px);
  box-shadow: 0 3px 4px rgba(0, 0, 0, 0.1);
  transform: skewX(-2deg) translate(-50%) translateY(10px);
  &.active {
    opacity: 1;
    pointer-events: auto;
    filter: blur(0px);
    transform: translate(-50%) translateY(-10px);
  }
  //little triangle at the bottom
  &::after {
    content: '';
    position: absolute;
    bottom: -10px;
    left: calc(50% - 10px);
    width: 20px;
    height: 10px;
    background: var(--panel-background-color);
    clip-path: polygon(0% 0%, 100% 0%, 50% 100%);
  }

  .recipient-popover-content {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 5px;

    .button-container {
      position: absolute;
      right: 10px;
      top: 18px;
      svg {
        width: 13px;
        height: 13px;
        margin-top: -1px;

      }
      .button-outside-label-text {
        margin-left: -7px;
      }
    }
  }

  input {
    width: 200px;
    height: 30px;
    border-radius: 0px;
    border: none;
    padding: 5px;
    margin-bottom: 0px;
    display: block;
    background: var(--input-background-color);
    color: var(--input-text-color);
    font-size: 12px;
    font-weight: 400;
    padding: 5px;
    border-radius: 5px 5px 0 0;
    &:first-child {
      margin-bottom: 1px;
    }
    &:last-child {
      border-radius: 0 0 5px 5px;
    }
    &:focus {
      outline: none;
    }
  }
}
</style>
