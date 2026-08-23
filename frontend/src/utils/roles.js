/**
 * Central role registry — single source of truth for all role metadata.
 * Import getRoleLabel / getRoleIcon / ROLES anywhere that needs role display.
 */

export const ROLES = [
  { value: 'superadmin',       label: 'Super Admin',                  swahili: 'Super Admin',                    icon: '⚙️',  levels: ['primary', 'secondary'] },
  { value: 'owner',            label: 'Owner',                        swahili: 'Mmiliki',                        icon: '👑',  levels: ['primary', 'secondary'] },
  { value: 'accountant',       label: 'Accountant',                   swahili: 'Muhasibu',                       icon: '💼',  levels: ['primary', 'secondary'] },
  { value: 'headmaster',       label: 'Headmaster',                   swahili: 'Mkurugenzi',                     icon: '🏫',  levels: ['secondary'] },
  { value: 'head_teacher',     label: 'Head Teacher',                 swahili: 'Mwalimu Mkuu',                   icon: '🏅',  levels: ['primary'] },
  { value: 'academic_pri',     label: 'Academic Coordinator (Pri)',   swahili: 'Msimamizi Masomo (Msingi)',       icon: '🎓',  levels: ['primary'] },
  { value: 'academic_sec',     label: 'Academic Coordinator (Sec)',   swahili: 'Msimamizi Masomo (Sekondari)',    icon: '🎓',  levels: ['secondary'] },
  { value: 'academic_teacher', label: 'Academic Teacher',             swahili: 'Mwalimu Masomo',                 icon: '🎓',  levels: ['primary', 'secondary'] },
  { value: 'teacher_pri',      label: 'Class Teacher (Primary)',      swahili: 'Mwalimu wa Darasa (Msingi)',      icon: '📚',  levels: ['primary'] },
  { value: 'teacher_sec',      label: 'Class Teacher (Secondary)',    swahili: 'Mwalimu wa Darasa (Sekondari)',   icon: '📚',  levels: ['secondary'] },
  { value: 'teacher',          label: 'Class Teacher',                swahili: 'Mwalimu wa Darasa',              icon: '📚',  levels: ['primary', 'secondary'] },
  { value: 'parent',           label: 'Parent / Guardian',            swahili: 'Mzazi / Mlezi',                  icon: '👨‍👩‍👧', levels: ['primary', 'secondary'] },
]

const _byValue = Object.fromEntries(ROLES.map(r => [r.value, r]))

export function getRole(value) {
  return _byValue[value] ?? { value, label: value, swahili: value, icon: '👤', levels: [] }
}

export function getRoleLabel(value) {
  return getRole(value).label
}

export function getRoleSwahili(value) {
  return getRole(value).swahili
}

export function getRoleIcon(value) {
  return getRole(value).icon
}

export function getRoleBadgeColor(value) {
  const colors = {
    superadmin:       'dark',
    owner:            'success',
    accountant:       'info',
    headmaster:       'danger',
    head_teacher:     'dark',
    academic_pri:     'warning',
    academic_sec:     'warning',
    academic_teacher: 'warning',
    teacher_pri:      'primary',
    teacher_sec:      'primary',
    teacher:          'primary',
    parent:           'secondary',
  }
  return colors[value] ?? 'secondary'
}

/** Roles available for a given school level (for Add Staff form) */
export function getRolesForLevel(level) {
  return ROLES.filter(r => r.levels.includes(level) && r.value !== 'superadmin' && r.value !== 'parent' && r.value !== 'teacher')
}
