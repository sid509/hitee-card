<?php

namespace App\Http\Controllers;

use App\Models\NotificationTemplate;
use App\Models\User;
use App\Models\FcmToken;
use App\Models\Broadcast;
use App\Models\UserNotification;
use App\Mail\BroadcastEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class BroadcastController extends Controller
{
    public function index()
    {
        $templates = NotificationTemplate::latest()->paginate(10, ['*'], 'templates_page');
        $broadcasts = Broadcast::with('template', 'sender')->latest()->paginate(10, ['*'], 'broadcasts_page');
        return view('modules.broadcast.index', compact('templates', 'broadcasts'));
    }

    public function create()
    {
        return view('modules.broadcast.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:email,fcm,sms',
            'name' => 'required|string|max:255',
            'subject_en' => 'nullable|string|max:255',
            'subject_ne' => 'nullable|string|max:255',
            'body_en' => 'required|string',
            'body_ne' => 'required|string',
        ]);

        NotificationTemplate::create($request->all());

        return redirect()->route('broadcast.index')->with('success', __('messages.success'));
    }

    public function sendForm()
    {
        $templates = NotificationTemplate::all();
        return view('modules.broadcast.send', compact('templates'));
    }

    public function send(Request $request)
    {
        $request->validate([
            'template_id' => 'required|exists:notification_templates,id',
            'target_type' => 'required|in:all,specific',
            'specific_emails' => 'nullable|string',
            'specific_fcm_tokens' => 'nullable|string',
            'specific_phones' => 'nullable|string',
        ]);

        $template = NotificationTemplate::findOrFail($request->template_id);
        
        $users = collect();
        $extra_tokens = [];
        $extra_phones = [];

        if ($request->target_type === 'all') {
            $users = User::all();
        } else {
            $emails = array_filter(array_map('trim', explode(',', $request->specific_emails ?? '')));
            $tokens = array_filter(array_map('trim', explode(',', $request->specific_fcm_tokens ?? '')));
            $phones = array_filter(array_map('trim', explode(',', $request->specific_phones ?? '')));
            
            if (empty($emails) && empty($tokens) && empty($phones)) {
                return back()->withErrors(['target_type' => 'Please provide at least one email, FCM token, or phone number for specific targeting.']);
            }

            // Find users by email or phone
            $users = User::whereIn('email', $emails)
                         ->orWhereIn('phone_number', $phones)
                         ->get();

            // Store direct tokens/phones that might not be in our users table
            $extra_tokens = $tokens;
            $extra_phones = $phones;
        }

        $broadcast = Broadcast::create([
            'template_id' => $template->id,
            'sent_by' => auth()->id(),
            'type' => $template->type,
            'title' => $template->name,
            'total_count' => $users->count() + count($extra_tokens) + count($extra_phones),
        ]);

        $successCount = 0;
        $failCount = 0;

        // 1. Send to identified users
        foreach ($users as $user) {
            if ($this->sendToUser($user, $template, $broadcast->id)) {
                $successCount++;
            } else {
                $failCount++;
            }
        }

        // 2. Send to extra FCM tokens (if target is specific)
        if ($request->target_type === 'specific') {
            foreach ($extra_tokens as $token) {
                // Check if we already sent to this token via identifyUsers (optional optimization)
                $fcmModel = FcmToken::where('token', $token)->first();
                $userByToken = $fcmModel?->user;
                
                $title = $template->subject_en;
                $body = $template->body_en;

                if ($userByToken) {
                    $lang = $userByToken->preferred_language === 'ne' ? 'ne' : 'en';
                    $title = $lang === 'ne' ? $template->subject_ne : $template->subject_en;
                    $body = $lang === 'ne' ? $template->body_ne : $template->body_en;
                    
                    $title = str_replace('{name}', $userByToken->name, $title ?? '');
                    $body = str_replace('{name}', $userByToken->name, $body ?? '');
                } else {
                    $title = str_replace('{name}', 'Valued User', $title ?? '');
                    $body = str_replace('{name}', 'Valued User', $body ?? '');
                }

                $result = $this->sendFcmNotification($token, $title, $body);
                if ($result === true) {
                    $successCount++;
                } elseif ($result === 'invalid') {
                    if ($fcmModel) $fcmModel->delete();
                    $failCount++;
                } else {
                    $failCount++;
                }
            }

            foreach ($extra_phones as $phone) {
                $userByPhone = User::where('phone_number', $phone)->first();
                $body = $template->body_en;

                if ($userByPhone) {
                    $lang = $userByPhone->preferred_language === 'ne' ? 'ne' : 'en';
                    $body = $lang === 'ne' ? $template->body_ne : $template->body_en;
                    $body = str_replace('{name}', $userByPhone->name, $body ?? '');
                } else {
                    $body = str_replace('{name}', 'Valued User', $body ?? '');
                }

                if ($this->sendSmsNotification($phone, $body)) {
                    $successCount++;
                } else {
                    $failCount++;
                }
            }
        }

        $broadcast->update([
            'success_count' => $successCount,
            'fail_count' => $failCount,
        ]);

        return redirect()->route('broadcast.index')->with('success', __('messages.success'));
    }

    private function sendToUser(User $user, NotificationTemplate $template, $broadcastId = null)
    {
        $lang = $user->preferred_language === 'ne' ? 'ne' : 'en';
        $subject = $lang === 'ne' ? $template->subject_ne : $template->subject_en;
        $body = $lang === 'ne' ? $template->body_ne : $template->body_en;

        // Variable replacement
        $subject = str_replace('{name}', $user->name, $subject ?? '');
        $body = str_replace('{name}', $user->name, $body ?? '');

        $status = 'failed';
        $errorMessage = null;

        try {
            if ($template->type === 'email') {
                if ($user->email) {
                    Mail::to($user->email)->send(new BroadcastEmail($subject, $body));
                    $status = 'sent';
                } else {
                    $errorMessage = "User has no email.";
                }
            } elseif ($template->type === 'fcm') {
                $tokens = $user->fcmTokens;
                if ($tokens->isNotEmpty()) {
                    $anySuccess = false;
                    foreach ($tokens as $fcmToken) {
                        $fcmResult = $this->sendFcmNotification($fcmToken->token, $subject, $body);
                        if ($fcmResult === true) {
                            $anySuccess = true;
                        } elseif ($fcmResult === 'invalid') {
                            $fcmToken->delete();
                        }
                    }
                    if ($anySuccess) $status = 'sent';
                    else $errorMessage = "All FCM tokens failed.";
                } else {
                    $errorMessage = "User has no FCM tokens.";
                }
            } elseif ($template->type === 'sms') {
                if ($user->phone_number) {
                    if ($this->sendSmsNotification($user->phone_number, $body)) {
                        $status = 'sent';
                    } else {
                        $errorMessage = "SMS provider failed.";
                    }
                } else {
                    $errorMessage = "User has no phone number.";
                }
            }
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
        }

        UserNotification::create([
            'user_id' => $user->id,
            'broadcast_id' => $broadcastId,
            'template_id' => $template->id,
            'type' => $template->type,
            'subject' => $subject,
            'body' => $body,
            'language' => $lang,
            'status' => $status,
            'error_message' => $errorMessage,
        ]);

        return $status === 'sent';
    }

    public function show(Broadcast $broadcast)
    {
        $logs = $broadcast->notifications()->with('user')->paginate(20);
        return view('modules.broadcast.show', compact('broadcast', 'logs'));
    }

    public function destroy(NotificationTemplate $template)
    {
        $template->delete();
        return redirect()->route('broadcast.index')->with('success', __('messages.success'));
    }

    private function sendFcmNotification($token, $title, $body)
    {
        $fcmServerKey = \App\Models\Setting::get('NOTIFICATION_TOKEN', env('NOTIFICATION_TOKEN'));
        if (!$fcmServerKey) return true; // Simulation

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $fcmServerKey,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'to' => $token,
                'notification' => ['title' => $title, 'body' => strip_tags($body)],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['failure']) && $data['failure'] > 0) {
                    $error = $data['results'][0]['error'] ?? '';
                    return in_array($error, ['NotRegistered', 'InvalidRegistration']) ? 'invalid' : false;
                }
                return true;
            }
            return false;
        } catch (\Exception $e) {
            Log::error("FCM Send Error: " . $e->getMessage());
            return false;
        }
    }

    private function sendSmsNotification($phone, $message)
    {
        // Placeholder for SMS Gateway integration
        Log::info("Sending SMS to $phone: " . strip_tags($message));
        return true;
    }
}
