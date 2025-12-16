<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Message Service API</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }
        .container {
            max-width: 600px;
            padding: 40px;
            text-align: center;
        }
        h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 600;
        }
        .subtitle {
            color: #8892b0;
            font-size: 1.1rem;
            margin-bottom: 40px;
        }
        .links {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .link-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 20px 25px;
            text-decoration: none;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s ease;
        }
        .link-card:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
            transform: translateX(5px);
        }
        .link-info {
            text-align: left;
        }
        .link-title {
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 5px;
        }
        .link-desc {
            color: #8892b0;
            font-size: 0.85rem;
        }
        .link-arrow {
            color: #8892b0;
            font-size: 1.2rem;
        }
        .tech-stack {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        .tech-title {
            color: #8892b0;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 15px;
        }
        .tech-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
        }
        .badge {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            color: #ccd6f6;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Message Service</h1>
        <p class="subtitle">Automatic message sending system with rate limiting</p>

        <div class="links">
            <a href="/api/messages/sent" target="_blank" class="link-card">
                <div class="link-info">
                    <div class="link-title">API Endpoint</div>
                    <div class="link-desc">GET /api/messages/sent</div>
                </div>
                <span class="link-arrow">→</span>
            </a>

            <a href="/api/documentation" target="_blank" class="link-card">
                <div class="link-info">
                    <div class="link-title">Swagger Documentation</div>
                    <div class="link-desc">Interactive API documentation</div>
                </div>
                <span class="link-arrow">→</span>
            </a>

            <a href="https://github.com/HayriCan/message-service" target="_blank" class="link-card">
                <div class="link-info">
                    <div class="link-title">GitHub Repository</div>
                    <div class="link-desc">Source code and documentation</div>
                </div>
                <span class="link-arrow">→</span>
            </a>
        </div>

        <div class="tech-stack">
            <div class="tech-title">Built with</div>
            <div class="tech-badges">
                <span class="badge">Laravel 12</span>
                <span class="badge">PHP 8.2</span>
                <span class="badge">Redis</span>
                <span class="badge">MySQL</span>
                <span class="badge">Docker</span>
            </div>
        </div>
    </div>
</body>
</html>
