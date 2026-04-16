<?php

if (!function_exists('formatDate')) {
    /**
     * Format date to Y-m-d
     *
     * @param mixed $date
     * @return string
     */
    function formatDate($date)
    {
        if (!$date) return '';
        return \Carbon\Carbon::parse($date)->format('Y-m-d');
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
