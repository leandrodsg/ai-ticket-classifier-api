# Integration Guide

This guide covers how to integrate the AI Ticket Classifier API into your application.

## Integration Flow

```
1. Generate CSV → 2. Upload CSV → 3. Poll Status → 4. Get Results
```

### Step 1: Generate Test CSV

Request a CSV file with test tickets:

```bash
curl -X POST http://localhost:8000/api/csv/generate \
  -H "Content-Type: application/json" \
  -d '{"ticket_count": 5}'
```

Response:

```json
{
  "success": true,
  "data": {
    "csv_content": "IyBYLVNlc3Npb24tSWQ6IDlkM2U0ZjVh...",
    "session_id": "9d3e4f5a-1234-5678-9abc-def012345678",
    "file_name": "tickets_20251229_103045.csv",
    "row_count": 5
  }
}
```

Save the `csv_content` and metadata for the next step.

### Step 2: Upload CSV

Decode the CSV to extract metadata headers:

```javascript
const csvContent = atob(response.data.csv_content);
const lines = csvContent.split('\n');

// Extract metadata
const signature = lines.find(l => l.startsWith('# X-Signature:')).split(': ')[1];
const nonce = lines.find(l => l.startsWith('# X-Nonce:')).split(': ')[1];
const timestamp = lines.find(l => l.startsWith('# X-Timestamp:')).split(': ')[1];
```

Upload with required headers:

```javascript
const uploadResponse = await fetch('/api/tickets/upload', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-Signature': signature,
    'X-Nonce': nonce,
    'X-Timestamp': timestamp
  },
  body: JSON.stringify({
    csv_content: response.data.csv_content,
    file_name: 'support_tickets.csv'
  })
});
```

Response:

```json
{
  "success": true,
  "data": {
    "session_id": "9d3e4f5a-1234-5678-9abc-def012345678",
    "status": "pending",
    "total_tickets": 5,
    "message": "Classification job created"
  }
}
```

### Step 3: Poll Job Status

Query job status using the session ID:

```javascript
async function pollJobStatus(sessionId) {
  const maxAttempts = 20;
  const delays = [1000, 2000, 4000, 8000, 15000];
  
  for (let i = 0; i < maxAttempts; i++) {
    const response = await fetch(`/api/tickets/${sessionId}`);
    const data = await response.json();
    
    if (data.data.status === 'completed') {
      return data.data;
    }
    
    if (data.data.status === 'failed') {
      throw new Error(data.data.error_message);
    }
    
    const delay = delays[Math.min(i, delays.length - 1)];
    await new Promise(resolve => setTimeout(resolve, delay));
  }
  
  throw new Error('Job timeout');
}
```

### Step 4: Process Results

Once completed, the response includes all classified tickets:

```json
{
  "success": true,
  "data": {
    "session_id": "9d3e4f5a-1234-5678-9abc-def012345678",
    "status": "completed",
    "created_at": "2025-12-29T10:30:00+00:00",
    "completed_at": "2025-12-29T10:30:45+00:00",
    "results": {
      "total_tickets": 5,
      "processing_time_ms": 1234
    },
    "tickets": [
      {
        "issue_key": "TICKET-001",
        "summary": "Cannot access email",
        "category": "technical",
        "priority": "high",
        "urgency": "high",
        "impact": "medium",
        "sentiment": "negative",
        "confidence_score": 0.95,
        "sla_due_date": "2025-12-29T14:30:00+00:00"
      }
    ]
  }
}
```

## Code Examples

### Python

```python
import requests
import base64
import time

BASE_URL = "http://localhost:8000/api"

# Generate CSV
response = requests.post(f"{BASE_URL}/csv/generate", json={"ticket_count": 5})
csv_data = response.json()["data"]

# Decode and extract metadata
csv_content = base64.b64decode(csv_data["csv_content"]).decode()
lines = csv_content.split("\n")

metadata = {}
for line in lines:
    if line.startswith("# X-Signature:"):
        metadata["signature"] = line.split(": ")[1]
    elif line.startswith("# X-Nonce:"):
        metadata["nonce"] = line.split(": ")[1]
    elif line.startswith("# X-Timestamp:"):
        metadata["timestamp"] = line.split(": ")[1]

# Upload CSV
upload_response = requests.post(
    f"{BASE_URL}/tickets/upload",
    headers={
        "X-Signature": metadata["signature"],
        "X-Nonce": metadata["nonce"],
        "X-Timestamp": metadata["timestamp"]
    },
    json={"csv_content": csv_data["csv_content"]}
)

session_id = upload_response.json()["data"]["session_id"]

# Poll status
while True:
    status_response = requests.get(f"{BASE_URL}/tickets/{session_id}")
    status_data = status_response.json()["data"]
    
    if status_data["status"] == "completed":
        print("Classification complete!")
        print(f"Total tickets: {status_data['results']['total_tickets']}")
        break
    elif status_data["status"] == "failed":
        print(f"Classification failed: {status_data['error_message']}")
        break
    
    time.sleep(2)
```

### Node.js

