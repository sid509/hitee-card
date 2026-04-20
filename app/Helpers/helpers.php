<?php

if (!function_exists('formatDate')) {
    /**
     * Format date to a uniform proper format
     *
     * @param mixed $date
     * @param bool $showTime
     * @return string
     */
    function formatDate($date, $showTime = true)
    {
        if (!$date) return '';
        $format = $showTime ? 'M d, Y h:i A' : 'M d, Y';
        return \Carbon\Carbon::parse($date)->format($format);
    }
}

if (!function_exists('apiResponse')) {
    /**
     * Generate a JSON response for an API endpoint.
     *
     * @param bool $status The status of the response. Defaults to false.
     * @param string $message The message of the response. Defaults to 'Something Went Wrong'.
     * @param mixed $content The data to be included in the response. Defaults to empty string.
     * @param int $http_code The HTTP status code of the response. Defaults to 500.
     * @param mixed $metaContent The metadata to be included in the response. Defaults to empty array.
     * @return \Illuminate\Http\JsonResponse The JSON response.
     */
    function apiResponse(bool $status = false, string $message = 'Something Went Wrong', mixed $content = '', int $http_code = 200, mixed $metaContent = []): \Illuminate\Http\JsonResponse
    {
        if (!$status && $http_code === 200) {
            $http_code = 500;
        }
        $response['status'] = $status;
        $response['message'] = $message;
        $response['meta'] = $metaContent;

        if ($content instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $paginate = [
                'total' => $content->total(),
                'per_page' => $content->perPage(),
                'current_page' => $content->currentPage(),
                'last_page' => $content->lastPage(),
            ];
            $response['paginate'] = $paginate;
            $content = $content->items();
        }

        $response['content'] = $content;

        return response()->json($response, $http_code);
    }
}

if (!function_exists('logActivity')) {
    /**
     * Log a user activity to a buffer file.
     *
     * @param string $action The action being performed.
     * @param string $description A human-readable description of the activity.
     * @param array $properties Additional context for the activity.
     * @param int|null $userId The ID of the user performing the activity. Defaults to current user.
     * @return bool
     */
    function logActivity($action, $description, $properties = [], $userId = null)
    {
        $logData = [
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'properties' => json_encode($properties),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];

        try {
            // Append as a new line to storage/app/activity_buffer.jsonl
            \Illuminate\Support\Facades\Storage::disk('local')->append('activity_buffer.jsonl', json_encode($logData));
            return true;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Activity logging failed: ' . $e->getMessage());
            return false;
        }
    }
}
