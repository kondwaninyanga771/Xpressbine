<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price_min', 12, 2);
            $table->decimal('price_max', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('provider_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('bio')->nullable();
            $table->string('verification_status')->default('pending')->index();
            $table->string('nrc_number')->nullable();
            $table->unsignedTinyInteger('years_experience')->nullable();
            $table->decimal('hourly_rate', 12, 2)->default(0);
            $table->decimal('service_radius_km', 6, 2)->default(15);
            $table->decimal('rating_average', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('category_provider', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_profile_id')->constrained()->cascadeOnDelete();
            $table->primary(['category_id', 'provider_profile_id']);
        });

        Schema::create('provider_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_profile_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('caption')->nullable();
            $table->timestamps();
        });

        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->unsignedInteger('duration_minutes')->default(120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('business_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('area')->nullable();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('spending_limit', 12, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('auto_topup_enabled')->default(false);
            $table->decimal('auto_topup_threshold', 12, 2)->nullable();
            $table->decimal('auto_topup_amount', 12, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('job_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('business_site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('address');
            $table->string('area')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('budget_min', 12, 2)->nullable();
            $table->decimal('budget_max', 12, 2)->nullable();
            $table->timestamp('preferred_at')->nullable();
            $table->string('status')->default('open')->index();
            $table->boolean('client_absent')->default(false);
            $table->string('on_site_contact_name')->nullable();
            $table->string('on_site_contact_phone', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_profile_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->text('message')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('status')->default('pending')->index();
            $table->timestamps();
            $table->unique(['job_request_id', 'provider_profile_id']);
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('provider_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('business_site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->index();
            $table->timestamp('scheduled_at');
            $table->unsignedInteger('duration_minutes');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('customer_confirmed_at')->nullable();
            $table->timestamp('auto_release_at')->nullable()->index();
            $table->timestamp('auto_released_at')->nullable();
            $table->timestamp('escrow_released_at')->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('commission_amount', 12, 2)->nullable();
            $table->decimal('payout_amount', 12, 2)->nullable();
            $table->string('address');
            $table->string('area')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('client_absent')->default(false);
            $table->string('entry_pin', 12)->nullable();
            $table->string('on_site_contact_name')->nullable();
            $table->string('on_site_contact_phone', 20)->nullable();
            $table->boolean('fix_attempted')->default(false);
            $table->timestamp('fix_due_at')->nullable();
            $table->timestamps();
        });

        Schema::create('booking_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('path');
            $table->timestamps();
        });

        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rater_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ratee_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('stars');
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['booking_id', 'rater_id']);
        });

        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('raised_by')->constrained('users')->cascadeOnDelete();
            $table->text('reason');
            $table->string('evidence_path')->nullable();
            $table->string('status')->default('open')->index();
            $table->string('outcome')->nullable();
            $table->decimal('refund_amount', 12, 2)->nullable();
            $table->text('provider_response')->nullable();
            $table->timestamp('provider_responded_at')->nullable();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('wallet_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->index();
            $table->decimal('amount', 12, 2);
            $table->decimal('escrow_delta', 12, 2)->default(0);
            $table->string('currency', 3)->default('ZMW');
            $table->string('idempotency_key')->unique();
            $table->string('description');
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['booking_id', 'type']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('moderation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->text('reason');
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('appeals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('moderation_action_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->string('status')->default('pending')->index();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_notes')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('appeals');
        Schema::dropIfExists('moderation_actions');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('disputes');
        Schema::dropIfExists('ratings');
        Schema::dropIfExists('booking_photos');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('job_requests');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('business_sites');
        Schema::dropIfExists('businesses');
        Schema::dropIfExists('packages');
        Schema::dropIfExists('provider_photos');
        Schema::dropIfExists('category_provider');
        Schema::dropIfExists('provider_profiles');
        Schema::dropIfExists('categories');
    }
};
