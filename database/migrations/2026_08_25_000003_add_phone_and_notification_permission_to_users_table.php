<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Two related columns:
     *
     *   receive_notifications — the opt-in that decides who gets alerted. This
     *   project authorises by ROLE (roles + users.role_id) and has no granular
     *   permission table, so this is a per-user flag rather than a new
     *   permission system. Role must not imply it: an Admin who does not want
     *   alerts should not get them, and an Employee who should, can.
     *
     *   phone — nullable at the database level because most users never need
     *   one. It only becomes required when receive_notifications is enabled,
     *   and that rule is enforced in validation rather than by the schema:
     *   a NOT NULL column would break every existing user row and would also
     *   forbid keeping a stored number after the permission is revoked.
     */
    public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        if (!Schema::hasColumn('users', 'phone')) {
            $table->string('phone', 20)->nullable()->after('email');
        }

        if (!Schema::hasColumn('users', 'receive_notifications')) {
            $table->boolean('receive_notifications')->default(false);
        }
    });
}

    /**
     * Reverse the migrations.
     */
   public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        if (Schema::hasColumn('users', 'receive_notifications')) {
            $table->dropColumn('receive_notifications');
        }

        if (Schema::hasColumn('users', 'phone')) {
            $table->dropColumn('phone');
        }
    });
}
};
