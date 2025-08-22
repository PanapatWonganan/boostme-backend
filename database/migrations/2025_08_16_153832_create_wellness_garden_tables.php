<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. User Gardens (ข้อมูลสวนของแต่ละผู้ใช้)
        Schema::create('user_gardens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->integer('level')->default(1);
            $table->integer('xp')->default(0);
            $table->integer('star_seeds')->default(100); // เงินในเกม
            $table->string('theme')->default('tropical'); // ธีมสวน
            $table->json('garden_layout')->nullable(); // layout ของสวน
            $table->timestamp('last_watered_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'level']);
        });

        // 2. Plant Types (ประเภทพืชที่มีในระบบ)
        Schema::create('plant_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name'); // ชื่อพืช
            $table->string('category'); // fitness, nutrition, mental, learning
            $table->string('rarity')->default('common'); // common, rare, epic, legendary
            $table->json('growth_stages'); // ขั้นตอนการเติบโต
            $table->json('care_requirements'); // ความต้องการในการดูแล
            $table->string('icon_path')->nullable(); // รูปไอคอน
            $table->text('description')->nullable();
            $table->integer('base_xp_reward')->default(50);
            $table->integer('unlock_level')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category', 'rarity']);
        });

        // 3. User Plants (พืชที่ผู้ใช้ปลูกในสวน)
        Schema::create('user_plants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('garden_id');
            $table->uuid('plant_type_id');
            $table->string('custom_name')->nullable(); // ชื่อที่ผู้ใช้ตั้ง
            $table->integer('stage')->default(0); // ขั้นการเติบโต (0-4)
            $table->integer('health')->default(100); // สุขภาพพืช
            $table->integer('growth_points')->default(0); // คะแนนการเติบโต
            $table->json('position')->nullable(); // ตำแหน่งในสวน {x, y}
            $table->timestamp('planted_at');
            $table->timestamp('last_watered_at')->nullable();
            $table->timestamp('next_water_at')->nullable();
            $table->timestamp('harvested_at')->nullable();
            $table->boolean('is_fully_grown')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('garden_id')->references('id')->on('user_gardens')->onDelete('cascade');
            $table->foreign('plant_type_id')->references('id')->on('plant_types')->onDelete('cascade');
            $table->index(['user_id', 'stage']);
            $table->index(['garden_id', 'is_fully_grown']);
        });

        // 4. Achievements (ความสำเร็จในเกม)
        Schema::create('achievements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('category'); // learning, fitness, mental, social, special
            $table->text('description');
            $table->string('badge_icon')->nullable();
            $table->string('rarity')->default('common'); // common, rare, epic, legendary
            $table->json('criteria'); // เงื่อนไขการได้รับ
            $table->integer('xp_reward')->default(100);
            $table->integer('star_seeds_reward')->default(50);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['category', 'rarity']);
        });

        // 5. User Achievements (ความสำเร็จที่ผู้ใช้ได้รับ)
        Schema::create('user_achievements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('achievement_id');
            $table->timestamp('earned_at');
            $table->json('progress_data')->nullable(); // ข้อมูลความก้าวหน้า
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('achievement_id')->references('id')->on('achievements')->onDelete('cascade');
            $table->unique(['user_id', 'achievement_id']);
            $table->index(['user_id', 'earned_at']);
        });

        // 6. Garden Activities (กิจกรรมในสวน)
        Schema::create('garden_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('garden_id');
            $table->string('activity_type'); // water, plant, harvest, fertilize
            $table->string('target_type')->nullable(); // plant, garden
            $table->uuid('target_id')->nullable(); // user_plant_id or garden_id
            $table->integer('xp_earned')->default(0);
            $table->integer('star_seeds_earned')->default(0);
            $table->json('activity_data')->nullable(); // ข้อมูลเพิ่มเติม
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('garden_id')->references('id')->on('user_gardens')->onDelete('cascade');
            $table->index(['user_id', 'activity_type', 'created_at']);
        });

        // 7. Garden Friends (ระบบเพื่อน)
        Schema::create('garden_friends', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id'); // ผู้ส่งคำขอ
            $table->uuid('friend_id'); // ผู้รับคำขอ
            $table->enum('status', ['pending', 'accepted', 'blocked'])->default('pending');
            $table->timestamp('requested_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('friend_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'friend_id']);
            $table->index(['user_id', 'status']);
        });

        // 8. Daily Challenges (ภารกิจประจำวัน)
        Schema::create('daily_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description');
            $table->string('challenge_type'); // course_complete, video_watch, login, social
            $table->json('requirements'); // เงื่อนไขการทำภารกิจ
            $table->integer('xp_reward')->default(50);
            $table->integer('star_seeds_reward')->default(25);
            $table->date('available_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['available_date', 'is_active']);
        });

        // 9. User Challenge Progress (ความก้าวหน้าของภารกิจ)
        Schema::create('user_challenge_progress', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('challenge_id');
            $table->integer('progress')->default(0); // ความก้าวหน้า
            $table->integer('target')->default(1); // เป้าหมาย
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->json('progress_data')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('challenge_id')->references('id')->on('daily_challenges')->onDelete('cascade');
            $table->unique(['user_id', 'challenge_id']);
            $table->index(['user_id', 'is_completed']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_challenge_progress');
        Schema::dropIfExists('daily_challenges');
        Schema::dropIfExists('garden_friends');
        Schema::dropIfExists('garden_activities');
        Schema::dropIfExists('user_achievements');
        Schema::dropIfExists('achievements');
        Schema::dropIfExists('user_plants');
        Schema::dropIfExists('plant_types');
        Schema::dropIfExists('user_gardens');
    }
};
