# Message Service

Automatic message sending system that processes messages from a database and sends them via webhook with rate limiting.

## Features

- **Automatic Message Sending**: Processes pending messages and sends them via external webhook
- **Rate Limiting**: Sends maximum 2 messages per 5 seconds
- **Queue-based Processing**: Uses Laravel Queue with Redis for background job processing
- **Redis Caching**: Caches sent message info (messageId, sent_at) for 24 hours
- **RESTful API**: Provides endpoint to list sent messages with pagination
- **Repository Pattern**: Clean architecture with Service-Repository pattern
- **Comprehensive Testing**: Unit and Feature tests included

## Requirements

- Docker & Docker Compose
- Git

## Quick Start

### 1. Clone the repository

```bash
git clone https://github.com/HayriCan/message-service.git
cd message-service
```

### 2. Copy environment file

```bash
cp .env.example .env
```

### 3. Configure webhook.site

1. Go to [webhook.site](https://webhook.site) and copy your unique URL
2. Click **Edit** button (top right)
3. Configure the response:
   - **Status code:** `202`
   - **Content-Type:** `application/json`
   - **Response body:**
   ```json
   {
       "message": "Accepted",
       "messageId": "$request.uuid$"
   }
   ```
4. Click **Save**

### 4. Update environment file

Edit `.env` file and update the following values:

```env
WEBHOOK_URL=https://webhook.site/your-unique-url
WEBHOOK_AUTH_KEY=INS.your-auth-key
```

### 5. Start Docker containers

```bash
docker-compose up -d
```

### 6. Install dependencies and setup

```bash
# Install composer dependencies
docker-compose exec app composer install

# Generate application key
docker-compose exec app php artisan key:generate

# Run migrations
docker-compose exec app php artisan migrate

# (Optional) Seed sample data
docker-compose exec app php artisan db:seed
```

### 7. Generate Swagger documentation

```bash
docker-compose exec app php artisan l5-swagger:generate
```

## Makefile (Optional)

If you have `make` installed, you can use shorthand commands:

```bash
make help          # Show all available commands
make up            # Start containers
make down          # Stop containers
make dispatch      # Process pending messages
make test          # Run all tests
make setup         # Full initial setup (install, key, migrate, seed, swagger)
```

Run `make help` to see all available commands.

## Usage

### Sending Messages

#### 1. Start the queue worker

The queue worker is already running as a separate container. If you need to restart it:

```bash
docker-compose restart queue
```

**Important:** You must restart the queue worker after:
- Changing `.env` file (environment variables)
- Modifying job classes or related code
- Updating configuration files

Or manually:

```bash
docker-compose exec app php artisan queue:work redis --sleep=3 --tries=3
```

#### 2. Process pending messages

```bash
docker-compose exec app php artisan messages:send
```

Options:
- `--limit=100`: Maximum number of messages to process (default: 100)
- `--reset-stale`: Reset stuck processing messages back to pending
- `--retry-failed`: Reset failed messages to pending and retry

Example:

```bash
# Process up to 50 messages
docker-compose exec app php artisan messages:send --limit=50

# Reset stale messages and process
docker-compose exec app php artisan messages:send --reset-stale

# Retry failed messages
docker-compose exec app php artisan messages:send --retry-failed
```

### API Endpoints

#### Get Sent Messages

```http
GET /api/messages/sent
```

Query Parameters:
- `page`: Page number (default: 1)
- `per_page`: Items per page (default: 15, max: 100)

Example Request:

```bash
curl http://localhost:8080/api/messages/sent?page=1&per_page=10
```

Example Response:

```json
{
  "data": [
    {
      "id": 1,
      "phone_number": "+905551234567",
      "content": "Hello World",
      "message_id": "abc-123-def",
      "sent_at": "2025-01-01T12:00:00+00:00"
    }
  ],
  "links": {
    "first": "http://localhost:8080/api/messages/sent?page=1",
    "last": "http://localhost:8080/api/messages/sent?page=10",
    "prev": null,
    "next": "http://localhost:8080/api/messages/sent?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 10,
    "per_page": 15,
    "to": 15,
    "total": 150
  }
}
```

### Swagger Documentation

After generating documentation, access it at:

```
http://localhost:8080/api/documentation
```

## Testing

Run all tests:

```bash
docker-compose exec app php artisan test
```

Run specific test suites:

```bash
# Unit tests only
docker-compose exec app php artisan test --testsuite=Unit

# Feature tests only
docker-compose exec app php artisan test --testsuite=Feature
```

## Architecture

### Directory Structure

```
app/
├── Console/Commands/
│   └── SendMessagesCommand.php     # Artisan command for processing messages
├── Enums/
│   └── MessageStatus.php           # Message status enum (pending, processing, sent, failed)
├── Http/
│   ├── Controllers/Api/
│   │   └── MessageController.php   # API controller for messages
│   └── Resources/
│       └── MessageResource.php     # API resource for message transformation
├── Jobs/
│   └── SendMessageJob.php          # Queue job for sending individual messages
├── Models/
│   └── Message.php                 # Eloquent model
├── Repositories/
│   ├── Contracts/
│   │   └── MessageRepositoryInterface.php
│   └── MessageRepository.php       # Repository implementation
└── Services/
    └── MessageService.php          # Business logic layer
```

### Message Flow

1. Messages are created in the database with `pending` status
2. `php artisan messages:send` command fetches pending messages
3. Messages are marked as `processing` and dispatched to queue with delays (rate limiting)
4. Queue worker processes jobs:
   - Validates character limit
   - Sends HTTP request to webhook
   - On success (HTTP 202): marks as `sent`, stores messageId, caches info
   - On client error (4xx): marks as `failed`
   - On server error (5xx): retries up to 3 times, then marks as `failed`

### Rate Limiting Strategy

Messages are chunked in groups of 2 and dispatched with 5-second delays:

- Chunk 1: delay 0s
- Chunk 2: delay 5s
- Chunk 3: delay 10s
- ...

This guarantees a maximum of 2 messages per 5 seconds.

## Configuration

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `DB_CONNECTION` | Database driver | mysql |
| `DB_HOST` | Database host | mysql |
| `DB_PORT` | Database port | 3306 |
| `DB_DATABASE` | Database name | message_service |
| `DB_USERNAME` | Database username | message_service |
| `DB_PASSWORD` | Database password | secret |
| `REDIS_HOST` | Redis host | redis |
| `REDIS_PORT` | Redis port | 6379 |
| `WEBHOOK_URL` | External webhook URL | - |
| `WEBHOOK_AUTH_KEY` | Webhook authentication key | - |
| `WEBHOOK_TIMEOUT` | HTTP request timeout (seconds) | 30 |
| `MESSAGE_CHAR_LIMIT` | Maximum message character length | 160 |
| `QUEUE_CONNECTION` | Queue driver | redis |
| `CACHE_STORE` | Cache driver | redis |

### Config Files

- `config/services.php`: Webhook configuration
- `config/message.php`: Message-related settings (char limit, rate limiting, cache)

## Docker Services

| Service | Port | Description |
|---------|------|-------------|
| app | - | PHP-FPM application |
| nginx | 8080 | Web server |
| mysql | 3306 | Database |
| redis | 6379 | Cache & Queue |
| queue | - | Queue worker |

## Webhook Integration

The system expects the webhook to return:

**Success Response (HTTP 202)**

```json
{
  "message": "Accepted",
  "messageId": "unique-message-id"
}
```

**Error Response (HTTP 4xx/5xx)**

Any non-202 status code is treated as an error.

## License

MIT
