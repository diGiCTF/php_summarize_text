# php_summarize_text
PHP script designed to process video transcription records stored in a database. It uses OpenAI's GPT-4 model to generate concise summaries of the transcription content and updates the database with the generated summaries. The script supports chunking for long transcriptions and provides an interactive workflow for users.


# Features
Automatic Summary Generation:

Utilizes OpenAI's GPT-4 model to generate a summary for transcriptions stored in the videos table.
Token and Chunk Handling:

Estimates the number of tokens in the transcription.
Automatically splits large transcriptions into manageable chunks (up to 3000 tokens per chunk) for processing.
Interactive Workflow:

Prompts the user to confirm whether to proceed with the summary for each record unless the number of chunks is 5 or fewer.
Automatically processes summaries with 5 or fewer chunks without user confirmation, displaying a message: --Automatic processing due to chunks being under 5--.
Database Integration:

Fetches records from the videos table where the summary column is NULL.
Updates the summary column with the generated summary upon successful processing.

Ensure the .openAI_KEY file is in the same directory and contains your OpenAI API key.
Usage:
```
php analyze.php
```
Interactive workflow:
```
Do you want to proceed with summary? (yes / no / skip):
```
Enter: Defaults to "yes".
yes / y: Proceeds with the summary generation.
no / n: Aborts the script.
skip / s: Skips the current record and moves to the next.
For 5 or fewer chunks, the script processes automatically

# Prerequisites
PHP installed on your system.
Database table structure:
```
CREATE TABLE videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    yt_id VARCHAR(255) NOT NULL,
    transcription TEXT NOT NULL,
    summary TEXT DEFAULT NULL
);
```
A .openAI_KEY file containing your OpenAI API key:
plaintext
```
sk-your-api-key
```

Example:
```
Summary needed for yt_id: -PMPEGUCirw
Approximate tokens from transcription: 25910
Approximate tokens for summary: -21814
Recommended model to use: Chunked Processing with gpt-4-turbo
Number of chunks: 9
Do you want to proceed with summary? (yes / no / skip): yes
Processing transcription in 9 chunks...
Processing chunk 1 of 9...
Processing chunk 2 of 9...
...
Summary successfully updated for yt_id: -PMPEGUCirw
Summary:
[Generated Summary Here]
```
