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
        //1. Master Kategori Barang
      Schema::create('categories', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
      });

        //2. Data Laporan
      Schema::create('items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        $table->foreignId('category_id')->constrained('categories');
        $table->enum('type', ['lost', 'found']);
        $table->enum('status', ['open', 'claimed', 'returned'])->default('open');
        $table->string('title');
        $table->text('description');
        $table->string('building');
        $table->string('floor_detail')->nullable();
        $table->date('event_date');
        $table->string('photo_path')->nullable();
        $table->boolean('is_sensitive')->default(false);
        $table->timestamps();
      });

        //3. Master Hashtag
      Schema::create('tags', function (Blueprint $table) {
        $table->id();
        $table->string('name')->unique();
        $table->timestamps();
      });

        //4. Relation Table (Item & Tag) [Many to Many]
      Schema::create('item_tag', function (Blueprint $table) {
        $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
        $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
        $table->primary(['item_id', 'tag_id']);
        });

        //5. Claiming
      Schema::create('claims', function (Blueprint $table) {
        $table->id();
        $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
        $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        $table->text('proof_descrition');
        $table->string('proof_file_path')->nullable();
        $table->string('claim_token', 10)->nullable()->unique();
        $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
        $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamp('handover_at')->nullable();
        $table->timestamps();
      });

      //6. Messaging
      Schema::create('messages', function (Blueprint $table) {
        $table->id();
        $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
        $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
        $table->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();
        $table->text('message');
        $table->boolean('is_read')->default(false);
        $table->timestamps();
      });

      //7. Audit Trail
      Schema::create('audit_logs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
        $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        $table->string('action');
        $table->text('notes')->nullable();
        $table->timestamps();
      });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('claims');
        Schema::dropIfExists('item_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('items');
        Schema::dropIfExists('categories');
    }
};
