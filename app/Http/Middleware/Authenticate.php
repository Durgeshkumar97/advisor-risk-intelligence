protected function redirectTo($request)
{
    if (! $request->expectsJson()) {
        return route('login');
    }
}