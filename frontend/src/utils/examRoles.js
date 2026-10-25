/**
 * Who may do what in the exam module. Mirrors App\Enums\UserRole on the
 * backend (examStaff / examManagers / examPublishers); the API is what actually
 * enforces this, the UI only hides controls that would be refused.
 */
const HEADS = ['head_teacher', 'headmaster']
const COORDINATORS = ['academic_pri', 'academic_sec']
const TEACHERS = ['teacher_pri', 'teacher_sec']

/** Set exams, subjects and the grading scale up. */
export const EXAM_MANAGERS = ['owner', 'superadmin', ...HEADS, ...COORDINATORS]

/** Approve and publish results to parents. */
export const EXAM_PUBLISHERS = ['owner', 'superadmin', ...HEADS, ...COORDINATORS]

/** Enter marks and read results. */
export const EXAM_STAFF = [...EXAM_MANAGERS, ...TEACHERS]

export const canManageExams = (role) => EXAM_MANAGERS.includes(role)
export const canPublishExams = (role) => EXAM_PUBLISHERS.includes(role)
export const canEnterMarks = (role) => EXAM_STAFF.includes(role)

export const EXAM_TYPES = ['test', 'midterm', 'terminal', 'annual', 'mock']

export const STATUS_COLOR = { draft: 'secondary', open: 'warning', published: 'success' }