```javascript
const axios = require('axios');

const BASE_URL = 'http://localhost:8000/api';

async function classifyTickets() {
  // Generate CSV
  const generateResponse = await axios.post(`${BASE_URL}/csv/generate`, {
    ticket_count: 5
  });
  
  const csvData = generateResponse.data.data;
  
  // Decode and extract metadata
  const csvContent = Buffer.from(csvData.csv_content, 'base64').toString();
  const lines = csvContent.split('\n');
  
  const signature = lines.find(l => l.startsWith('# X-Signature:')).split(': ')[1];
  const nonce = lines.find(l => l.startsWith('# X-Nonce:')).split(': ')[1];
  const timestamp = lines.find(l => l.startsWith('# X-Timestamp:')).split(': ')[1];
  
  // Upload CSV
  const uploadResponse = await axios.post(`${BASE_URL}/tickets/upload`, {
    csv_content: csvData.csv_content
  }, {
    headers: {
      'X-Signature': signature,
      'X-Nonce': nonce,
      'X-Timestamp': timestamp
    }
  });
  
  const sessionId = uploadResponse.data.data.session_id;
  
  // Poll status
  const delays = [1000, 2000, 4000, 8000];
  let attempt = 0;
  
  while (attempt < 20) {
    const statusResponse = await axios.get(`${BASE_URL}/tickets/${sessionId}`);
    const statusData = statusResponse.data.data;
    
    if (statusData.status === 'completed') {
      console.log('Classification complete!');
      console.log(`Total tickets: ${statusData.results.total_tickets}`);
      return statusData.tickets;
    }
    
    if (statusData.status === 'failed') {
      throw new Error(statusData.error_message);
    }
    
    const delay = delays[Math.min(attempt, delays.length - 1)];
    await new Promise(resolve => setTimeout(resolve, delay));
    attempt++;
  }
  
  throw new Error('Job timeout');
}

classifyTickets()
  .then(tickets => console.log('Tickets:', tickets))
  .catch(err => console.error('Error:', err));
```

### PHP

```php
<?php

$baseUrl = 'http://localhost:8000/api';

// Generate CSV
$generateResponse = file_get_contents($baseUrl . '/csv/generate', false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode(['ticket_count' => 5])
    ]
]));

$csvData = json_decode($generateResponse, true)['data'];

// Decode and extract metadata
$csvContent = base64_decode($csvData['csv_content']);
$lines = explode("\n", $csvContent);

$metadata = [];
foreach ($lines as $line) {
    if (strpos($line, '# X-Signature:') === 0) {
        $metadata['signature'] = trim(explode(': ', $line)[1]);
    } elseif (strpos($line, '# X-Nonce:') === 0) {
        $metadata['nonce'] = trim(explode(': ', $line)[1]);
    } elseif (strpos($line, '# X-Timestamp:') === 0) {
        $metadata['timestamp'] = trim(explode(': ', $line)[1]);
    }
}

// Upload CSV
$uploadResponse = file_get_contents($baseUrl . '/tickets/upload', false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n" .
                   "X-Signature: {$metadata['signature']}\r\n" .
                   "X-Nonce: {$metadata['nonce']}\r\n" .
                   "X-Timestamp: {$metadata['timestamp']}",
        'content' => json_encode(['csv_content' => $csvData['csv_content']])
    ]
]));

$sessionId = json_decode($uploadResponse, true)['data']['session_id'];

// Poll status
$maxAttempts = 20;
$attempt = 0;

while ($attempt < $maxAttempts) {
    $statusResponse = file_get_contents($baseUrl . "/tickets/{$sessionId}");
    $statusData = json_decode($statusResponse, true)['data'];
    
    if ($statusData['status'] === 'completed') {
        echo "Classification complete!\n";
        echo "Total tickets: {$statusData['results']['total_tickets']}\n";
        break;
    }
    
    if ($statusData['status'] === 'failed') {
        die("Classification failed: {$statusData['error_message']}\n");
    }
    
    sleep(2);
    $attempt++;
}
```

## Error Handling

Always implement proper error handling:

```javascript
try {
  const tickets = await classifyTickets();
  // Process tickets
} catch (error) {
  if (error.response?.status === 429) {
    // Rate limit exceeded
    const retryAfter = error.response.headers['retry-after'];
    console.log(`Rate limited. Retry after ${retryAfter}s`);
  } else if (error.response?.status === 503) {
    // Service unavailable
    console.log('AI service temporarily unavailable');
  } else if (error.response?.status === 422) {
    // Validation error
    console.log('Invalid request:', error.response.data.details);
  } else {
    console.log('Unexpected error:', error.message);
  }
}
```

## Production Considerations

### Environment Variables

Configure the API base URL based on environment:

```javascript
const API_BASE_URL = process.env.NODE_ENV === 'production'
  ? 'https://production-url.railway.app/api'
  : 'http://localhost:8000/api';
```

### Timeout Handling

Set reasonable timeouts for long-running operations:

```javascript
const controller = new AbortController();
const timeoutId = setTimeout(() => controller.abort(), 60000); // 60s timeout

try {
  const response = await fetch(url, { signal: controller.signal });
  // Process response
} finally {
  clearTimeout(timeoutId);
}
```

### Logging

Log important events for debugging:

```javascript
console.log(`[${new Date().toISOString()}] Job created: ${sessionId}`);
console.log(`[${new Date().toISOString()}] Job status: ${status}`);
console.log(`[${new Date().toISOString()}] Job completed in ${processingTime}ms`);
```

### Webhook Alternative

For production systems, consider implementing webhooks instead of polling:

1. Include a callback URL in the upload request
2. API calls your webhook when classification completes
3. Reduces API calls and improves efficiency

This requires custom implementation on the API side.
