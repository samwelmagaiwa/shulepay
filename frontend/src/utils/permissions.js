import { i18n } from '@/i18n'

/**
 * Display names for the permission matrix the backend sends
 * (RolePermissionController::allPermissions): module headings such as
 * 'Fee Structures', and permissions such as 'invoices.edit_restricted'.
 *
 * Both used to be shown raw. They are looked up here, so they follow the
 * language switcher; anything not listed (a permission added later) falls back
 * to a readable form of its own name instead of showing a missing-key marker.
 * Read inside a template, these are reactive.
 */

// Modules that already have a navigation label reuse it, so a module is called
// the same thing in the menu and in the permission list.
const MODULE_KEYS = {
  Dashboard: 'nav.dashboard',
  Students: 'nav.students',
  Guardians: 'nav.guardians',
  Invoices: 'nav.invoices',
  Payments: 'nav.payments',
  Installments: 'nav.installments',
  Refunds: 'nav.refunds',
  'Fee Structures': 'nav.feeStructures',
  Discounts: 'nav.discounts',
  Clearance: 'nav.clearance',
  Expenses: 'nav.expenses',
  'Petty Cash': 'nav.pettyCash',
  Payroll: 'nav.payroll',
  Employees: 'nav.employees',
  Suppliers: 'nav.suppliers',
  Assets: 'nav.assets',
  Budgets: 'nav.budgets',
  Attendance: 'nav.attendance',
  Transport: 'nav.transport',
  Inventory: 'nav.inventory',
  Reports: 'nav.reports',
  SMS: 'permissionLabels.moduleSms',
  Users: 'nav.userManagement',
  Schools: 'nav.schools',
  'Audit Log': 'nav.audit',
  Access: 'permissionLabels.moduleAccess',
}

const translate = (key) => i18n.global.t(key)
const exists = (key) => i18n.global.te(key)

export function moduleLabel(name) {
  const key = MODULE_KEYS[name]
  return key && exists(key) ? translate(key) : name
}

export function permissionLabel(perm) {
  const full = String(perm)
  if (exists(`permissionLabels.${full}`)) return translate(`permissionLabels.${full}`)

  const tail = full.split('.')[1] ?? full
  const restricted = tail.endsWith('_restricted')
  const action = tail.replace('_restricted', '')

  if (restricted && exists(`permissionLabels.restrict_${action}`)) return translate(`permissionLabels.restrict_${action}`)
  if (!restricted && exists(`permissionLabels.action_${tail}`)) return translate(`permissionLabels.action_${tail}`)

  return restricted ? `🔒 restrict ${action}ing` : tail.replace(/_/g, ' ')
}
