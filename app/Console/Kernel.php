use App\Models\ClientIntake;
use Illuminate\Support\Facades\Mail;

protected function schedule(Schedule $schedule): void
{
    $schedule->call(function () {

        $users = ClientIntake::whereIn('status', ['trial', 'active'])->get();

        foreach ($users as $user) {

            $report = $this->generateReport($user);

            Mail::raw($report, function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Weekly Risk Report');
            });

        }

    })->weeklyOn(1, '09:00'); // Monday 9 AM
}