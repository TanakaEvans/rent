<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\AcademicPeriod;
use App\Models\StudyLevel;
use App\Models\ClassGroup;
use App\Models\StudentCategory;
use App\Models\AdmissionType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        $gender = $this->faker->randomElement(['male', 'female']);
        $firstName = $gender === 'male'
            ? $this->faker->firstNameMale()
            : $this->faker->firstNameFemale();

        // Generate a unique admission number using timestamp and random string
        $admissionNumber = 'STU' . date('Y') . strtoupper(Str::random(6));

        return [
            'first_name' => $firstName,
            'middle_name' => $this->faker->optional(0.7)->firstName(),
            'last_name' => $this->faker->lastName(),
            'admission_number' => $admissionNumber,
            'date_of_birth' => $this->faker->dateTimeBetween('-15 years', '-6 years')->format('Y-m-d'),
            'gender' => $gender,
            'email' => null, // Set to null to avoid unique constraint issues
            'phone' => $this->faker->optional(0.6)->numerify('+263 7## ### ###'),
            'address' => $this->faker->optional(0.7)->address(),
            'academic_period_id' => AcademicPeriod::factory(),
            'study_level_id' => StudyLevel::factory(),
            'class_group_id' => ClassGroup::factory(),
            'student_category_id' => StudentCategory::factory(),
            'admission_type_id' => AdmissionType::factory(),
            'program_id' => null,
            'admission_date' => $this->faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'status' => $this->faker->randomElement(['active', 'active', 'active', 'suspended', 'graduated', 'transferred']),
        ];
    }

    /**
     * Assign to a specific academic period
     */
    public function forPeriod(AcademicPeriod $period): static
    {
        return $this->state(fn (array $attributes) => [
            'academic_period_id' => $period->id,
        ]);
    }

    /**
     * Assign to a specific study level
     */
    public function forLevel(StudyLevel $level): static
    {
        return $this->state(fn (array $attributes) => [
            'study_level_id' => $level->id,
        ]);
    }

    /**
     * Assign to a specific class group
     */
    public function forClass(ClassGroup $classGroup): static
    {
        return $this->state(fn (array $attributes) => [
            'class_group_id' => $classGroup->id,
            'study_level_id' => $classGroup->study_level_id,
        ]);
    }

    /**
     * Assign to a specific category
     */
    public function forCategory(StudentCategory $category): static
    {
        return $this->state(fn (array $attributes) => [
            'student_category_id' => $category->id,
        ]);
    }

    /**
     * Assign a specific admission type
     */
    public function withAdmissionType(AdmissionType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'admission_type_id' => $type->id,
        ]);
    }

    /**
     * Set student as active
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Set student as suspended
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'suspended',
        ]);
    }

    /**
     * Male student
     */
    public function male(): static
    {
        return $this->state(fn (array $attributes) => [
            'gender' => 'male',
            'first_name' => $this->faker->firstNameMale(),
        ]);
    }

    /**
     * Female student
     */
    public function female(): static
    {
        return $this->state(fn (array $attributes) => [
            'gender' => 'female',
            'first_name' => $this->faker->firstNameFemale(),
        ]);
    }

    /**
     * Add email to student
     */
    public function withEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => $this->faker->unique()->safeEmail(),
        ]);
    }
}
