use App\Models\ClientIntake;
use App\Services\ReportService;
use Illuminate\Support\Facades\Mail;

protected function schedule(Schedule $schedule): void
{
    $schedule->call(function () {

        $reportService = new ReportService();

        $users = ClientIntake::whereIn('status', ['trial', 'active'])->get();

        foreach ($users as $user) {

            if (!$user->email) continue;

            $report = $reportService->generate($user);

            Mail::raw($report, function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Your Weekly Risk Report');
            });

        }

    })->weeklyOn(1, '09:00');
} 
