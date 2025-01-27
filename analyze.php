<?php
require 'vendor/autoload.php'; // Load Composer autoloader
require 'inc.php'; // Include database connection
use OpenAI\Client;

// Load API key from .openAI_KEY file
$apiKeyFile = __DIR__ . '/.openAI_KEY';
if (!file_exists($apiKeyFile) || !is_readable($apiKeyFile)) {
    die("Error: API key file (.openAI_KEY) is missing or unreadable.\n");
}
$apiKey = trim(file_get_contents($apiKeyFile));
if (empty($apiKey)) {
    die("Error: API key file (.openAI_KEY) is empty.\n");
}

// Function to estimate token count (1 token ≈ 4 characters)
function estimateTokens($text) {
    return ceil(strlen($text) / 4);
}

// Function to split transcription into chunks
function splitIntoChunks($text, $maxTokens = 3000) {
    $chunks = [];
    $words = explode(' ', $text);
    $currentChunk = '';

    foreach ($words as $word) {
        if (estimateTokens($currentChunk . ' ' . $word) > $maxTokens) {
            $chunks[] = $currentChunk;
            $currentChunk = $word;
        } else {
            $currentChunk .= ' ' . $word;
        }
    }
    if (!empty($currentChunk)) {
        $chunks[] = $currentChunk;
    }

    return $chunks;
}

// Initialize OpenAI client
$client = OpenAI::client($apiKey);

while (true) {
    // Fetch the record where a summary is needed
    $query = "SELECT title, transcription, yt_id, summary, chunks FROM `videos` WHERE transcription IS NOT NULL AND summary IS NULL ORDER BY chunks ASC LIMIT 1";
    #$query = "SELECT transcription, yt_id, summary FROM `videos` WHERE transcription IS NOT NULL AND summary IS NULL LIMIT 1";
    $result = $conn->query($query);

    if ($result->num_rows === 0) {
        echo "No more records found that require a summary.\n";
        break;
    }

    // Fetch the record
    $record = $result->fetch_assoc();
    $transcription = $record['transcription'];
    $title = $record['title'];
    $yt_id = $record['yt_id'];

    if (!$transcription) {
        echo "Error: Transcription is empty or invalid.\n";
        continue; // Skip to the next record
    }

    // Estimate tokens for the transcription and the summary
    $systemMessage = 'You are a helpful assistant that summarizes text content.';
    $userMessage = "Summarize the following transcription from a video titled (".$title."):\n\n" . $transcription;
    $inputTokens = estimateTokens($systemMessage . $userMessage);
    $maxTokens = 4096;
    $availableTokensForSummary = $maxTokens - $inputTokens;

    // Determine if chunking is required
    $requiresChunking = $availableTokensForSummary < 500; // Allow ~500 tokens for output to ensure space
    $recommendedModel = $requiresChunking ? 'Chunked Processing with gpt-4-turbo' : 'gpt-4-turbo';

    // Calculate the number of chunks (if chunking is required)
    $chunks = [];
    if ($requiresChunking) {
        $chunks = splitIntoChunks($transcription, 3000);
    }
    $chunkCount = count($chunks);

    // Output the walkthrough
    echo "Summary needed for yt_id: {$yt_id}\n";
    echo "Approximate tokens from transcription: {$inputTokens}\n";
    echo "Approximate tokens for summary: {$availableTokensForSummary}\n";
    echo "Recommended model to use: {$recommendedModel}\n";
    if ($requiresChunking) {
        echo "Number of chunks: {$chunkCount}\n";
    }

    // Automatically process if chunks are 5 or fewer
    if ($chunkCount <= 20) {
        echo "--Automatic processing due to chunks--\n";
        $confirmation = 'yes';
    } else {
        // Prompt the user for confirmation
        echo "Do you want to proceed with summary? (yes / no / skip): ";
        $handle = fopen("php://stdin", "r");
        $confirmation = strtolower(trim(fgets($handle)));

        // Default to "yes" if the user presses Enter
        if ($confirmation === '') {
            $confirmation = 'yes';
        }
    }

    if (in_array($confirmation, ['s', 'skip'])) {
        echo "Skipping record with yt_id: {$yt_id}\n";
        continue; // Skip to the next record
    }

    if (!in_array($confirmation, ['y', 'yes'])) {
        echo "Summary process aborted.\n";
        break;
    }

    try {
        if ($requiresChunking) {
            echo "Processing transcription in {$chunkCount} chunks...\n";
            $summaries = [];

            foreach ($chunks as $index => $chunk) {
                echo "Processing chunk " . ($index + 1) . " of {$chunkCount}...\n";
                $response = $client->chat()->create([
                    'model' => 'gpt-4-turbo',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $systemMessage,
                        ],
                        [
                            'role' => 'user',
                            'content' => "Summarize the following transcription:\n\n" . $chunk,
                        ],
                    ],
                    'max_tokens' => 1000, // Reserve space for each chunk
                    'temperature' => 0.7,
                ]);
                $summaries[] = trim($response['choices'][0]['message']['content']);
            }

            // Combine the chunk summaries into a final summary
            $finalSummary = implode(' ', $summaries);
        } else {
            // Process the transcription without chunking
            $response = $client->chat()->create([
                'model' => 'gpt-4-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemMessage,
                    ],
                    [
                        'role' => 'user',
                        'content' => $userMessage,
                    ],
                ],
                'max_tokens' => $availableTokensForSummary,
                'temperature' => 0.7,
            ]);
            $finalSummary = trim($response['choices'][0]['message']['content']);
        }

        // Update the summary in the database
        $updateQuery = $conn->prepare("UPDATE `videos` SET summary = ? WHERE yt_id = ?");
        $updateQuery->bind_param('ss', $finalSummary, $yt_id);

        if ($updateQuery->execute()) {
            echo "Summary successfully updated for yt_id: {$yt_id}\n";
            echo "Summary:\n{$finalSummary}\n";
        } else {
            echo "Error: Failed to update the summary in the database.\n";
        }

        $updateQuery->close();
    } catch (Exception $e) {
        echo "Error communicating with OpenAI: " . $e->getMessage() . "\n";
    }
}

// Close the database connection
$conn->close();
?>
