use Razorpay\Api\Api;
use Illuminate\Support\Facades\DB;
use App\Models\ClientIntake;

public function verify(Request $request)
{
    $api = new Api(env('RAZORPAY_KEY'), env('RAZORPAY_SECRET'));

    try {

        // 1. Verify Razorpay signature
        $attributes = [
            'razorpay_order_id'   => $request->razorpay_order_id,
            'razorpay_payment_id'=> $request->razorpay_payment_id,
            'razorpay_signature' => $request->razorpay_signature,
        ];

        $api->utility->verifyPaymentSignature($attributes);

        DB::beginTransaction();

        // 2. Find latest intake (by phone)
        $intake = ClientIntake::where('phone', $request->phone)
            ->latest()
            ->first();

        if (!$intake) {
            throw new \Exception('User not found');
        }

        // 3. Prevent duplicate payment (CRITICAL)
        $existing = DB::table('payments')
            ->where('razorpay_payment_id', $request->razorpay_payment_id)
            ->first();

        if ($existing) {
            return redirect()->route('payment.success');
        }

        // 4. Update intake → ACTIVE
        $intake->update([
            'status' => 'active',
            'plan'   => $request->plan,
            'converted_at' => now(),
            'ai_status' => 'active',
        ]);

        // 5. Store payment
        DB::table('payments')->insert([
            'client_intake_id'      => $intake->id,
            'name'                  => $intake->name,
            'email'                 => $intake->email,
            'phone'                 => $intake->phone,
            'plan'                  => $request->plan,
            'amount'                => $request->amount,
            'currency'              => 'INR',
            'status'                => 'paid',
            'provider'              => 'razorpay',
            'razorpay_order_id'     => $request->razorpay_order_id,
            'razorpay_payment_id'   => $request->razorpay_payment_id,
            'razorpay_signature'    => $request->razorpay_signature,
            'paid_at'               => now(),
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        // 6. Create subscription (basic version)
        DB::table('subscriptions')->insert([
            'lead_id'   => null,
            'user_id'   => null,
            'plan_id'   => 1, // FIX later (dynamic)
            'status'    => 'active',
            'starts_at' => now(),
            'ends_at'   => now()->addMonth(),
            'provider'  => 'razorpay',
            'created_at'=> now(),
            'updated_at'=> now(),
        ]);

        DB::commit();

        // 7. Pass data to success page
        session([
            'plan'   => $request->plan,
            'amount' => $request->amount,
        ]);

        return redirect()->route('payment.success');

    } catch (\Exception $e) {

        DB::rollBack();

        return response()->json([
            'success' => false,
            'error'   => $e->getMessage(),
        ], 400);
    }
}