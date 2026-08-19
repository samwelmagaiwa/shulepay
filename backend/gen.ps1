php artisan make:model Subject -m
php artisan make:controller Api/SubjectController --api --model=Subject
php artisan make:model ClassSubject -m
php artisan make:model Exam -m
php artisan make:controller Api/ExamController --api --model=Exam
php artisan make:model ExamResult -m
php artisan make:model Attendance -m
php artisan make:controller Api/AttendanceController --api --model=Attendance
