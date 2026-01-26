<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Course;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create test user (provider)
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '+255789123456',
            'role' => 'provider',
        ]);

        // Create test course
        Course::create([
            'provider_id' => $user->id,
            'title' => 'Test Course',
            'category' => 'Technology',
            'mode' => 'Online',
            'short_description' => 'A test course for cohorts',
            'long_description' => 'This is a test course',
            'learning_outcomes' => ['Learn PHP', 'Learn Laravel'],
            'skills' => ['Backend Development'],
            'requirements' => ['Basic PHP knowledge'],
            'contents' => ['Lesson 1', 'Lesson 2'],
            'status' => 'published',
            'banner' => null
        ]);
    }
}
