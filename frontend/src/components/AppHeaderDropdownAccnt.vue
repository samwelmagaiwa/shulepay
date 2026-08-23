<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { getRoleLabel, getRoleIcon } from '@/utils/roles'

const { t } = useI18n()

const auth   = useAuthStore()
const router = useRouter()

const initials = computed(() => {
  const name = auth.user?.name || ''
  return name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2)
})

const roleDisplay = computed(() => {
  const role = auth.user?.role
  if (!role) return ''
  return `${getRoleIcon(role)} ${getRoleLabel(role)}`
})

async function logout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <CDropdown placement="bottom-end" variant="nav-item">
    <CDropdownToggle class="py-0 pe-0" :caret="false">
      <div class="avatar-circle">{{ initials }}</div>
    </CDropdownToggle>
    <CDropdownMenu class="pt-0 dropdown-menu-custom">
      <CDropdownHeader component="h6" class="dropdown-header-custom">
        <div class="fw-bold">{{ auth.user?.name }}</div>
        <div class="text-muted small">{{ roleDisplay }}</div>
      </CDropdownHeader>

      <CDropdownDivider />

      <CDropdownItem @click="router.push('/profile')" class="dropdown-item-custom" style="cursor:pointer">
        🔐 {{ t('nav.changePassword') }}
      </CDropdownItem>

      <CDropdownDivider />

      <CDropdownItem @click="logout" class="dropdown-item-custom text-danger" style="cursor:pointer">
        <CIcon icon="cil-account-logout" class="me-2" /> Toka Mfumoni
      </CDropdownItem>
    </CDropdownMenu>
  </CDropdown>
</template>

<style scoped>
.avatar-circle {
  width: 36px; height: 36px; border-radius: 50%;
  background: #007f3e; color: white;
  font-size: 0.8rem; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  border: 2px solid rgba(255,255,255,0.8);
  cursor: pointer;
}
.dropdown-menu-custom {
  border: none; border-radius: 12px; min-width: 200px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.12); overflow: hidden;
}
.dropdown-header-custom {
  padding: 12px 16px;
  background: linear-gradient(to right, #f8f9fa, #e9ecef);
}
.dropdown-item-custom {
  padding: 10px 16px; font-weight: 500;
  display: flex; align-items: center;
  transition: background 0.15s;
}
.dropdown-item-custom:hover { background: #f8f9fa; }
</style>
