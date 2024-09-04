
# Simple Game API System

I created this simple API system for my indie game, **Dare or Die**. The system serves as an API for checking and updating certain game elements during runtime.

This system is **not** an account management system but a simple API for storing and updating game-specific information such as player stats and game progress, using Steam or Epic ID as identifiers.

This repository provides a starting point for developers who want to integrate a basic game API system into their own projects. It's especially useful for indie developers looking for a lightweight API solution. Feel free to fork it and add your own features!

**Note:** The database schema and further documentation will be added once development is complete. If you need support or have any questions, feel free to join the [Discord](https://discord.gg/4pWBHE7NHE).

---

## Features

- **Rate Limiter:** Prevents abuse by limiting API requests per user.
- **Redis Cache:** Improves performance by caching database queries in Redis.
- **Player Management:** Adds players to the database using Steam or Epic IDs and stores custom game information.
- **MySQL Database:** Stores all player information and game-related data.
- **PHP 8.2 Compatibility:** Works with the latest PHP version.
- **Leaf Framework:** Utilizes the lightweight [Leaf PHP framework](https://leafphp.dev/) for routing and request handling.

---

## Installation

To get started, clone this repository and set up the environment.

### Clone the Repository

```bash
git clone https://github.com/
```

### Install Dependencies

Make sure you have [Composer](https://getcomposer.org/) installed.

```bash
cd simple-game-api-system
composer install
```

### Setup Environment

1. **Database Setup:** Create a MySQL database for the API and configure the `config.php` file with your database credentials.
2. **Redis Setup:** Ensure you have a Redis server running. Modify the `config.php` file to include the correct Redis connection details.

---

## Configuration

Create or modify the `config.php` file for your database and Redis configurations. Here's an example:

```php
<?php
define('RATE_LIMIT', 666666); // Max requests per user
define('RATE_LIMIT_TIME_WINDOW', 60); // Time window in seconds

define('B_DEBUG', true); // Debug mode

// Database connection settings
define('DB_CONFIG', 'mysql:host=localhost;dbname=db_n_dod_api_v1;charset=utf8mb4');
define('DB_UN', 'your_db_username');
define('DB_PW', 'your_db_password');

// Redis configuration
define('REDIS_ENABLE', true);
define('REDIS_TTL', 360000); // Redis TTL in seconds
define('REDIS_PW', 'your_redis_password');

// API credentials
define('API_KEY', 'your_api_key');
define('API_UN', 'your_api_username');
define('API_PW', 'your_api_password');
?>
```

---

## API Endpoints



---

## License

This project is licensed under the MPL-2 License - see the [LICENSE](LICENSE) file for details.
