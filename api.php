<?php

// Replace with your actual API key
$apiKey = 'AIzaSyAMWxaoB5HaarQ-PTP-Dk1a86xngqLTnCg';

// The base URL for the YouTube API endpoint
$baseUrl = 'https://www.googleapis.com/youtube/v3/';

// Search parameters
$maxResults = 25;
$videoType = 'video'; // Type of content
$minViewCount = 500; // More than 500 views
$videoCategoryId = '28'; // Science & Technology

// List of video durations to search for
$videoDurations = array('medium', 'long');

// List of channel IDs you want to retrieve videos from
$wantedChannels = array(
    'UC6hlQ0x6kPbAGjYkoz53cvA',
    'UCiT1BmYvOBsEvU9iw0076Sw',
    // Add more channel IDs here
);

// List of unwanted channel IDs
$unwantedChannels = array(
    '',
    '',
    // Add more unwanted channel IDs here
);

// Add a function to cache API responses
function cacheApiResponse($cacheKey, $response)
{
    $cacheDirectory = 'cache/';
    if (!is_dir($cacheDirectory)) {
        mkdir($cacheDirectory, 0755, true);
    }
    $cacheFile = $cacheDirectory . $cacheKey . '.json';
    file_put_contents($cacheFile, json_encode($response));
}

// Add a function to get cached responses
function getCachedApiResponse($cacheKey)
{
    $cacheDirectory = 'cache/';
    $cacheFile = $cacheDirectory . $cacheKey . '.json';

    if (file_exists($cacheFile)) {
        $cachedResponse = json_decode(file_get_contents($cacheFile), true);
        return $cachedResponse;
    }

    return false;
}

// Function to perform API request with exponential backoff
function apiRequestWithExponentialBackoff($requestUrl)
{
    $maxAttempts = 5;
    $backoffDelay = 1; // Initial backoff delay in seconds

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        // Send the API request
        $response = sendApiRequest($requestUrl);

        if ($response !== false) {
            return $response;
        }

        // Handle exponential backoff
        $backoffTime = pow(2, $attempt - 1) * $backoffDelay;
        sleep($backoffTime);
    }

    return false;
}

// Function to send an API request
function sendApiRequest($requestUrl)
{
    // Initiate cURL session
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $requestUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute cURL session and get the response
    $response = curl_exec($ch);

    // Check for cURL errors
    if (curl_errno($ch)) {
        echo 'Error: ' . curl_error($ch);
        exit;
    }

    // Close cURL session
    curl_close($ch);

    // Decode the JSON response
    return json_decode($response, true);
}

// Initialize an array to store combined results
$combinedResults = array();

foreach ($videoDurations as $duration) {
    foreach ($wantedChannels as $channelId) {
        // Build the request URL
        $requestUrl = $baseUrl . 'search' .
            '?part=snippet' .
            '&maxResults=' . $maxResults .
            '&order=date' .  // Order by date (newest first)
            '&videoDuration=' . $duration . // Video duration
            '&type=' . $videoType . // Filter by video type
            '&videoCategoryId=' . $videoCategoryId . // Filter by Science & Technology category
            '&channelId=' . $channelId . // Filter by specific channel
            '&key=' . $apiKey;

        // Generate a cache key based on the request parameters
        $cacheKey = md5($requestUrl);

        // Check if a cached response is available
        $cachedResponse = getCachedApiResponse($cacheKey);

        if ($cachedResponse !== false) {
            $data = $cachedResponse;
        } else {
            // Send the API request with exponential backoff
            $data = apiRequestWithExponentialBackoff($requestUrl);

            // Cache the response
            cacheApiResponse($cacheKey, $data);
        }

        // Add the results to the combined array
        if (isset($data['items'])) {
            $combinedResults = array_merge($combinedResults, $data['items']);
        }
    }
}

// Sort the combined results by published date (newest first)
usort($combinedResults, function ($a, $b) {
    return strtotime($b['snippet']['publishedAt']) - strtotime($a['snippet']['publishedAt']);
});

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <style>
        .video-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 20px;
            padding: 10px;
        }

        .video-card {
            width: calc(20% - 20px);
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .video-thumbnail {
            width: 100%;
            border-radius: 10px 10px 0 0;
        }

        .video-title {
            padding: 10px;
            font-weight: bold;
            color: #333;
            text-decoration: none;
            transition: color 0.3s;
        }

        .video-title:hover {
            color: #ff5722;
        }
    </style>
</head>
<body>
    <div class="video-grid">
        <?php
        foreach ($combinedResults as $item) {
            $channelId = $item['snippet']['channelId'];

            // Check if the channel is unwanted
            if (!in_array($channelId, $unwantedChannels)) {
                $videoId = $item['id']['videoId'];
                $videoLink = 'https://www.youtube.com/watch?v=' . $videoId;
                $videoTitle = htmlspecialchars($item['snippet']['title']);
                $mediumThumbnail = $item['snippet']['thumbnails']['medium']['url'];

                // Fetch video statistics to get view count
                $videoStatsUrl = $baseUrl . 'videos' .
                    '?part=statistics' .
                    '&id=' . $videoId .
                    '&key=' . $apiKey;

                $statsResponse = file_get_contents($videoStatsUrl);
                $statsData = json_decode($statsResponse, true);

                if (isset($statsData['items'][0]['statistics']['viewCount'])) {
                    $viewCount = $statsData['items'][0]['statistics']['viewCount'];

                    // Only display videos with view count more than 500
                    if ($viewCount > $minViewCount) {
                        echo '<div class="video-card">' . PHP_EOL;
                        echo '    <a class="video-title" href="' . $videoLink . '" title="' . $videoTitle . '">' . PHP_EOL;
                        echo '        <img class="video-thumbnail" src="' . $mediumThumbnail . '" alt="' . $videoTitle . '">' . PHP_EOL;
                        echo '        ' . $videoTitle . PHP_EOL;
                        echo '    </a>' . PHP_EOL;
                        echo '</div>' . PHP_EOL;
                    }
                }
            }
        }
        ?>
    </div>
</body>
</html>