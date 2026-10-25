import { EXAM_MANAGERS } from '@/utils/examRoles'

/**
 * The order an exam module is set up in, and the page where each step is done.
 * Shared by the progress row on the exam list and by the "carry on?" cards.
 */
const CALENDAR_ROLES = ['accountant', 'owner', 'superadmin', 'academic_pri', 'academic_sec']
const CLASS_KEEPERS = ['owner', 'superadmin', 'academic_pri', 'academic_sec']

export const SETUP_STEPS = ['academic_year', 'term', 'classes', 'subjects', 'class_subjects', 'teachers']

export const SETUP_TARGETS = {
  academic_year: { to: '/admin/academic-years', roles: CALENDAR_ROLES },
  term: { to: '/admin/terms', roles: CALENDAR_ROLES },
  classes: { to: '/mitihani/mipangilio?tab=classes', roles: CLASS_KEEPERS },
  subjects: { to: '/mitihani/mipangilio?tab=subjects', roles: EXAM_MANAGERS },
  class_subjects: { to: '/mitihani/mipangilio?tab=classSubjects', roles: EXAM_MANAGERS },
  teachers: { to: '/mitihani/mipangilio?tab=teachers', roles: EXAM_MANAGERS },
}

/** Where a role may do a step, or null when that role cannot open the page. */
export function targetFor(step, role) {
  const target = SETUP_TARGETS[step]
  return target && (!target.roles || target.roles.includes(role)) ? target.to : null
}
