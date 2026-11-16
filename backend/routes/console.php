<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
| Scheduled jobs. On cPanel add ONE cron job (every minute):
|   * * * * * cd /home/USER/loon && php artisan schedule:run >> /dev/null 2>&1
*/
Schedule::command('business:reminders')->dailyAt('09:00')->withoutOverlapping();

/*
| Reset an admin password from the terminal (when e-mail is not set up):
|   php artisan admin:password admin@example.com
*/
Artisan::command('admin:password {email} {password?}', function (string $email, ?string $password = null) {
    $user = User::where('email', $email)->first();
    if (! $user) {
        $this->error("No admin user with e-mail {$email}.");
        $this->line('Existing admins: '.User::pluck('email')->implode(', '));

        return 1;
    }

    $password ??= $this->secret('New password (min 8 characters)');
    if (strlen((string) $password) < 8) {
        $this->error('Password must be at least 8 characters.');

        return 1;
    }

    $user->password = $password;
    $user->save();
    $user->tokens()->delete();

    $this->info("Password changed for {$email}. All sessions were signed out.");

    return 0;
})->purpose('Change an admin user password');
