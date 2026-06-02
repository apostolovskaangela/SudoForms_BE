<?php

namespace Database\Seeders;

use App\Models\Answer;
use App\Models\Course;
use App\Models\Professor;
use App\Models\Response;
use App\Models\Survey;
use App\Models\SurveyParticipation;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->command->info('Seeding Sudo Forms demo data...');

        // --- Admins (Student Success Team / Management) ---
        User::create([
            'name' => 'Student Success Admin',
            'email' => 'admin@sudoforms.test',
            'password' => 'password',
            'role' => 'admin',
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Brainster Management',
            'email' => 'manager@sudoforms.test',
            'password' => 'password',
            'role' => 'admin',
            'is_active' => true,
        ]);

        // --- Students ---
        $students = collect();

        // A known demo student for easy login.
        $students->push(User::create([
            'name' => 'Demo Student',
            'email' => 'student@sudoforms.test',
            'password' => 'password',
            'role' => 'student',
            'is_active' => true,
            'student_number' => 'BNC-2026-001',
        ]));

        $firstNames = ['Ana', 'Marko', 'Elena', 'Stefan', 'Maja', 'Nikola', 'Sara', 'Damjan', 'Ivana', 'Bojan',
            'Teodora', 'Filip', 'Marija', 'Aleksandar', 'Jana', 'Viktor', 'Sofija', 'Luka', 'Mila', 'David'];
        $lastNames = ['Petrov', 'Stojanov', 'Ilieva', 'Naumov', 'Trajkova', 'Georgiev', 'Mitreva', 'Pavlov',
            'Ristova', 'Angelov', 'Kostova', 'Dimitrov', 'Spasova', 'Jovanov', 'Nedelkova'];

        for ($i = 2; $i <= 50; $i++) {
            $name = $firstNames[($i * 7) % count($firstNames)].' '.$lastNames[($i * 3) % count($lastNames)];
            // ~90% active.
            $isActive = ($i % 10) !== 0;

            $students->push(User::create([
                'name' => $name,
                'email' => 'student'.$i.'@sudoforms.test',
                'password' => 'password',
                'role' => 'student',
                'is_active' => $isActive,
                'student_number' => sprintf('BNC-2026-%03d', $i),
            ]));
        }

        $activeStudents = $students->filter(fn (User $s) => $s->is_active)->values();
        $this->command->info('  Students: '.$students->count().' ('.$activeStudents->count().' active)');

        // Exclude the demo student from simulated history so that logging in as
        // student@sudoforms.test always has open surveys to try in the UI.
        $simPool = $activeStudents->reject(fn (User $s) => $s->email === 'student@sudoforms.test')->values();

        // --- Professors & Courses ---
        $coursesData = [
            ['Web Programming', 'WEB301', 'Prof. Marina Jovanovska', 'Senior Lecturer', 'Software Engineering', 'excellent'],
            ['Databases', 'DB210', 'Prof. Goran Stefanov', 'Lecturer', 'Software Engineering', 'good'],
            ['UI/UX Design', 'UX150', 'Prof. Ana Petrovska', 'Lecturer', 'Design', 'good'],
            ['Algorithms & Data Structures', 'ALG220', 'Prof. Dimitar Kostov', 'Senior Lecturer', 'Computer Science', 'mixed'],
            ['Operating Systems', 'OS310', 'Prof. Vlatko Ristov', 'Lecturer', 'Computer Science', 'poor'],
            ['Project Management', 'PM400', 'Prof. Sofija Naumova', 'Associate Professor', 'Management', 'excellent'],
        ];

        $courses = collect();
        $profileByCourse = [];

        foreach ($coursesData as [$courseName, $code, $profName, $title, $dept, $profile]) {
            $professor = Professor::create([
                'name' => $profName,
                'title' => $title,
                'department' => $dept,
            ]);

            $course = Course::create([
                'name' => $courseName,
                'code' => $code,
                'professor_id' => $professor->id,
                'semester' => 'Summer 2026',
            ]);

            $courses->push($course);
            $profileByCourse[$course->id] = $profile;
        }

        $admin = User::where('email', 'admin@sudoforms.test')->first();

        // --- Surveys with demo responses ---
        $courseQuestions = [
            ['type' => 'rating', 'text' => 'How clear were the lectures this week?', 'required' => true],
            ['type' => 'rating', 'text' => 'How well did the professor explain difficult concepts?', 'required' => true],
            ['type' => 'rating', 'text' => 'How engaging and interactive was the class?', 'required' => true],
            ['type' => 'single_choice', 'text' => 'How was the pace of the course this week?', 'required' => true,
                'options' => ['Too slow', 'Just right', 'Too fast']],
            ['type' => 'text', 'text' => 'What is one thing that could be improved? (optional)', 'required' => false],
        ];

        $adminQuestions = [
            ['type' => 'rating', 'text' => 'How satisfied are you with the responsiveness of student services?', 'required' => true],
            ['type' => 'rating', 'text' => 'How easy was it to get the information you needed?', 'required' => true],
            ['type' => 'single_choice', 'text' => 'Which service did you interact with most?', 'required' => true,
                'options' => ['Enrollment', 'Career support', 'Technical support', 'Finance']],
            ['type' => 'text', 'text' => 'Any suggestions for the administration? (optional)', 'required' => false],
        ];

        // Active course surveys (collecting now).
        $webCourse = $courses->firstWhere('name', 'Web Programming');
        $dbCourse = $courses->firstWhere('name', 'Databases');
        $uxCourse = $courses->firstWhere('name', 'UI/UX Design');
        $osCourse = $courses->firstWhere('name', 'Operating Systems');

        $surveyWeb = $this->makeSurvey($admin, [
            'title' => 'Weekly Evaluation — Web Programming',
            'description' => 'Your anonymous feedback on this week of Web Programming.',
            'type' => 'course',
            'course_id' => $webCourse->id,
            'status' => 'active',
            'starts_at' => now()->subDays(4),
            'ends_at' => now()->addDays(3),
        ], $courseQuestions);

        $surveyDb = $this->makeSurvey($admin, [
            'title' => 'Weekly Evaluation — Databases',
            'description' => 'Your anonymous feedback on this week of Databases.',
            'type' => 'course',
            'course_id' => $dbCourse->id,
            'status' => 'active',
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->addDays(5),
        ], $courseQuestions);

        // Closed surveys (full history for charts).
        $surveyUx = $this->makeSurvey($admin, [
            'title' => 'Weekly Evaluation — UI/UX Design (Week 9)',
            'description' => 'Closed evaluation from last week.',
            'type' => 'course',
            'course_id' => $uxCourse->id,
            'status' => 'closed',
            'starts_at' => now()->subDays(12),
            'ends_at' => now()->subDays(5),
        ], $courseQuestions);

        $surveyOs = $this->makeSurvey($admin, [
            'title' => 'Weekly Evaluation — Operating Systems (Week 9)',
            'description' => 'Closed evaluation from last week.',
            'type' => 'course',
            'course_id' => $osCourse->id,
            'status' => 'closed',
            'starts_at' => now()->subDays(12),
            'ends_at' => now()->subDays(5),
        ], $courseQuestions);

        $surveyAdmin = $this->makeSurvey($admin, [
            'title' => 'Administrative Services Feedback — May',
            'description' => 'Help us improve enrollment, finance and support services.',
            'type' => 'administration',
            'course_id' => null,
            'status' => 'closed',
            'starts_at' => now()->subDays(20),
            'ends_at' => now()->subDays(6),
        ], $adminQuestions);

        // A draft (no responses yet).
        $this->makeSurvey($admin, [
            'title' => 'Library & Facilities Survey (Draft)',
            'description' => 'Draft survey — not yet published to students.',
            'type' => 'administration',
            'course_id' => null,
            'status' => 'draft',
            'starts_at' => null,
            'ends_at' => null,
        ], $adminQuestions);

        // --- Simulate anonymous submissions ---
        $this->simulate($surveyWeb, $simPool, 0.78, $profileByCourse[$webCourse->id]);
        $this->simulate($surveyDb, $simPool, 0.64, $profileByCourse[$dbCourse->id]);
        $this->simulate($surveyUx, $simPool, 0.81, $profileByCourse[$uxCourse->id]);
        $this->simulate($surveyOs, $simPool, 0.59, $profileByCourse[$osCourse->id]);
        $this->simulate($surveyAdmin, $simPool, 0.7, 'good');

        $this->command->info('  Surveys seeded with anonymous demo responses.');
        $this->command->info('Done. Admin: admin@sudoforms.test / password  |  Student: student@sudoforms.test / password');
    }

    /**
     * @param  array<int, array<string, mixed>>  $questions
     */
    private function makeSurvey(User $admin, array $attributes, array $questions): Survey
    {
        $survey = Survey::create(array_merge($attributes, ['created_by' => $admin->id]));

        foreach ($questions as $index => $q) {
            $survey->questions()->create([
                'text' => $q['text'],
                'type' => $q['type'],
                'options' => $q['type'] === 'single_choice' ? $q['options'] : null,
                'required' => $q['required'] ?? true,
                'order' => $index,
            ]);
        }

        return $survey;
    }

    /**
     * Create anonymous responses + the matching participation log for a subset
     * of students. Participation (WHO) and responses (WHAT) are written as
     * independent records — they are never linked per student.
     */
    private function simulate(Survey $survey, Collection $activeStudents, float $ratio, string $profile): void
    {
        $survey->loadMissing('questions');
        $participants = $activeStudents->shuffle()->take((int) round($activeStudents->count() * $ratio));

        $ratingWeights = [
            'excellent' => [1, 2, 7, 25, 40],
            'good' => [2, 5, 15, 35, 30],
            'mixed' => [8, 12, 30, 30, 20],
            'poor' => [20, 25, 30, 15, 10],
        ][$profile] ?? [2, 5, 15, 35, 30];

        $comments = $this->commentPool($profile);

        DB::transaction(function () use ($survey, $participants, $ratingWeights, $comments) {
            foreach ($participants as $student) {
                $when = now()->subDays(random_int(0, 6))->subHours(random_int(0, 23));

                // WHO — participation log (keyed to the student).
                SurveyParticipation::create([
                    'survey_id' => $survey->id,
                    'user_id' => $student->id,
                    'submitted_at' => $when,
                ]);

                // WHAT — anonymous response (no student key).
                $response = Response::create([
                    'survey_id' => $survey->id,
                    'submitted_at' => $when,
                ]);

                foreach ($survey->questions as $question) {
                    $rating = null;
                    $value = null;

                    if ($question->type === 'rating') {
                        $rating = $this->weightedRating($ratingWeights);
                    } elseif ($question->type === 'single_choice') {
                        $options = $question->options ?? [];
                        $value = $options[array_rand($options)] ?? null;
                    } else { // text (optional) — ~35% leave a comment
                        if (random_int(1, 100) <= 35) {
                            $value = $comments[array_rand($comments)];
                        }
                    }

                    if (is_null($rating) && is_null($value)) {
                        continue;
                    }

                    Answer::create([
                        'response_id' => $response->id,
                        'question_id' => $question->id,
                        'rating' => $rating,
                        'value' => $value,
                    ]);
                }
            }
        });
    }

    /**
     * @param  array<int, int>  $weights  ratings 1..5
     */
    private function weightedRating(array $weights): int
    {
        $total = array_sum($weights);
        $roll = random_int(1, $total);
        $acc = 0;

        foreach ($weights as $i => $weight) {
            $acc += $weight;
            if ($roll <= $acc) {
                return $i + 1;
            }
        }

        return 5;
    }

    /**
     * @return array<int, string>
     */
    private function commentPool(string $profile): array
    {
        return match ($profile) {
            'excellent' => [
                'Great explanations and very supportive professor.',
                'Loved the practical, hands-on examples.',
                'Clear, well-structured and engaging lectures.',
                'Best course this semester so far.',
            ],
            'poor' => [
                'Lectures are hard to follow, please slow down.',
                'Too much theory and not enough practice.',
                'Communication about assignments could be much better.',
                'I often leave class more confused than before.',
            ],
            'mixed' => [
                'Good content but the pace is uneven.',
                'More worked examples would really help.',
                'Sometimes great, sometimes rushed.',
                'Assignments need clearer instructions.',
            ],
            default => [
                'Solid lectures, a few more examples would help.',
                'Pace is mostly fine, content is useful.',
                'Would appreciate faster feedback on assignments.',
                'Overall a good week.',
            ],
        };
    }
}
