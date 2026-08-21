<script setup>
import { RouterLink } from 'vue-router'
import { AppSidebarNav } from '@/components/AppSidebarNav.js'
import { useSidebarStore } from '@/stores/sidebar.js'
import { useBrandingStore } from '@/stores/branding'

const sidebar  = useSidebarStore()
const branding = useBrandingStore()
</script>

<template>
  <CSidebar
    class="sidebar-custom border-end"
    position="fixed"
    :unfoldable="sidebar.unfoldable"
    :visible="sidebar.visible"
    :narrow="sidebar.narrow"
    @visible-change="(value) => sidebar.toggleVisible(value)"
  >
    <CSidebarHeader class="border-bottom">
      <RouterLink custom to="/dashibodi" v-slot="{ href, navigate }">
        <CSidebarBrand v-bind="$attrs" as="a" :href="href" @click="navigate"
          class="d-flex align-items-center justify-content-center gap-2 py-2 text-decoration-none"
          style="min-height:64px;">
          <!-- Logo — full sidebar shows image + text; narrow shows image only -->
          <img
            v-if="branding.logoUrl"
            :src="branding.logoUrl"
            alt="Logo"
            class="sidebar-brand-logo"
            @error="branding.logoUrl = null"
          />
          <svg v-else viewBox="0 0 40 40" width="40" height="40" class="sidebar-brand-logo flex-shrink-0">
            <circle cx="20" cy="20" r="19" fill="rgba(255,255,255,0.15)"/>
            <rect x="8"  y="13" width="11" height="16" rx="2" fill="white" opacity=".9"/>
            <rect x="21" y="13" width="11" height="16" rx="2" fill="white" opacity=".9"/>
            <rect x="18" y="11" width="4"  height="20" rx="1" fill="#fcd116"/>
            <circle cx="29" cy="11" r="5" fill="#fcd116"/>
            <text x="29" y="14.5" text-anchor="middle" font-size="6" font-weight="bold" fill="#007f3e">$</text>
          </svg>
          <div class="sidebar-brand-full d-flex flex-column lh-1 overflow-hidden">
            <span class="fw-bold text-white" style="font-size:.95rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
              {{ branding.appName }}
            </span>
            <span style="font-size:.6rem; font-weight:700; letter-spacing:1px; text-transform:uppercase; color:rgba(255,255,255,.55);">
              {{ branding.appTagline }}
            </span>
          </div>
        </CSidebarBrand>
      </RouterLink>
      <CCloseButton class="d-lg-none" dark @click="sidebar.toggleVisible()" />
    </CSidebarHeader>
    <AppSidebarNav />
    <CSidebarFooter class="border-top d-none d-lg-flex">
      <CSidebarToggler @click="sidebar.toggleNarrow()" />
    </CSidebarFooter>
  </CSidebar>
</template>

<style scoped>
.sidebar-brand-logo {
  width: 40px;
  height: 40px;
  object-fit: contain;
  border-radius: 50%;
  flex-shrink: 0;
}

.sidebar-custom {
  --cui-sidebar-narrow-width: 104px;
  background: linear-gradient(180deg, #007f3e 0%, #003082 100%);
  color: #ffffff;
  transition: width 0.3s ease-in-out;
  border-right: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar {
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
  border-radius: 0 10px 10px 0;
}

.nav-title {
  color: #ffffff;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1.5px;
  padding: 1.2rem 1rem 0.6rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.15);
  opacity: 0.8;
}

.nav-link {
  transition: all 0.3s ease;
  color: #e4e7ed;
  padding: 0.85rem 1.2rem;
  border-radius: 12px;
  margin: 0.3rem 0.6rem;
  display: flex;
  align-items: center;
  font-size: 1rem;
}

.nav-link:hover {
  background: linear-gradient(90deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
  color: #ffffff;
  transform: translateX(8px) scale(1.02);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.nav-link.active {
  background: linear-gradient(90deg, #ffffff, #f0f0f0);
  color: #224abe;
  font-weight: 700;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  border-left: none;
  padding-left: 1.2rem;
}

.nav-icon {
  transition: transform 0.3s ease, color 0.3s ease;
  margin-right: 0.85rem;
  font-size: 1.2rem;
}

.nav-link:hover .nav-icon {
  transform: scale(1.3) rotate(5deg);
  color: #ffd700;
}

.sidebar-narrow .nav-link {
  justify-content: center;
  padding: 0.85rem;
}

.sidebar-narrow .nav-icon {
  margin-right: 0;
  font-size: 1.4rem;
}

.badge {
  transition: transform 0.3s ease, background-color 0.3s ease;
  font-weight: 600;
}

.nav-link:hover .badge {
  transform: scale(1.15);
  background-color: #ffd700;
  color: #224abe;
}
</style>
